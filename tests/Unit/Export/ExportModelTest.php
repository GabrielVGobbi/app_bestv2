<?php

namespace Tests\Unit\Export;

use App\Models\Export;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_expired_returns_true_when_expires_at_is_past(): void
    {
        $export = Export::factory()->make(['expires_at' => now()->subHour()]);

        $this->assertTrue($export->isExpired());
    }

    public function test_is_expired_returns_false_when_expires_at_is_future(): void
    {
        $export = Export::factory()->make(['expires_at' => now()->addHour()]);

        $this->assertFalse($export->isExpired());
    }

    public function test_is_expired_returns_false_when_expires_at_is_null(): void
    {
        $export = Export::factory()->make(['expires_at' => null]);

        $this->assertFalse($export->isExpired());
    }

    public function test_is_active_returns_true_for_pending_status(): void
    {
        $export = Export::factory()->make(['status' => Export::STATUS_PENDING]);

        $this->assertTrue($export->isActive());
    }

    public function test_is_active_returns_true_for_processing_status(): void
    {
        $export = Export::factory()->make(['status' => Export::STATUS_PROCESSING]);

        $this->assertTrue($export->isActive());
    }

    public function test_is_active_returns_false_for_completed_status(): void
    {
        $export = Export::factory()->make(['status' => Export::STATUS_COMPLETED]);

        $this->assertFalse($export->isActive());
    }

    public function test_is_active_returns_false_for_failed_status(): void
    {
        $export = Export::factory()->make(['status' => Export::STATUS_FAILED]);

        $this->assertFalse($export->isActive());
    }

    public function test_is_owned_by_returns_true_for_correct_user(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $export = Export::factory()->make(['user_id' => 1]);

        $this->assertTrue($export->isOwnedBy($user));
    }

    public function test_is_owned_by_returns_false_for_different_user(): void
    {
        $user = User::factory()->make(['id' => 2]);
        $export = Export::factory()->make(['user_id' => 1]);

        $this->assertFalse($export->isOwnedBy($user));
    }

    public function test_mark_as_processing_updates_status_and_started_at(): void
    {
        $export = Export::factory()->create();

        $export->markAsProcessing();

        $export->refresh();

        $this->assertEquals(Export::STATUS_PROCESSING, $export->status);
        $this->assertNotNull($export->started_at);
    }

    public function test_mark_as_completed_sets_all_completion_fields(): void
    {
        $export = Export::factory()->create(['status' => Export::STATUS_PROCESSING]);

        $export->markAsCompleted('exports/file.csv', 'file.csv', 1024, 100);

        $export->refresh();

        $this->assertEquals(Export::STATUS_COMPLETED, $export->status);
        $this->assertEquals('exports/file.csv', $export->file_path);
        $this->assertEquals('file.csv', $export->file_name);
        $this->assertEquals(1024, $export->file_size);
        $this->assertEquals(100, $export->total_records);
        $this->assertNotNull($export->completed_at);
        $this->assertNotNull($export->expires_at);
    }

    public function test_mark_as_failed_sets_status_and_error_message(): void
    {
        $export = Export::factory()->create(['status' => Export::STATUS_PROCESSING]);

        $export->markAsFailed('Connection timeout.');

        $export->refresh();

        $this->assertEquals(Export::STATUS_FAILED, $export->status);
        $this->assertEquals('Connection timeout.', $export->error_message);
    }
}
