<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Modules\Backup\Models\BackupHistory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemOperator;
use Mockery\CompositeExpectation;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Tests\TestCase;

class ScheduleMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    #[DataProvider('maintenanceCommands')]
    public function test_maintenance_schedule_covers_retention_with_shared_cache_mutexes(string $command, string $expression): void
    {
        $event = $this->event($command);
        $this->assertSame($expression, $event->expression);
        $this->assertSame('UTC', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
        $this->assertSame(1440, $event->expiresAt);
        $this->assertIsolated();
    }

    public static function maintenanceCommands(): array
    {
        return [
            'telemetry' => ['telescope:prune', '0 0 * * *'],
            'exports' => ['exports:prune', '0 0 * * *'],
            'zip' => ['uploads:prune-zips', '0 * * * *'],
            'richtext' => ['rich-text:prune-uploads', '0 0 * * *'],
            'backup run' => ['backup:run', '0 2 * * *'],
            'backup clean' => ['backup:clean', '0 3 * * *'],
            'backup monitor' => ['backup:monitor', '0 4 * * *'],
            'database dumps' => ['database-exports:prune', '0 0 * * *'],
        ];
    }

    public function test_real_boot_has_one_authoritative_timezone_without_changing_historical_effective_clock(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame(config('app.timezone'), date_default_timezone_get());
        $this->assertSame(config('app.timezone'), now()->timezoneName);
    }

    #[DataProvider('backupTimes')]
    public function test_backup_due_times_are_explicit_utc_even_while_application_clock_is_sao_paulo(string $time, string $expected): void
    {
        $this->travelTo(Carbon::parse('2031-05-01 '.$time, 'UTC'));
        foreach (['backup:run', 'backup:clean', 'backup:monitor'] as $command) {
            $this->assertSame($command === $expected, $this->event($command)->isDue($this->app));
        }
        $this->assertSame('America/Sao_Paulo', date_default_timezone_get());
    }

    public static function backupTimes(): array
    {
        return [['02:00:00', 'backup:run'], ['03:00:00', 'backup:clean'], ['04:00:00', 'backup:monitor']];
    }

    public function test_backup_creation_cleaning_and_monitoring_share_the_same_duration_lock(): void
    {
        $run = $this->event('backup:run');
        $clean = $this->event('backup:clean');
        $monitor = $this->event('backup:monitor');
        $this->assertSame($run->mutexName(), $clean->mutexName());
        $this->assertSame($run->mutexName(), $monitor->mutexName());
        $this->assertTrue($run->mutex->create($run));
        try {
            $this->assertFalse($clean->mutex->create($clean));
            $this->assertFalse($monitor->mutex->create($monitor));
        } finally {
            $run->mutex->forget($run);
        }
        $this->assertTrue($clean->mutex->create($clean));
        $clean->mutex->forget($clean);
        $this->assertIsolated();
    }

    public function test_real_backup_cleanup_preserves_newest_staging_and_audit_while_clearing_only_missing_scoped_paths(): void
    {
        [$old, $new] = $this->backupFixture();
        Storage::disk('local')->put('ops002fixture/staging.zip.part', 'active writer');
        Storage::disk('local')->put('other-namespace/missing.zip', 'outside');
        $oldRow = $this->history('local', $old);
        $newRow = $this->history('local', $new);
        $otherRow = $this->history('local', 'other-namespace/missing.zip');
        $otherDisk = $this->history('public', $old);
        $failed = $this->history(null, null, 'failed');
        $before = $oldRow->getRawOriginal();
        $events = [];
        Event::listen(CleanupWasSuccessful::class, function () use (&$events): void {
            $events[] = 'success';
        });
        $this->assertSame(0, Artisan::call('backup:clean'));
        $this->assertSame(['success'], $events);
        $this->assertFalse(Storage::disk('local')->exists($old));
        $this->assertTrue(Storage::disk('local')->exists($new));
        $this->assertSame('active writer', Storage::disk('local')->get('ops002fixture/staging.zip.part'));
        $this->assertNull($oldRow->fresh()->path);
        foreach (['uuid', 'status', 'file_name', 'file_size', 'backed_up_at', 'disk'] as $attribute) {
            $this->assertSame($before[$attribute], $oldRow->fresh()->getRawOriginal($attribute));
        }
        $this->assertSame($new, $newRow->fresh()->path);
        $this->assertSame('other-namespace/missing.zip', $otherRow->fresh()->path);
        $this->assertSame($old, $otherDisk->fresh()->path);
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertDatabaseCount('backup_histories', 5);
        $this->assertIsolated();
    }

    #[DataProvider('deletionFailures')]
    public function test_backup_delete_failures_are_observable_and_do_not_spin_or_clear_history(string $mode, bool $quota): void
    {
        [$old, $new] = $this->backupFixture();
        if ($quota) {
            config(['backup.cleanup.default_strategy.keep_all_backups_for_days' => 10000,
                'backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than' => 0]);
        }
        $row = $this->history('local', $old);
        $before = $row->fresh()->getRawOriginal();
        $original = Storage::disk('local');
        $disk = new class($original->getDriver(), $original->getAdapter(), $original->getConfig()) extends FilesystemAdapter
        {
            public int $deleteCalls = 0;

            public string $failureMode;

            public function delete($paths): bool
            {
                $this->deleteCalls++;
                if ($this->deleteCalls > 1) {
                    throw new RuntimeException('OPS002 bounded regression guard: repeated deletion after no progress.');
                }
                if ($this->failureMode === 'throw') {
                    throw new RuntimeException('OPS002 synthetic deletion failure.');
                }

                return $this->failureMode === 'lie';
            }
        };
        $disk->failureMode = $mode;
        Storage::set('local', $disk);
        $events = [];
        Event::listen(CleanupHasFailed::class, function () use (&$events): void {
            $events[] = 'failure';
        });
        Event::listen(CleanupWasSuccessful::class, function () use (&$events): void {
            $events[] = 'success';
        });
        $this->assertSame(1, Artisan::call('backup:clean'));
        $this->assertSame(1, $disk->deleteCalls, 'No-progress deletion must stop on its first attempt.');
        $this->assertSame(['failure'], $events);
        $this->assertSame($before, $row->fresh()->getRawOriginal());
        $this->assertTrue($original->exists($old));
        $this->assertTrue($original->exists($new));
        $this->assertIsolated();
    }

    public static function deletionFailures(): array
    {
        return ['period false' => ['false', false], 'period exception' => ['throw', false], 'success without absence' => ['lie', false], 'quota false must not loop' => ['false', true]];
    }

    public function test_actual_zip_staging_unknown_names_and_nested_archives_are_preserved(): void
    {
        [$old, $new] = $this->backupFixture();
        $protected = ['ops002fixture/staging.zip.part', 'ops002fixture/unrecognized.zip', 'ops002fixture/nested/2020-01-01-00-00-00.zip'];
        $hashes = [];
        foreach ($protected as $path) {
            Storage::disk('local')->put($path, '');
            $absolute = Storage::disk('local')->path($path);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($absolute, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
            $this->assertTrue($zip->addFromString('fixture.txt', 'OPS002 actual ZIP bytes under active or unknown name'));
            $this->assertTrue($zip->close());
            $this->assertTrue(touch($absolute, now()->subYears(5)->timestamp));
            $this->assertContains(Storage::disk('local')->mimeType($path), ['application/zip', 'application/x-zip', 'application/x-gzip']);
            $hashes[$path] = hash_file('sha256', $absolute);
        }
        $this->assertSame(0, Artisan::call('backup:clean'));
        foreach ($hashes as $path => $hash) {
            $this->assertFileExists(Storage::disk('local')->path($path));
            $this->assertSame($hash, hash_file('sha256', Storage::disk('local')->path($path)));
        }
        $this->assertFalse(Storage::disk('local')->exists($old));
        $this->assertTrue(Storage::disk('local')->exists($new));
        $this->assertIsolated();
    }

    #[DataProvider('backupHealth')]
    public function test_real_monitor_reports_health_without_external_notifications(bool $healthy): void
    {
        $this->backupFixture();
        if (! $healthy) {
            Storage::disk('local')->deleteDirectory('ops002fixture');
        }
        $events = [];
        Event::listen(HealthyBackupWasFound::class, function () use (&$events): void {
            $events[] = true;
        });
        Event::listen(UnhealthyBackupWasFound::class, function () use (&$events): void {
            $events[] = false;
        });
        $this->assertSame($healthy ? 0 : 1, Artisan::call('backup:monitor'));
        $this->assertSame([$healthy], $events);
        $this->assertIsolated();
    }

    public static function backupHealth(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('historyRaces')]
    public function test_cleanup_history_preserves_unverified_or_concurrently_replaced_paths(bool $throw): void
    {
        [$old, $new] = $this->backupFixture();
        Storage::disk('local')->delete($old);
        $row = $this->history('local', $old);
        $before = $row->fresh()->getRawOriginal();
        $base = Storage::disk('local');
        $probe = \Mockery::mock(FilesystemOperator::class);
        $this->assertInstanceOf(FilesystemOperator::class, $probe);
        $afterReplacement = null;
        $expectation = $probe->shouldReceive('fileExists');
        $this->assertInstanceOf(CompositeExpectation::class, $expectation);
        $expectation->__call('once', [])->__call('with', [$old])->__call('andReturnUsing', [function () use ($throw, $row, $new, &$afterReplacement): bool {
            if ($throw) {
                throw new RuntimeException('OPS002 existence check unavailable.');
            }
            $row->update(['path' => $new]);
            $afterReplacement = $row->fresh()->getRawOriginal();

            return false;
        }]);
        $disk = new class($base->getDriver(), $base->getAdapter(), $base->getConfig()) extends FilesystemAdapter
        {
            public FilesystemOperator $probe;

            public function getDriver(): FilesystemOperator
            {
                return $this->probe;
            }
        };
        $disk->probe = $probe;
        Storage::set('local', $disk);
        $failure = null;
        try {
            Event::dispatch(new CleanupWasSuccessful('local', 'ops002fixture'));
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }
        if ($throw) {
            $this->assertInstanceOf(RuntimeException::class, $failure);
            $this->assertSame('OPS002 existence check unavailable.', $failure->getMessage());
            $this->assertSame($before, $row->fresh()->getRawOriginal());
        } else {
            $this->assertNull($failure);
            $this->assertSame($afterReplacement, $row->fresh()->getRawOriginal());
            $this->assertSame($new, $row->fresh()->path);
        }
        $this->assertTrue($base->exists($new));
        $this->assertIsolated();
    }

    public static function historyRaces(): array
    {
        return ['unavailable existence check' => [true], 'new path survives stale cleanup observation' => [false]];
    }

    private function event(string $command): \Illuminate\Console\Scheduling\Event
    {
        app(Kernel::class)->all();
        $events = array_values(array_filter(app(Schedule::class)->events(), static fn ($event): bool => preg_match('/(?:^|\\s)'.preg_quote($command, '/').'(?:\\s|$)/', $event->command ?? '') === 1));
        $this->assertCount(1, $events, 'Each maintenance command must be registered exactly once: '.$command);

        return $events[0];
    }

    private function backupFixture(): array
    {
        $this->travelTo(Carbon::parse('2031-05-01 12:00:00', 'UTC'));
        config(['backup.backup.name' => 'ops002fixture', 'backup.backup.destination.disks' => ['local'],
            'backup.cleanup.default_strategy.keep_all_backups_for_days' => 1,
            'backup.cleanup.default_strategy.keep_daily_backups_for_days' => 0,
            'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => 0,
            'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => 0,
            'backup.cleanup.default_strategy.keep_yearly_backups_for_years' => 0,
            'backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than' => null,
            'backup.monitor_backups.0.name' => 'ops002fixture', 'backup.monitor_backups.0.disks' => ['local'],
            'backup.notifications.mail.to' => 'ops002@example.test']);
        $this->app->forgetInstance(Config::class);
        $old = 'ops002fixture/2020-01-01-00-00-00.zip';
        $new = 'ops002fixture/'.now()->format('Y-m-d-H-i-s').'.zip';
        foreach ([$old, $new] as $path) {
            Storage::disk('local')->put($path, 'OPS002 synthetic backup fixture');
        }

        return [$old, $new];
    }

    private function history(?string $disk, ?string $path, string $status = 'completed'): BackupHistory
    {
        return BackupHistory::query()->create(['disk' => $disk, 'path' => $path, 'file_name' => $path === null ? null : basename($path),
            'file_size' => 29, 'status' => $status, 'error_message' => $status === 'failed' ? 'OPS002 failure before destination' : null, 'backed_up_at' => now()]);
    }

    private function assertIsolated(): void
    {
        Mail::assertNothingOutgoing();
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
