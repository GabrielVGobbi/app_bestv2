<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Closure;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Filesystem\ReceiveFile;
use Illuminate\Filesystem\ServeFile;
use Illuminate\Foundation\Exceptions\Renderer\Renderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystem;
use ReflectionClass;
use Tests\TestCase;

class FrameworkSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function emailControlCharacters(): array
    {
        $cases = [];

        foreach (['email', 'email:rfc'] as $rule) {
            foreach (['CRLF' => "\r\n", 'CR' => "\r", 'LF' => "\n"] as $name => $separator) {
                $cases[$rule.' '.$name] = ['"fixture'.$separator.'Bcc: other@example.test"@example.test', $rule];
            }
        }

        return $cases;
    }

    #[DataProvider('emailControlCharacters')]
    public function test_email_rules_reject_internal_control_characters_and_recover(string $email, string $rule): void
    {
        $invalid = Validator::make(['email' => $email], ['email' => ['required', $rule]]);
        $this->assertTrue($invalid->fails());
        $this->assertTrue($invalid->errors()->has('email'));

        $valid = Validator::make(['email' => 'fixture+recovery@example.test'], ['email' => ['required', $rule]]);
        $this->assertTrue($valid->passes());
        $this->assertSame(['email' => 'fixture+recovery@example.test'], $valid->validated());
    }

    /** @return array<string, array{mixed, bool}> */
    public static function ordinaryEmailInputs(): array
    {
        return [
            'ASCII' => ['fixture@example.test', true],
            'plus tag' => ['fixture+tag@example.test', true],
            'quoted local part' => ['"fixture name"@example.test', true],
            'Unicode domain' => ['fixture@bücher.example', true],
            'malformed' => ['not-an-address', false],
            'array' => [['fixture@example.test'], false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('ordinaryEmailInputs')]
    public function test_default_email_rule_preserves_legitimate_inputs_and_rejects_invalid_types(mixed $email, bool $accepted): void
    {
        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
        $this->assertSame($accepted, $validator->passes());
        $this->assertSame(! $accepted, $validator->errors()->has('email'));
    }

    public function test_registration_rejects_crlf_without_creating_or_authenticating_a_user_then_accepts_valid_input(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());
        Mail::fake();
        Notification::fake();
        $before = User::count();
        $payload = [
            'name' => 'Framework Fixture',
            'email' => "\"fixture\r\nBcc: other@example.test\"@example.test",
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->postJson(route('register.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($before, User::count());
        $this->assertGuest();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();

        $payload['email'] = 'framework-registration@example.test';
        $this->post(route('register.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('app.dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertSame($before + 1, User::count());
        $this->assertSame($payload['email'], auth()->user()->email);
    }

    public function test_profile_rejects_crlf_without_mutation_or_delivery_then_accepts_a_valid_update(): void
    {
        $user = User::factory()->create(['email' => 'framework-original@example.test']);
        Mail::fake();
        Notification::fake();
        $before = $user->fresh()->getAttributes();

        $this->actingAs($user)->patchJson(route('profile.update'), [
            'name' => 'Framework Fixture',
            'email' => "\"fixture\r\nBcc: other@example.test\"@example.test",
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();

        $this->patch(route('profile.update'), [
            'name' => 'Framework Updated',
            'email' => 'framework-updated@example.test',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
        $this->assertSame('framework-updated@example.test', $user->fresh()->email);
        $this->assertSame('Framework Updated', $user->fresh()->name);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    /** @return array<string, array{string, string}> */
    public static function portableTemporaryPaths(): array
    {
        $cases = [];

        foreach (['plain' => 'fixture.txt', 'nested' => 'nested/folder/fixture.txt', 'fragment' => 'fixture#fragment.txt'] as $name => $path) {
            foreach (['download', 'upload'] as $operation) {
                $cases[$name.' '.$operation] = [$path, $operation];
            }
        }

        return $cases;
    }

    #[DataProvider('portableTemporaryPaths')]
    public function test_local_temporary_urls_reach_exact_real_files_and_preserve_nested_paths(string $path, string $operation): void
    {
        $this->withFrozenClock(function () use ($path, $operation): void {
            $disk = $this->localTemporaryDisk();
            $this->assertTrue($disk->put('untouched.txt', 'UNCHANGED'));
            $this->assertTrue($disk->put($path, 'EXACT ORIGINAL'));
            $url = $this->temporaryUrl($disk, $operation, $path, now()->addMinute());
            $response = $this->performTemporaryRequest($operation, $url, 'EXACT UPLOAD');

            if ($operation === 'download') {
                $response->assertOk();
                $this->assertSame('EXACT ORIGINAL', $response->streamedContent());
                $this->assertSame('EXACT ORIGINAL', $disk->get($path));
            } else {
                $response->assertNoContent();
                $this->assertSame('EXACT UPLOAD', $disk->get($path));
            }

            $this->assertSame('UNCHANGED', $disk->get('untouched.txt'));
            $this->assertSame('/_framework-security/storage/'.$path, rawurldecode(parse_url($url, PHP_URL_PATH)));
            $this->assertNull(parse_url($url, PHP_URL_FRAGMENT));
            $query = [];
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
            $this->assertSame((string) now()->addMinute()->timestamp, $query['expires'] ?? null);
        });
    }

    /** @return array<string, array{string, string}> */
    public static function invalidTemporaryRequests(): array
    {
        $cases = [];

        foreach (['expired', 'tampered', 'wrong operation'] as $kind) {
            foreach (['download', 'upload'] as $operation) {
                $cases[$kind.' '.$operation] = [$kind, $operation];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidTemporaryRequests')]
    public function test_local_temporary_urls_refuse_invalid_requests_without_mutation_and_recover(string $kind, string $operation): void
    {
        $this->withFrozenClock(function () use ($kind, $operation): void {
            $disk = $this->localTemporaryDisk();
            $this->assertTrue($disk->put('fixture.txt', 'ORIGINAL'));
            $this->assertTrue($disk->put('untouched.txt', 'UNCHANGED'));
            $before = $this->diskSnapshot($disk);
            $url = $this->temporaryUrl($disk, $operation, 'fixture.txt', $kind === 'expired' ? now()->subMinute() : now()->addMinute());
            $requestOperation = $operation;

            if ($kind === 'tampered') {
                $url .= 'c';
            } elseif ($kind === 'wrong operation') {
                $requestOperation = $operation === 'download' ? 'upload' : 'download';
            }

            $this->performTemporaryRequest($requestOperation, $url, 'REJECTED WRITE')->assertForbidden();
            $this->assertSame($before, $this->diskSnapshot($disk));
            $recovery = $this->performTemporaryRequest('download', $this->temporaryUrl($disk, 'download', 'fixture.txt', now()->addMinute()));
            $recovery->assertOk();
            $this->assertSame('ORIGINAL', $recovery->streamedContent());
            $this->assertSame($before, $this->diskSnapshot($disk));
        });
    }

    public function test_local_temporary_download_returns_not_found_without_changing_existing_files(): void
    {
        $this->withFrozenClock(function (): void {
            $disk = $this->localTemporaryDisk();
            $this->assertTrue($disk->put('existing.txt', 'UNCHANGED'));
            $before = $this->diskSnapshot($disk);
            $url = $this->temporaryUrl($disk, 'download', 'missing.txt', now()->addMinute());
            $this->performTemporaryRequest('download', $url)->assertNotFound();
            $this->assertSame($before, $this->diskSnapshot($disk));
        });
    }

    /** @return array<string, array{string}> */
    public static function temporaryUrlOperations(): array
    {
        return ['download' => ['download'], 'upload' => ['upload']];
    }

    #[RequiresOperatingSystem('Linux|Darwin')]
    #[DataProvider('temporaryUrlOperations')]
    public function test_local_temporary_urls_preserve_query_delimiters_and_enforce_expiration_on_real_filesystem(string $operation): void
    {
        $this->withFrozenClock(function () use ($operation): void {
            $disk = $this->localTemporaryDisk();
            $png = file_get_contents(__DIR__.'/../Fixtures/upload-preview.png');
            $this->assertIsString($png);
            $initialBytes = $png."\nDEP001 INITIAL\n";
            $uploadedBytes = $png."\nDEP001 UPLOADED\n";
            $path = 'fixture.txt?pad=x';
            $this->assertTrue($disk->put('fixture.txt', 'ALTERNATE UNCHANGED'));
            $this->assertTrue($disk->put($path, $initialBytes));
            $this->assertSame('image/png', $disk->mimeType($path));
            $url = $this->temporaryUrl($disk, $operation, $path, now()->addMinute());
            $response = $this->performTemporaryRequest($operation, $url, $uploadedBytes);

            if ($operation === 'download') {
                $response->assertOk();
                $this->assertSame($initialBytes, $response->streamedContent());
                $this->assertSame($initialBytes, $disk->get($path));
            } else {
                $response->assertNoContent();
                $this->assertSame($uploadedBytes, $disk->get($path));
                $this->assertSame('image/png', $disk->mimeType($path));
            }

            $this->assertSame('ALTERNATE UNCHANGED', $disk->get('fixture.txt'));
            $before = $this->diskSnapshot($disk);
            $expired = $this->temporaryUrl($disk, $operation, $path, now()->subMinute());
            $this->performTemporaryRequest($operation, $expired, 'REJECTED QUESTION WRITE')->assertForbidden();
            $this->assertSame($before, $this->diskSnapshot($disk));
            $recovered = $this->performTemporaryRequest('download', $this->temporaryUrl($disk, 'download', $path, now()->addMinute()));
            $recovered->assertOk();
            $this->assertSame($operation === 'download' ? $initialBytes : $uploadedBytes, $recovered->streamedContent());
            $this->assertSame($before, $this->diskSnapshot($disk));
        });
    }

    public function test_renderer_request_header_preserves_literal_adversarial_and_utf8_data_without_active_nodes(): void
    {
        $this->prepareRendererComponents();
        $payload = '<img src="data:image/png;base64,AA==" onerror="window.__depFrameworkHeaderExecuted=1"> ç "fixture"';
        $html = Blade::render('<x-laravel-exceptions-renderer::request-header :headers="$headers" />', ['headers' => ['X-DEP-Fixture' => $payload]]);
        $dom = $this->parseRenderedHtml($html);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//span[@data-tippy-content]');
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length);
        $node = $nodes->item(0);
        $this->assertInstanceOf(DOMElement::class, $node);
        $this->assertSame($payload, $node->getAttribute('data-tippy-content'));
        $this->assertSame($payload, trim($node->textContent));
        $this->assertSame(0, $xpath->query('//img|//script|//svg|//*[@onerror]')->length);
        $this->assertFalse(config('app.debug'));
    }

    /** @return array<string, array{string}> */
    public static function rendererSources(): array
    {
        return [
            'legitimate multiline source' => ["Fixture::call(1)\nNext line ç"],
            'adversarial literal source' => ['Fixture("<img src=\"data:image/png;base64,AA==\" onerror=\"window.__depFrameworkSourceExecuted=1\">")'],
        ];
    }

    #[DataProvider('rendererSources')]
    public function test_renderer_formatted_source_uses_its_explicit_channel_and_escaped_fallback(string $source): void
    {
        $root = $this->prepareRendererComponents();
        $frame = new class($source)
        {
            public function __construct(private string $source) {}

            public function class(): null
            {
                return null;
            }

            public function operator(): string
            {
                return '';
            }

            public function callable(): string
            {
                return 'throw';
            }

            public function source(): string
            {
                return $this->source;
            }
        };
        $html = Blade::render('<x-laravel-exceptions-renderer::formatted-source :frame="$frame" />', ['frame' => $frame]);
        $dom = $this->parseRenderedHtml($html);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@data-tippy-html-content]');
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length);
        $node = $nodes->item(0);
        $this->assertInstanceOf(DOMElement::class, $node);
        $this->assertSame($source, $node->getAttribute('data-tippy-html-content'));
        $this->assertFalse($node->hasAttribute('data-tippy-content'));
        $this->assertStringContainsString($source, $node->textContent);
        $this->assertSame(0, $xpath->query('//img|//script|//svg|//*[@onerror]')->length);
        $this->assertFalse(config('app.debug'));

        $finder = $this->app['view']->getFinder();
        $finder->replaceNamespace('laravel-exceptions-renderer', []);
        $finder->flush();
        $provider = new class($this->app) extends ServiceProvider
        {
            public function registerRendererViews(string $path): void
            {
                $this->loadViewsFrom($path, 'laravel-exceptions-renderer');
            }
        };
        $provider->registerRendererViews($root);
        $view = $this->app['view']->make('laravel-exceptions-renderer::components.formatted-source', ['frame' => $frame]);
        $this->assertInstanceOf(View::class, $view);
        $overrideDom = $this->parseRenderedHtml($view->render());
        $overrideXpath = new DOMXPath($overrideDom);
        $textNodes = $overrideXpath->query('//*[@data-tippy-content]');
        $this->assertNotFalse($textNodes);
        $this->assertSame(1, $textNodes->length);
        $textNode = $textNodes->item(0);
        $this->assertInstanceOf(DOMElement::class, $textNode);
        $this->assertSame($source, $textNode->getAttribute('data-tippy-content'));
        $this->assertSame(0, $overrideXpath->query('//*[@data-tippy-html-content]')->length);
        $fallback = $overrideXpath->query('//pre/code');
        $this->assertNotFalse($fallback);
        $this->assertSame(1, $fallback->length);
        $this->assertSame($source, $fallback->item(0)->textContent);
        $this->assertSame(0, $overrideXpath->query('//img|//script|//svg|//*[@onerror]')->length);
        $expectedView = realpath(resource_path('views/vendor/laravel-exceptions-renderer/components/formatted-source.blade.php'));
        $this->assertIsString($expectedView);
        $this->assertSame($expectedView, realpath($view->getPath()));
        $this->assertFalse(config('app.debug'));
    }

    private function localTemporaryDisk(): LocalFilesystemAdapter
    {
        $this->withoutVite();
        $name = 'framework-security';
        $owned = realpath($this->testEnvironment->path);
        $this->assertIsString($owned);
        $root = $owned.'/storage/disks/'.$name;
        File::ensureDirectoryExists($root);
        $resolved = realpath($root);
        $this->assertIsString($resolved);
        $this->assertStringStartsWith(str_replace('\\', '/', $owned).'/', str_replace('\\', '/', $resolved));
        $config = ['driver' => 'local', 'root' => $root, 'serve' => true, 'visibility' => 'private', 'throw' => true];
        config(['filesystems.disks.'.$name => $config]);
        Storage::forgetDisk($name);
        $disk = Storage::disk($name);
        $this->assertInstanceOf(LocalFilesystemAdapter::class, $disk);
        $this->assertTrue($disk->providesTemporaryUrls());

        Route::get('/_framework-security/storage/{path}', function (Request $request, string $path) use ($name, $config) {
            return (new ServeFile($name, $config, false))($request, $path);
        })->where('path', '.*')->name('storage.'.$name);
        Route::put('/_framework-security/storage/{path}', function (Request $request, string $path) use ($name, $config) {
            return (new ReceiveFile($name, $config, false))($request, $path);
        })->where('path', '.*')->name('storage.'.$name.'.upload');
        $this->app['router']->getRoutes()->refreshNameLookups();

        return $disk;
    }

    private function temporaryUrl(LocalFilesystemAdapter $disk, string $operation, string $path, DateTimeInterface $expiration): string
    {
        return $operation === 'download'
            ? $disk->temporaryUrl($path, $expiration)
            : $disk->temporaryUploadUrl($path, $expiration)['url'];
    }

    private function performTemporaryRequest(string $operation, string $url, string $content = ''): TestResponse
    {
        return $operation === 'download'
            ? $this->get($url)
            : $this->call('PUT', $url, [], [], [], [], $content);
    }

    /** @return array<string, string> */
    private function diskSnapshot(LocalFilesystemAdapter $disk): array
    {
        $snapshot = [];

        foreach ($disk->allFiles() as $path) {
            $contents = $disk->get($path);
            $this->assertIsString($contents);
            $snapshot[$path] = $contents;
        }

        ksort($snapshot, SORT_STRING);

        return $snapshot;
    }

    private function withFrozenClock(Closure $callback): void
    {
        $originalClock = Carbon::getTestNow();

        try {
            Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'UTC'));
            $callback();
        } finally {
            Carbon::setTestNow($originalClock);
        }
    }

    private function prepareRendererComponents(): string
    {
        $file = (new ReflectionClass(Renderer::class))->getFileName();
        $this->assertIsString($file);
        $root = dirname($file, 3).'/resources/exceptions/renderer';
        $this->app['view']->addNamespace('laravel-exceptions-renderer', $root);
        Blade::anonymousComponentPath($root.'/components', 'laravel-exceptions-renderer');
        $this->app->make(Vite::class)->useHotFile($this->testEnvironment->path.'/missing-framework-renderer.hot');
        $this->assertStringStartsWith($this->testEnvironment->path.'/', config('view.compiled'));
        $this->assertFalse(config('app.debug'));

        return $root;
    }

    private function parseRenderedHtml(string $html): DOMDocument
    {
        $originalErrors = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument;
            $this->assertTrue($dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET));

            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($originalErrors);
        }
    }
}
