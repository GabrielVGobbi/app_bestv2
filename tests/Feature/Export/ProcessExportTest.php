<?php

namespace Tests\Feature\Export;

use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
    }

    public function test_job_generates_csv_and_marks_export_completed(): void
    {
        User::factory()->count(3)->create();

        $exporter = User::factory()->create();
        $export = Export::factory()->create([
            'user_id' => $exporter->id,
            'model_class' => User::class,
            'format' => 'csv',
            'columns' => ['name', 'email'],
            'filters' => [],
            'search' => '',
            'sort_column' => 'id',
            'sort_direction' => 'asc',
        ]);

        (new ProcessExport($export))->handle();

        $export->refresh();

        $this->assertEquals(Export::STATUS_COMPLETED, $export->status);
        $this->assertNotNull($export->file_path);
        $this->assertNotNull($export->file_name);
        $this->assertNotNull($export->file_size);
        $this->assertNotNull($export->completed_at);
        $this->assertNotNull($export->expires_at);
        $this->assertGreaterThan(0, $export->total_records);

        Storage::disk('local')->assertExists($export->file_path);

        Notification::assertSentTo($exporter, ExportReadyNotification::class);
    }

    public function test_job_marks_export_failed_on_exception(): void
    {
        $exporter = User::factory()->create();

        $export = Export::factory()->create([
            'user_id' => $exporter->id,
            'model_class' => User::class,
            'format' => 'csv',
            'columns' => [],
        ]);

        // Force a failure by making the model class invalid after creation
        $export->model_class = 'NonExistentModel';
        $export->save();

        $job = new ProcessExport($export->fresh());
        $job->failed(new \RuntimeException('Model not found'));

        $export->refresh();
        $this->assertEquals(Export::STATUS_FAILED, $export->status);
        $this->assertEquals('Model not found', $export->error_message);
    }

    public function test_job_exports_all_columns_when_none_selected(): void
    {
        User::factory()->count(2)->create();

        $exporter = User::factory()->create();
        $export = Export::factory()->create([
            'user_id' => $exporter->id,
            'model_class' => User::class,
            'format' => 'csv',
            'columns' => [],
        ]);

        (new ProcessExport($export))->handle();

        $export->refresh();

        $this->assertEquals(Export::STATUS_COMPLETED, $export->status);
        Storage::disk('local')->assertExists($export->file_path);
    }
}
