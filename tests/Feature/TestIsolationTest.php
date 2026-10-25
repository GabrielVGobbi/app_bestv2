<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Mail\DynamicMailable;
use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use App\Services\DevToolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use Tests\TestEnvironment;

class TestIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_and_factories_use_only_an_in_memory_database(): void
    {
        User::factory()->create();
        $databases = DB::select('PRAGMA database_list');

        $this->assertSame('', $databases[0]->file);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame(['sqlite'], array_keys(config('database.connections')));
    }

    public function test_runtime_paths_and_all_disks_are_disposable(): void
    {
        foreach ([storage_path(), app()->getCachedConfigPath(), app()->getCachedRoutesPath(), app()->getCachedServicesPath(), app()->getCachedPackagesPath(), app()->getCachedEventsPath(), config('view.compiled'), config('debugbar.storage.path'), app()->environmentFilePath()] as $path) {
            $this->assertStringStartsWith($this->testEnvironment->path.'/', $path);
        }

        foreach (array_keys(config('filesystems.disks')) as $disk) {
            Storage::disk($disk)->put('probe.txt', 'fixture');
            $this->assertStringStartsWith($this->testEnvironment->path.'/', Storage::disk($disk)->path('probe.txt'));
        }

        foreach (array_keys(config('cache.stores')) as $store) {
            Cache::store($store)->put('probe', 'fixture');
            $this->assertSame('fixture', Cache::store($store)->get('probe'));
            Cache::store($store)->flush();
        }
    }

    public function test_unfaked_http_is_refused_before_network_io(): void
    {
        $this->expectException(StrayRequestException::class);
        Http::get('https://example.invalid/prep-001');
    }

    public function test_explicit_provider_mailers_cannot_construct_remote_transports(): void
    {
        $manager = app('mail.manager');

        foreach (array_keys(config('mail.mailers')) as $mailer) {
            $transport = $manager->mailer($mailer)->getSymfonyTransport();
            $this->assertInstanceOf(ArrayTransport::class, $transport);
        }
    }

    public function test_email_dispatch_is_captured_and_direct_execution_uses_fake_mail(): void
    {
        $log = app(EmailServiceInterface::class)->queue(new SendEmailDTO(
            to: 'fixture@example.test',
            subject: 'Isolation probe',
            template: 'emails.exports.ready',
            category: EmailCategory::Export,
            variables: ['downloadUrl' => 'http://localhost/fixture-download', 'expiresAt' => '2026-10-19'],
        ));

        $this->assertSame('queued', $log->fresh()->status->value);
        Queue::assertPushed(ProcessEmailJob::class);
        Mail::assertNothingSent();

        (new ProcessEmailJob($log))->handle(app(EmailProviderMessageIdResolver::class));

        $this->assertSame('sent', $log->fresh()->status->value);
        Mail::assertSent(DynamicMailable::class);
    }

    public function test_devtools_refuses_a_missing_environment_file_in_the_fixture(): void
    {
        $this->isolateProjectFiles();
        $this->assertFileDoesNotExist(base_path('.env'));
        $this->expectException(RuntimeException::class);

        app(DevToolsService::class)->updateEnvVariable('APP_NAME', 'fixture');
    }

    public function test_disposable_cleanup_removes_generated_files_and_preserves_siblings(): void
    {
        $sandbox = new TestEnvironment;
        $sibling = new TestEnvironment;

        try {
            File::put($sandbox->path.'/project/.env', 'fixture');
            File::put($sandbox->path.'/project/.config', 'fixture');
            File::put($sibling->path.'/project/.env', 'preserved');
            $sandbox->cleanUp();

            $this->assertDirectoryDoesNotExist($sandbox->path);
            $this->assertSame('preserved', File::get($sibling->path.'/project/.env'));
        } finally {
            $sandbox->cleanUp();
            $sibling->cleanUp();
        }
    }
}
