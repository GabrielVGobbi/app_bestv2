<?php

namespace Tests\Feature\Operations;

use App\Models\RichText;
use App\Models\User;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RichTextTempPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        Carbon::setTestNow(Carbon::parse('2030-02-15 12:00:00', date_default_timezone_get()));
        Storage::fake('public');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        try {
            Carbon::setTestNow();
        } finally {
            parent::tearDown();
        }
    }

    public function test_only_old_top_level_temporary_orphans_are_removed(): void
    {
        $old = $this->file('temp/old.png', 86401);
        $recent = $this->file('temp/recent.png', 86399);
        $boundary = $this->file('temp/boundary.png', 86400);
        $permanent = $this->file('permanent.png', 172800);
        $nested = $this->file('temp/nested/keep.png', 172800);
        $before = $this->snapshots([$recent, $boundary, $permanent, $nested]);

        $this->artisan('rich-text:prune-uploads')->expectsOutputToContain('Removidos: 1')->assertSuccessful();

        $this->assertFalse(Storage::disk('public')->exists($old));
        $this->assertSame($before, $this->snapshots(array_keys($before)));
        $this->assertIsolated();
    }

    public function test_dry_run_keeps_all_bytes_and_timestamps(): void
    {
        $old = $this->file('temp/old.png', 172800);
        $recent = $this->file('temp/recent.png', 1);
        $before = $this->snapshots([$old, $recent]);

        $this->artisan('rich-text:prune-uploads', ['--dry-run' => true])->expectsOutputToContain('[dry-run] removeria: 1')->assertSuccessful();

        $this->assertSame($before, $this->snapshots(array_keys($before)));
        $this->assertIsolated();
    }

    #[DataProvider('invalidHours')]
    public function test_invalid_hours_fail_before_resolving_any_storage(string $hours): void
    {
        Storage::shouldReceive('disk')->never();

        $this->artisan('rich-text:prune-uploads', ['--hours' => $hours])->assertFailed();

        $this->assertIsolated();
    }

    /** @return array<string, array{string}> */
    public static function invalidHours(): array
    {
        return ['zero' => ['0'], 'negative' => ['-1'], 'fraction' => ['1.5'], 'text' => ['invalid'], 'empty' => [''], 'exponent' => ['1e2']];
    }

    #[DataProvider('references')]
    public function test_persisted_html_or_json_references_preserve_old_temporary_files(string $column, bool $json, bool $escapedSlashes): void
    {
        $referenced = $this->file('temp/referenced.png', 172800);
        $orphan = $this->file('temp/orphan.png', 172800);
        $url = Storage::disk('public')->url($referenced);
        $content = $json
            ? json_encode(['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => $url]]]], $escapedSlashes ? JSON_THROW_ON_ERROR : JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : '<p>OPS002 saved content</p><img src="'.$url.'">';
        $richText = RichText::query()->create([
            'richable_type' => User::class, 'richable_id' => User::factory()->create()->id, 'field' => 'body',
            'format' => $json ? 'tiptap_json' : 'html', 'body' => 'OPS002 unrelated body', 'body_html' => null,
            $column => $content,
        ]);
        $raw = $richText->fresh()->getRawOriginal();
        $before = $this->snapshots([$referenced]);

        $this->artisan('rich-text:prune-uploads')->expectsOutputToContain('Removidos: 1')->assertSuccessful();

        $this->assertSame($before, $this->snapshots([$referenced]));
        $this->assertFalse(Storage::disk('public')->exists($orphan));
        $this->assertSame($raw, $richText->fresh()->getRawOriginal());
        $this->assertIsolated();
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function references(): array
    {
        return [
            'HTML body' => ['body', false, false], 'HTML cache' => ['body_html', false, false],
            'escaped JSON body' => ['body', true, true], 'JSON cache' => ['body_html', true, false],
        ];
    }

    #[DataProvider('deleteFailures')]
    public function test_failed_deletions_keep_bytes_report_failure_and_count_only_successes(string $failure): void
    {
        $failed = $this->file('temp/a-failed.png', 172800);
        $removable = $this->file('temp/z-removable.png', 172800);
        $before = $this->snapshots([$failed]);
        $observer = $this->observe();
        $observer->failurePath = $failed;
        $observer->deleteFailure = $failure;

        $this->artisan('rich-text:prune-uploads')->expectsOutputToContain('Removidos: 1')->assertFailed();

        $this->assertSame($before, $this->snapshots([$failed]));
        $this->assertFalse(Storage::disk('public')->exists($removable));
        $this->assertContains($failed, $observer->deletions);
        $this->assertContains($removable, $observer->deletions);
        $this->assertIsolated();
    }

    /** @return array<string, array{string}> */
    public static function deleteFailures(): array
    {
        return ['false' => ['false'], 'exception' => ['throw']];
    }

    public function test_a_file_refreshed_during_metadata_read_is_preserved_before_delete(): void
    {
        $path = $this->file('temp/refreshed.png', 172800);
        $absolute = Storage::disk('public')->path($path);
        $observer = $this->observe();
        $observer->afterLastModified = function (string $candidate) use ($path, $absolute): void {
            if ($candidate === $path) {
                $this->assertSame(22, file_put_contents($absolute, 'OPS002 active revision'));
                $this->assertTrue(touch($absolute, now()->timestamp));
            }
        };

        $this->artisan('rich-text:prune-uploads')->assertSuccessful();

        $this->assertSame('OPS002 active revision', Storage::disk('public')->get($path));
        $this->assertNotContains($path, $observer->deletions);
        $this->assertIsolated();
    }

    public function test_a_hardlinked_temporary_file_is_refused_and_its_owned_sentinel_survives(): void
    {
        $path = $this->file('temp/linked.png', 172800);
        $absolute = Storage::disk('public')->path($path);
        $sentinel = $this->testEnvironment->path.'/ops002-linked-sentinel';
        $this->assertTrue(link($absolute, $sentinel));
        $before = $this->snapshots([$path]);
        try {
            $this->artisan('rich-text:prune-uploads')->assertFailed();

            $this->assertSame($before, $this->snapshots([$path]));
            $this->assertSame('OPS002 fixture temp/linked.png', file_get_contents($sentinel));
        } finally {
            if (is_file($sentinel)) {
                unlink($sentinel);
            }
        }
        $this->assertIsolated();
    }

    #[DataProvider('unsafeDisks')]
    public function test_nonlocal_configuration_or_adapter_is_refused_before_listing(bool $remoteAdapter): void
    {
        $path = $this->file('temp/keep.png', 172800);
        $base = Storage::disk('public');
        $before = $this->snapshots([$path]);
        $observer = new RichTextPruneDisk($base->getDriver(), $remoteAdapter ? Mockery::mock(FlysystemAdapter::class) : $base->getAdapter(),
            array_replace($base->getConfig(), ['driver' => $remoteAdapter ? 'local' : 's3']));
        Storage::set('public', $observer);

        $this->artisan('rich-text:prune-uploads')->assertFailed();

        $this->assertSame(0, $observer->listings);
        $this->assertSame([], $observer->deletions);
        Storage::set('public', $base);
        $this->assertSame($before, $this->snapshots([$path]));
        $this->assertIsolated();
    }

    /** @return array<string, array{bool}> */
    public static function unsafeDisks(): array
    {
        return ['remote configured driver' => [false], 'nonlocal adapter' => [true]];
    }

    private function file(string $suffix, int $ageSeconds): string
    {
        $path = 'uploads/rich-text/'.$suffix;
        $this->assertTrue(Storage::disk('public')->put($path, 'OPS002 fixture '.$suffix));
        $this->assertTrue(touch(Storage::disk('public')->path($path), now()->subSeconds($ageSeconds)->timestamp));

        return $path;
    }

    /** @param list<string> $paths
     * @return array<string, array{string, int}>
     */
    private function snapshots(array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            $absolute = Storage::disk('public')->path($path);
            clearstatcache(true, $absolute);
            $this->assertFileExists($absolute);
            $result[$path] = [hash_file('sha256', $absolute), filemtime($absolute)];
        }

        return $result;
    }

    private function observe(): RichTextPruneDisk
    {
        $base = Storage::disk('public');
        $observer = new RichTextPruneDisk($base->getDriver(), $base->getAdapter(), $base->getConfig());
        Storage::set('public', $observer);

        return $observer;
    }

    private function assertIsolated(): void
    {
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}

class RichTextPruneDisk extends FilesystemAdapter
{
    public ?string $failurePath = null;

    public string $deleteFailure = '';

    public int $listings = 0;

    /** @var list<string> */
    public array $deletions = [];

    public ?Closure $afterLastModified = null;

    public function files($directory = null, $recursive = false): array
    {
        $this->listings++;

        return parent::files($directory, $recursive);
    }

    public function lastModified($path): int
    {
        $modified = parent::lastModified($path);
        $callback = $this->afterLastModified;
        $this->afterLastModified = null;
        if ($callback !== null) {
            $callback($path);
        }

        return $modified;
    }

    public function delete($paths): bool
    {
        foreach ((array) $paths as $path) {
            $this->deletions[] = $path;
            if ($path === $this->failurePath) {
                if ($this->deleteFailure === 'throw') {
                    throw new RuntimeException('OPS002 synthetic deletion failure.');
                }

                return false;
            }
        }

        return parent::delete($paths);
    }
}
