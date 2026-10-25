<?php

namespace Tests\Feature\Export;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Notifications\ExportReadyNotification;
use App\Policies\UserPolicy;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ProcessExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateProjectFiles();
        Storage::fake('local');
        Notification::fake();
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        config([
            'permissions.super_role' => 'developer',
            'exports.allowed_models' => [...config('exports.allowed_models'), SpreadsheetExportUser::class],
        ]);
        Gate::policy(SpreadsheetExportUser::class, UserPolicy::class);
    }

    public function test_job_generates_csv_and_marks_export_completed(): void
    {
        User::factory()->count(3)->create();

        $exporter = $this->exportActor();
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
        $exporter = $this->exportActor();

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
        $job->failed(new RuntimeException('Model not found'));

        $export->refresh();
        $this->assertEquals(Export::STATUS_FAILED, $export->status);
        $this->assertSame(__('Export could not be completed. Please request a new export.'), $export->error_message);
        $this->assertStringNotContainsString('Model not found', $export->error_message);
    }

    public function test_job_exports_all_columns_when_none_selected(): void
    {
        User::factory()->count(2)->create();

        $exporter = $this->exportActor();
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

    #[DataProvider('formats')]
    public function test_untrusted_strings_and_headers_cannot_be_written_as_formulas(string $format): void
    {
        $cases = [
            ['=1+1', "'=1+1"],
            ['+SUM(1,1)', "'+SUM(1,1)"],
            ['-0012', "'-0012"],
            ['@SUM(1,1)', "'@SUM(1,1)"],
            ['＝1+1', "'＝1+1"],
            ['＋SUM(1,1)', "'＋SUM(1,1)"],
            ['－0012', "'－0012"],
            ['＠SUM(1,1)', "'＠SUM(1,1)"],
            ['  =1+1', "'  =1+1"],
            [' +1', "' +1"],
            ["\v-1", "'\v-1"],
            ["\f@SUM(1,1)", "'\f@SUM(1,1)"],
            ['  ＝1+1', "'  ＝1+1"],
            ["\t=1+1", "'\t=1+1"],
            ["\r=1+1", "'\r=1+1"],
            ["\n=1+1", "'\n=1+1"],
            ["\r\n+1", "'\r\n+1"],
            ["\tplain text", "'\tplain text"],
            ['0012', '0012'],
            ['12.50', '12.50'],
            ["'already text", "'already text"],
            ['plain text', 'plain text'],
            ['  plain text', '  plain text'],
            ['Ação e café', 'Ação e café'],
        ];
        $export = $this->generateArtifact($format, array_column($cases, 0), ['name', 'email']);
        $path = Storage::disk('local')->path($export->file_path);

        if ($format === 'xlsx') {
            $sheet = $this->worksheet($path);
            foreach ($sheet['xpath']->query('//s:c') as $cell) {
                $this->assertInstanceOf(DOMElement::class, $cell);
                $this->assertContains($cell->getAttribute('t'), ['inlineStr', 's']);
            }
            $this->assertStringContainsString("\r=1+1", $sheet['xml']);
            $this->assertStringContainsString("\r\n+1", $sheet['xml']);
        }

        $rows = $this->readRows($format, $path);
        $expected = [
            $format === 'xlsx' ? ['=SEC017 name', '＠SEC017 email'] : ["'=SEC017 name", "'＠SEC017 email"],
            ['SEC017 owner control', 'sec017-owner@example.test'],
        ];
        foreach ($cases as $index => [$original, $delimited]) {
            $expected[] = [$format === 'xlsx' ? $this->xmlReaderValue($original) : $delimited, "sec017-{$index}@example.test"];
        }

        $this->assertSame($expected, $rows);
        foreach ($rows as $row) {
            $this->assertCount(2, $row);
        }
    }

    #[DataProvider('formats')]
    public function test_export_preserves_field_boundaries_and_intentional_scalar_types(string $format): void
    {
        $names = ["left,\"quoted\"\tmiddle\r\n=1+1", 'safe text with =1+1 inside'];
        $columns = ['name', 'email', 'negative_integer', 'negative_float', 'zero_number', 'truth', 'falsehood', 'empty_value', 'date_value', 'numeric_text', 'signed_numeric_text', 'end_marker'];
        $export = $this->generateArtifact($format, $names, $columns);
        $path = Storage::disk('local')->path($export->file_path);

        if ($format === 'xlsx') {
            $sheet = $this->worksheet($path);
            $this->assertStringContainsString("left,&quot;quoted&quot;\tmiddle\r\n=1+1", $sheet['xml']);

            foreach (['C2', 'D2', 'E2'] as $reference) {
                $cell = $sheet['xpath']->query("//s:c[@r='{$reference}']")->item(0);
                $this->assertInstanceOf(DOMElement::class, $cell);
                $this->assertContains($cell->getAttribute('t'), ['', 'n']);
            }
            foreach (['F2', 'G2'] as $reference) {
                $cell = $sheet['xpath']->query("//s:c[@r='{$reference}']")->item(0);
                $this->assertInstanceOf(DOMElement::class, $cell);
                $this->assertSame('b', $cell->getAttribute('t'));
            }
            foreach (['A2', 'B2', 'I2', 'J2', 'K2', 'L2'] as $reference) {
                $cell = $sheet['xpath']->query("//s:c[@r='{$reference}']")->item(0);
                $this->assertInstanceOf(DOMElement::class, $cell);
                $this->assertContains($cell->getAttribute('t'), ['inlineStr', 's']);
            }
        }

        $rows = $this->readRows($format, $path);
        $this->assertCount(4, $rows);
        $this->assertSame([
            $format === 'xlsx' ? '=SEC017 name' : "'=SEC017 name",
            $format === 'xlsx' ? '＠SEC017 email' : "'＠SEC017 email",
            'Negative integer', 'Negative float', 'Zero', 'True', 'False', 'Empty', 'Date', 'Numeric text', 'Signed numeric text', 'End marker',
        ], $rows[0]);

        foreach (['SEC017 owner control', ...$names] as $index => $name) {
            $email = $index === 0 ? 'sec017-owner@example.test' : 'sec017-'.($index - 1).'@example.test';
            $expected = $format === 'xlsx'
                ? [$this->xmlReaderValue($name), $email, -42, -12.5, 0, true, false, '', '2030-01-02 03:04:05', '0012', '-0012', 'right-side sentinel']
                : [$name, $email, '-42', '-12.5', '0', '1', '0', '', '2030-01-02 03:04:05', '0012', "'-0012", 'right-side sentinel'];

            $this->assertCount(12, $rows[$index + 1]);
            $this->assertSame($expected, $rows[$index + 1]);
        }
    }

    /** @return array<string, array{string}> */
    public static function formats(): array
    {
        return ['CSV' => ['csv'], 'TSV' => ['tsv'], 'XLSX' => ['xlsx']];
    }

    #[DataProvider('largeArtifactSorts')]
    public function test_large_exports_write_each_record_once_in_the_requested_order(string $format, string $sort, string $direction): void
    {
        $actor = $this->exportActor();
        $fixtures = $this->largeExportFixtures();
        $expected = $this->orderedFixtureRows($fixtures, $sort, $direction, $format);
        $export = $this->largeExport($actor, $format, $sort, $direction);
        $progress = [];
        $dispatcher = Export::getEventDispatcher();
        $events = clone $dispatcher;
        Export::setEventDispatcher($events);
        $events->listen('eloquent.updated: '.Export::class, function (Export $updated) use ($export, &$progress): void {
            if ($updated->id === $export->id && $updated->status === Export::STATUS_PROCESSING && $updated->wasChanged('processed_records')) {
                $progress[] = $updated->processed_records;
            }
        });

        try {
            (new ProcessExport($export))->handle();
        } finally {
            Export::setEventDispatcher($dispatcher);
        }

        $export->refresh();
        $this->assertSame(Export::STATUS_COMPLETED, $export->status);
        $rows = $this->readRows($format, Storage::disk('local')->path($export->file_path));
        $this->assertSame(['ID', $format === 'xlsx' ? '=SEC017 name' : "'=SEC017 name", $format === 'xlsx' ? '＠SEC017 email' : "'＠SEC017 email"], array_shift($rows));
        $this->assertCount(1207, $rows);
        $this->assertCount(1207, array_unique(array_column($rows, 0)));
        $this->assertCount(1207, array_unique(array_column($rows, 2)));
        $this->assertSame($expected, $rows);
        $this->assertSame([500, 1000, 1207], $progress);
        $this->assertSame(count($rows), $export->processed_records);
        $this->assertSame(count($rows), $export->total_records);
        $this->assertCount(1207, $fixtures);
        $this->assertDatabaseCount('users', 1208);
        $this->assertFileDoesNotExist(sys_get_temp_dir().DIRECTORY_SEPARATOR.$export->uuid.'.'.$format);
        Notification::assertSentTo($actor, ExportReadyNotification::class);
        Notification::assertCount(1);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, string, string}> */
    public static function largeArtifactSorts(): array
    {
        $cases = [];
        foreach (['csv', 'tsv', 'xlsx'] as $format) {
            foreach ([['name', 'asc'], ['name', 'desc'], ['email', 'desc'], ['id', 'desc']] as [$sort, $direction]) {
                $cases["{$format} {$sort} {$direction}"] = [$format, $sort, $direction];
            }
        }
        $cases['csv default id descending'] = ['csv', '', ''];
        $cases['csv nullable created_at ascending'] = ['csv', 'created_at', 'asc'];
        $cases['csv nullable created_at descending'] = ['csv', 'created_at', 'desc'];

        return $cases;
    }

    #[DataProvider('formats')]
    public function test_completed_progress_counts_written_rows_when_the_initial_estimate_changes(string $format): void
    {
        $actor = $this->exportActor();
        $fixtures = $this->largeExportFixtures();
        $removed = array_pop($fixtures);
        $export = $this->largeExport($actor, $format, 'name', 'asc');
        $initialEstimate = null;
        $dispatcher = Export::getEventDispatcher();
        $events = clone $dispatcher;
        Export::setEventDispatcher($events);
        $events->listen('eloquent.updated: '.Export::class, function (Export $updated) use ($export, $removed, &$initialEstimate): void {
            if ($updated->id === $export->id && $updated->status === Export::STATUS_PROCESSING && $updated->wasChanged('total_records')) {
                $initialEstimate = $updated->total_records;
                User::query()->whereKey($removed['id'])->delete();
            }
        });

        try {
            (new ProcessExport($export))->handle();
        } finally {
            Export::setEventDispatcher($dispatcher);
        }

        $export->refresh();
        $this->assertSame(1207, $initialEstimate);
        $this->assertSame(Export::STATUS_COMPLETED, $export->status);
        $rows = $this->readRows($format, Storage::disk('local')->path($export->file_path));
        array_shift($rows);
        $this->assertCount(1206, $rows);
        $this->assertSame($this->orderedFixtureRows($fixtures, 'name', 'asc', $format), $rows);
        $this->assertSame(count($rows), $export->processed_records);
        $this->assertSame(count($rows), $export->total_records);
        Notification::assertCount(1);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    #[DataProvider('formats')]
    public function test_interrupted_export_restarts_without_duplicate_rows_or_stale_progress(string $format): void
    {
        $actor = $this->exportActor();
        $fixtures = $this->largeExportFixtures();
        $export = $this->largeExport($actor, $format, 'name', 'desc');
        $dispatcher = Export::getEventDispatcher();
        $events = clone $dispatcher;
        Export::setEventDispatcher($events);
        $loaded = 0;
        $events->listen('eloquent.retrieved: '.SpreadsheetExportUser::class, function () use (&$loaded): void {
            if (++$loaded === 501) {
                throw new RuntimeException('EXP001 interrupted between batches');
            }
        });

        try {
            (new ProcessExport($export))->handle();
            $this->fail('The controlled interruption must reach the worker retry boundary.');
        } catch (RuntimeException $exception) {
            $this->assertSame('EXP001 interrupted between batches', $exception->getMessage());
        } finally {
            Export::setEventDispatcher($dispatcher);
        }

        $export->refresh();
        $this->assertSame(Export::STATUS_PROCESSING, $export->status);
        $this->assertSame(500, $export->processed_records);
        $this->assertSame(1207, $export->total_records);
        $this->assertNull($export->file_path);
        $this->assertNull($export->completed_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertFileDoesNotExist(sys_get_temp_dir().DIRECTORY_SEPARATOR.$export->uuid.'.'.$format);
        Notification::assertNothingSent();

        $retryProgress = [];
        $events = clone $dispatcher;
        Export::setEventDispatcher($events);
        $events->listen('eloquent.updated: '.Export::class, function (Export $updated) use ($export, &$retryProgress): void {
            if ($updated->id === $export->id && $updated->status === Export::STATUS_PROCESSING && $updated->wasChanged('processed_records')) {
                $retryProgress[] = $updated->processed_records;
            }
        });

        try {
            (new ProcessExport($export))->handle();
        } finally {
            Export::setEventDispatcher($dispatcher);
        }

        $export->refresh();
        $this->assertSame(Export::STATUS_COMPLETED, $export->status);
        $rows = $this->readRows($format, Storage::disk('local')->path($export->file_path));
        array_shift($rows);
        $this->assertCount(1207, $rows);
        $this->assertCount(1207, array_unique(array_column($rows, 0)));
        $this->assertSame($this->orderedFixtureRows($fixtures, 'name', 'desc', $format), $rows);
        $this->assertSame([0, 500, 1000, 1207], $retryProgress);
        $this->assertSame(1207, $export->processed_records);
        $this->assertSame(1207, $export->total_records);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertFileDoesNotExist(sys_get_temp_dir().DIRECTORY_SEPARATOR.$export->uuid.'.'.$format);
        Notification::assertSentTo($actor, ExportReadyNotification::class);
        Notification::assertCount(1);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_serialization_failure_counts_only_rows_successfully_written_in_the_partial_batch(): void
    {
        $actor = $this->exportActor();
        $fixtures = $this->largeExportFixtures();
        $export = $this->largeExport($actor, 'csv', 'id', 'asc');
        $dispatcher = Export::getEventDispatcher();
        $events = clone $dispatcher;
        Export::setEventDispatcher($events);
        $loaded = 0;
        $events->listen('eloquent.retrieved: '.SpreadsheetExportUser::class, function (SpreadsheetExportUser $record) use (&$loaded): void {
            if (++$loaded === 503) {
                $record->setAttribute('name', new \stdClass);
            }
        });

        try {
            (new ProcessExport($export))->handle();
            $this->fail('The unsupported value on row 503 must fail during serialization.');
        } catch (\TypeError $exception) {
            $this->assertStringContainsString('OpenSpout\\Common\\Entity\\Cell::fromValue()', $exception->getMessage());
        } finally {
            Export::setEventDispatcher($dispatcher);
        }

        $export->refresh();
        $this->assertSame(Export::STATUS_PROCESSING, $export->status);
        $this->assertSame(502, $export->processed_records);
        $this->assertSame(1207, $export->total_records);
        $this->assertNull($export->file_path);
        $this->assertNull($export->completed_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertFileDoesNotExist(sys_get_temp_dir().DIRECTORY_SEPARATOR.$export->uuid.'.csv');
        $this->assertDatabaseHas('users', ['id' => $fixtures[502]['id'], 'name' => $fixtures[502]['name']]);
        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    /** @return list<array{id: int, name: string, email: string, created_at: ?string}> */
    private function largeExportFixtures(): array
    {
        $defaults = User::factory()->make()->getAttributes();
        $fixtures = [];
        $inserts = [];
        for ($index = 0; $index < 1207; $index++) {
            $fixture = [
                'id' => 10000 + $index * 7,
                'name' => sprintf('EXP001 fixture %04d', intdiv(($index * 37) % 1207, 7)),
                'email' => sprintf('exp001-%04d@example.test', ($index * 53) % 1207),
                'created_at' => $index % 3 === 0 ? null : sprintf('2030-01-%02d 00:00:00', 1 + (($index * 19) % 28)),
            ];
            $fixtures[] = $fixture;
            $inserts[] = [...$defaults, ...$fixture, 'uuid' => sprintf('00000000-0000-4000-8000-%012d', $index + 1)];
        }
        foreach (array_chunk($inserts, 50) as $batch) {
            User::query()->insert($batch);
        }

        return $fixtures;
    }

    private function largeExport(User $actor, string $format, string $sort, string $direction): Export
    {
        config(['exports.chunk_size' => 500]);

        return Export::factory()->create([
            'user_id' => $actor->id,
            'model_class' => SpreadsheetExportUser::class,
            'format' => $format,
            'columns' => ['id', 'name', 'email'],
            'filters' => [],
            'search' => 'EXP001 fixture',
            'sort_column' => $sort,
            'sort_direction' => $direction,
        ]);
    }

    /**
     * @param  list<array{id: int, name: string, email: string, created_at: ?string}>  $fixtures
     * @return list<array{int|string, string, string}>
     */
    private function orderedFixtureRows(array $fixtures, string $sort, string $direction, string $format): array
    {
        if ($sort === '') {
            $sort = 'id';
            $direction = 'desc';
        }
        usort($fixtures, function (array $left, array $right) use ($sort, $direction): int {
            $comparison = $sort === 'id' ? $left['id'] <=> $right['id'] : strcmp($left[$sort] ?? '', $right[$sort] ?? '');

            return ($direction === 'desc' ? -$comparison : $comparison) ?: $left['id'] <=> $right['id'];
        });

        return array_map(fn (array $fixture): array => [
            $format === 'xlsx' ? $fixture['id'] : (string) $fixture['id'],
            $fixture['name'],
            $fixture['email'],
        ], $fixtures);
    }

    #[DataProvider('workerInvalidations')]
    /** @return array<string, array{string}> */
    public static function workerInvalidations(): array
    {
        return [
            'view-users permission revoked' => ['permission'],
            'authority role revoked' => ['role'],
            'super-role configuration changed' => ['configuration'],
            'model removed from allowlist' => ['allowlist'],
            'email verification lost' => ['unverified'],
            'password change required' => ['password'],
            'persisted private column' => ['columns'],
            'persisted nonexistent model' => ['missing-model'],
            'allowlisted real policy but no scope' => ['missing-scope'],
            'processing export retains previous progress' => ['processing'],
            'view-clients permission revoked' => ['client-permission'],
        ];
    }

    public function test_deleted_owner_cascades_export_and_prepared_job_never_uses_another_authenticated_actor(): void
    {
        $owner = $this->exportActor();
        $other = $this->exportActor(['name' => 'SEC016 authorized ambient actor']);
        $this->assertTrue(Gate::forUser($other)->allows('viewAny', User::class));
        $export = Export::factory()->create([
            'user_id' => $owner->id,
            'model_class' => User::class,
            'format' => 'csv',
            'columns' => ['name', 'email'],
        ]);
        $job = new ProcessExport($export->load('user'));
        $this->actingAs($other, 'web');
        $owner->delete();

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
        $otherBefore = $other->refresh()->getRawOriginal();
        $rolesBefore = $other->roles()->orderBy('roles.id')->pluck('roles.id')->all();
        $permissionsBefore = $other->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();

        $job->handle();

        $this->assertDatabaseCount('exports', 0);
        $this->assertSame($otherBefore, $other->refresh()->getRawOriginal());
        $this->assertSame($rolesBefore, $other->roles()->orderBy('roles.id')->pluck('roles.id')->all());
        $this->assertSame($permissionsBefore, $other->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertFileDoesNotExist(sys_get_temp_dir().DIRECTORY_SEPARATOR.$export->uuid.'.csv');
        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    /** @param array<string, mixed> $attributes */
    private function exportActor(array $attributes = []): User
    {
        $actor = User::factory()->create($attributes);
        $actor->roles()->attach(Role::firstOrCreate(['slug' => 'developer'], ['name' => 'developer']));
        foreach (['view-users', 'view-clients'] as $slug) {
            $actor->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }

        return $actor;
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $columns
     */
    private function generateArtifact(string $format, array $names, array $columns): Export
    {
        $exporter = $this->exportActor(['name' => 'SEC017 owner control', 'email' => 'sec017-owner@example.test']);
        foreach ($names as $index => $name) {
            User::factory()->create(['name' => $name, 'email' => "sec017-{$index}@example.test"]);
        }
        $before = User::query()->orderBy('id')->get()->map(fn (User $user): array => $user->getRawOriginal())->all();
        $export = Export::factory()->create([
            'user_id' => $exporter->id,
            'model_class' => SpreadsheetExportUser::class,
            'format' => $format,
            'columns' => $columns,
            'filters' => [],
            'search' => '',
            'sort_column' => 'id',
            'sort_direction' => 'asc',
        ]);
        config(['exports.chunk_size' => 2]);

        (new ProcessExport($export))->handle();
        $export->refresh();

        $this->assertSame(Export::STATUS_COMPLETED, $export->status);
        $this->assertSame(count($names) + 1, $export->total_records);
        $this->assertSame($export->total_records, $export->processed_records);
        $this->assertNotNull($export->completed_at);
        $this->assertNotNull($export->expires_at);
        $this->assertGreaterThan(0, $export->file_size);
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertFileDoesNotExist(sys_get_temp_dir().DIRECTORY_SEPARATOR.$export->uuid.'.'.$format);
        $this->assertSame($before, User::query()->orderBy('id')->get()->map(fn (User $user): array => $user->getRawOriginal())->all());
        Notification::assertSentTo($exporter, ExportReadyNotification::class);
        Notification::assertCount(1);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Http::assertNothingSent();

        return $export;
    }

    /** @return list<array<int, mixed>> */
    private function readRows(string $format, string $path): array
    {
        $rows = [];
        if ($format === 'xlsx') {
            $reader = new XlsxReader;
            $reader->open($path);
            try {
                $sheets = 0;
                foreach ($reader->getSheetIterator() as $sheet) {
                    $sheets++;
                    foreach ($sheet->getRowIterator() as $row) {
                        $rows[] = $row->toArray();
                    }
                }
                $this->assertSame(1, $sheets);
            } finally {
                $reader->close();
            }

            return $rows;
        }

        $bytes = file_get_contents($path);
        $this->assertIsString($bytes);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $bytes);
        $this->assertStringNotContainsString("\0", $bytes);
        $stream = fopen($path, 'rb');
        $this->assertIsResource($stream);
        try {
            $this->assertSame("\xEF\xBB\xBF", fread($stream, 3));
            while (($row = fgetcsv($stream, null, $format === 'tsv' ? "\t" : ',', '"', '')) !== false) {
                $rows[] = $row;
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    /** @return array{xml: string, xpath: DOMXPath} */
    private function worksheet(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertIsString($xml);
        } finally {
            $zip->close();
        }
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($xml, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->assertSame(0, $xpath->query('//s:f')->length, 'The XLSX artifact must contain no formula elements.');

        return ['xml' => $xml, 'xpath' => $xpath];
    }

    /**
     * XML readers normalize CR/CRLF to LF (W3C XML 1.0 section2.11).
     * Raw XML bytes and cell types are checked separately; OpenSpout re-infers reader cell types.
     *
     * @see https://www.w3.org/TR/xml/#sec-line-ends
     */
    private function xmlReaderValue(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}

class SpreadsheetExportUser extends User
{
    protected $table = 'users';

    public function getForeignKey(): string
    {
        return 'user_id';
    }

    protected array $exportable = [
        'id' => 'ID',
        'name' => '=SEC017 name',
        'email' => '＠SEC017 email',
        'negative_integer' => 'Negative integer',
        'negative_float' => 'Negative float',
        'zero_number' => 'Zero',
        'truth' => 'True',
        'falsehood' => 'False',
        'empty_value' => 'Empty',
        'date_value' => 'Date',
        'numeric_text' => 'Numeric text',
        'signed_numeric_text' => 'Signed numeric text',
        'end_marker' => 'End marker',
    ];

    public function getNegativeIntegerAttribute(): int
    {
        return -42;
    }

    public function getNegativeFloatAttribute(): float
    {
        return -12.5;
    }

    public function getZeroNumberAttribute(): int
    {
        return 0;
    }

    public function getTruthAttribute(): bool
    {
        return true;
    }

    public function getFalsehoodAttribute(): bool
    {
        return false;
    }

    public function getEmptyValueAttribute(): ?string
    {
        return null;
    }

    public function getDateValueAttribute(): DateTimeImmutable
    {
        return new DateTimeImmutable('2030-01-02T03:04:05+00:00');
    }

    public function getNumericTextAttribute(): string
    {
        return '0012';
    }

    public function getSignedNumericTextAttribute(): string
    {
        return '-0012';
    }

    public function getEndMarkerAttribute(): string
    {
        return 'right-side sentinel';
    }
}

class UnscopedWorkerExportUser extends Model
{
    use Exportable, Searchable;

    protected $table = 'users';

    protected array $exportable = ['name' => 'Nome', 'email' => 'Email'];
}
