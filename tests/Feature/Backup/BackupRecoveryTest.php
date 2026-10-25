<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\User;
use App\Modules\Backup\Models\BackupHistory;
use App\Modules\Permissions\Models\Role;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Backup\Config\Config;
use Tests\TestCase;
use Tests\TestEnvironment;
use ZipArchive;

class BackupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $policy;

    /** @var array<string, string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $backupConfiguration = base_path('config/backup.php');
        $filesystemConfiguration = base_path('config/filesystems.php');
        $this->isolateProjectFiles();
        $this->app->useStoragePath(base_path('storage'));
        $this->app->useBootstrapPath(base_path('bootstrap'));
        $this->policy = require $backupConfiguration;
        config(['filesystems' => require $filesystemConfiguration]);
        Storage::forgetDisk('local');
        (new FilesystemServiceProvider($this->app))->boot();
        Notification::fake();
    }

    public function test_actual_sdk_archive_excludes_credentials_and_runtime_but_preserves_recovery_files(): void
    {
        $excluded = [
            '.env', '.env.production', '.env.example', '.config', '.config.bak', 'auth.json', '.npmrc',
            '.aws/credentials', '.ssh/id_ed25519', '.git/config', 'vendor/autoload.php', 'node_modules/private.js',
            'bootstrap/cache/config.php', 'bootstrap/cache/services.php', 'storage/framework/sessions/session',
            'storage/logs/laravel.log', 'storage/debugbar/request.json',
            'database/database.sqlite', 'database/database.sqlite-wal', 'database/database.sqlite-shm',
        ];
        $included = [
            'app/RecoveryExample.php' => '<?php /* OPS003 source fixture */',
            'config/database.php' => '<?php /* env-based configuration source */',
            'resources/recovery.txt' => 'OPS003 resource',
            'storage/app/private/uploads/recovery.bin' => "\x00OPS003_PRIVATE\xff\x01",
            'storage/app/public/logo.txt' => 'OPS003 public asset',
            'database/migrations/recovery.php' => '<?php /* migration source */',
            '.configurable' => 'OPS003 legitimate neighbor',
        ];
        foreach ($excluded as $path) {
            $this->putFixture($path, 'OPS003_SECRET_'.hash('sha256', $path));
        }
        foreach ($included as $path => $contents) {
            $this->putFixture($path, $contents);
        }
        [$archive, $names] = $this->archive();
        foreach ($excluded as $path) {
            $this->assertFalse($archive->locateName($path), $path);
            $this->assertNotContains($path, $names);
            foreach ($names as $name) {
                $contents = $this->archiveContents($archive, $name);
                if (is_string($contents)) {
                    $this->assertStringNotContainsString('OPS003_SECRET_'.hash('sha256', $path), $contents, $path);
                }
            }
        }
        foreach ($included as $path => $contents) {
            $this->assertContains($path, $names);
            $this->assertSame($contents, $this->archiveContents($archive, $path));
        }
        $this->assertTrue($archive->close());
        foreach ($this->files as $path => $contents) {
            $this->assertSame($contents, file_get_contents(base_path($path)), $path);
        }
        $this->assertIsolated();
    }

    public function test_missing_excluded_paths_do_not_hide_source_configuration_or_neighbor_files(): void
    {
        foreach (['config/app.php', 'bootstrap/app.php', 'database/migrations/fixture.php', 'storage/app/private/fixture.bin', '.configurable'] as $path) {
            $this->putFixture($path, 'OPS003_KEEP_'.hash('sha256', $path));
        }
        [$archive, $names] = $this->archive();
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('~^/|^[a-zA-Z]:|(?:^|/)\.\.(?:/|$)~', $name);
        }
        foreach ($this->files as $path => $contents) {
            $this->assertContains($path, $names);
            $this->assertSame($contents, $this->archiveContents($archive, $path));
        }
        $this->assertTrue($archive->close());
        $this->assertIsolated();
    }

    #[DataProvider('unauthorizedActors')]
    public function test_backup_download_rejects_unauthorized_actors_without_a_download_audit(?string $role): void
    {
        $this->putFixture('storage/app/private/recovery.bin', 'OPS003 protected bytes');
        [$archive] = $this->archive();
        $this->assertTrue($archive->close());
        $history = BackupHistory::query()->sole();
        $before = hash_file('sha256', Storage::disk('local')->path($history->path));
        if ($role !== null) {
            $this->actingAs($this->actor($role));
        }
        $response = $this->get(route('admin.dev.backups.download', $history->uuid));
        if ($role === null) {
            $response->assertRedirect(route('login'));
        } else {
            $response->assertForbidden();
        }
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'backups', 'description' => 'downloaded']);
        $this->assertSame($before, hash_file('sha256', Storage::disk('local')->path($history->path)));
        $this->assertIsolated();
    }

    public static function unauthorizedActors(): array
    {
        return [[null], ['user'], ['admin'], ['super-admin']];
    }

    public function test_developer_downloads_exact_private_archive_and_records_the_audit(): void
    {
        $this->putFixture('storage/app/private/recovery.bin', "\x00OPS003_PRIVATE\xff");
        [$archive] = $this->archive();
        $this->assertTrue($archive->close());
        $history = BackupHistory::query()->sole();
        $path = Storage::disk('local')->path($history->path);
        $expected = hash_file('sha256', $path);
        $actor = $this->actor('developer');
        $response = $this->actingAs($actor)->get(route('admin.dev.backups.download', $history->uuid));
        $response->assertOk()->assertDownload($history->file_name);
        $this->assertSame($expected, hash('sha256', $response->streamedContent()));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'backups', 'description' => 'downloaded', 'causer_id' => $actor->id, 'subject_id' => $history->id]);
        $this->assertStringStartsWith(storage_path('app/private'), $path);
        $this->assertStringNotContainsString(public_path(), $path);
        $this->get('/storage/'.$history->path)->assertForbidden();
        $this->get('/storage/private/'.$history->path)->assertForbidden();
        $this->assertIsolated();
    }

    /** @return array{ZipArchive, list<string>} */
    private function archive(): array
    {
        $profile = $this->policy;
        $profile['backup']['name'] = 'ops003-fixture';
        $profile['backup']['source']['databases'] = [];
        $profile['backup']['password'] = null;
        $profile['log_channel'] = false;
        config(['backup' => $profile, 'ops003_backup' => $profile]);
        $this->app->forgetInstance(Config::class);
        $this->assertSame(0, Artisan::call('backup:run', ['--only-files' => true, '--config' => 'ops003_backup']), Artisan::output());
        $history = BackupHistory::query()->sole();
        $this->assertSame(BackupHistory::STATUS_COMPLETED, $history->status);
        $this->assertSame('local', $history->disk);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('local')->path($history->path), ZipArchive::RDONLY));
        $names = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            $this->assertIsString($name);
            $name = str_replace('\\', '/', $name);
            $this->assertNotContains(strtolower($name), array_map('strtolower', $names));
            $names[] = $name;
        }
        $this->assertNotEmpty($names);

        return [$archive, $names];
    }

    private function archiveContents(ZipArchive $archive, string $path): string|false
    {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            if (str_replace('\\', '/', (string) $archive->getNameIndex($index)) === $path) {
                return $archive->getFromIndex($index);
            }
        }

        return false;
    }

    private function putFixture(string $path, string $contents): void
    {
        (new Filesystem)->ensureDirectoryExists(dirname(base_path($path)));
        $this->assertSame(strlen($contents), file_put_contents(base_path($path), $contents));
        $this->files[$path] = $contents;
    }

    private function actor(string $role): User
    {
        $actor = User::factory()->create();
        $record = Role::firstOrCreate(['slug' => $role], ['name' => $role, 'slug' => $role]);
        $actor->roles()->attach($record);

        return $actor->load('roles');
    }

    private function assertIsolated(): void
    {
        TestEnvironment::assertSafeDatabase(config());
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}
