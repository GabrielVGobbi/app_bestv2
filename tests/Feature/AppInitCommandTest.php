<?php

namespace Tests\Feature;

use Closure;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Support\Facades\File;
use LogicException;
use Mockery\CompositeExpectation;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Throwable;

class AppInitCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
    }

    public function test_it_runs_without_overwriting_existing_private_files(): void
    {
        $environmentPath = base_path('.env');
        $configPath = base_path('.config');

        $originalEnvironment = 'APP_KEY=fixture-key';
        $originalConfig = 'private fixture';
        File::put($environmentPath, $originalEnvironment);
        File::put($configPath, $originalConfig);
        File::put(database_path('database.sqlite'), 'existing database fixture');

        $this->artisan('app:init', ['--skip-storage-link' => true])
            ->assertSuccessful();

        $this->assertFileExists($environmentPath);
        $this->assertFileExists($configPath);

        $this->assertSame($originalEnvironment, File::get($environmentPath));
        $this->assertSame($originalConfig, File::get($configPath));
        $this->assertSame('existing database fixture', File::get(database_path('database.sqlite')));
    }

    public function test_it_creates_missing_private_files_and_key_only_in_the_fixture(): void
    {
        config(['app.key' => '']);
        $this->assertFileDoesNotExist(base_path('.env'));
        $this->assertFileDoesNotExist(base_path('.config'));
        $this->assertFileDoesNotExist(database_path('database.sqlite'));

        $this->artisan('app:init', ['--skip-storage-link' => true])->assertSuccessful();

        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.+$/m', File::get(base_path('.env')));
        $this->assertStringContainsString('[servidores]', File::get(base_path('.config')));
        $this->assertSame('', File::get(database_path('database.sqlite')));
    }

    #[DataProvider('existingKeySyntax')]
    public function test_repeated_init_preserves_parsed_existing_key_and_encrypted_data(string $syntax): void
    {
        $key = $this->fixtureKey();
        $line = match ($syntax) {
            'raw' => 'APP_KEY='.$key,
            'quoted' => 'APP_KEY="'.$key.'"',
            'spaced' => 'APP_KEY = "'.$key.'" # retain this comment',
            'export' => "export APP_KEY = '{$key}' # retain export syntax",
            default => throw new LogicException('Unknown REL003 key syntax fixture.'),
        };
        $environment = "APP_NAME=Fixture\n{$line}\nKEEP_SETTING=before\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        File::put(database_path('database.sqlite'), 'REL003 existing database');
        config(['app.key' => $key, 'app.cipher' => 'AES-256-CBC']);
        $this->assertSame($key, Dotenv::parse($environment)['APP_KEY']);
        $encrypted = $this->encrypter($key)->encryptString('REL003 ciphertext fixture');
        $calls = 0;
        $this->replaceCommand('key:generate {--force} {--show}', function () use (&$calls): int {
            $calls++;

            return Command::FAILURE;
        });

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->artisan('app:init', ['--skip-storage-link' => true])->assertSuccessful();
            $this->assertSame($environment, File::get(base_path('.env')));
            $persistedKey = Dotenv::parse(File::get(base_path('.env')))['APP_KEY'];
            $this->assertSame($key, $persistedKey);
            $this->assertSame('REL003 ciphertext fixture', $this->encrypter($persistedKey)->decryptString($encrypted));
            $this->assertSame('REL003 private config', File::get(base_path('.config')));
            $this->assertSame('REL003 existing database', File::get(database_path('database.sqlite')));
        }

        $this->assertSame(0, $calls, 'Any parsed nonempty key must bypass key generation.');
    }

    /** @return array<string, array{string}> */
    public static function existingKeySyntax(): array
    {
        return ['plain' => ['raw'], 'double quoted' => ['quoted'], 'spaces and inline comment' => ['spaced'], 'export and single quotes' => ['export']];
    }

    #[DataProvider('externalKeyEnvironment')]
    public function test_existing_effective_key_is_preserved_when_environment_key_is_blank_or_missing(string $line): void
    {
        $key = $this->fixtureKey();
        $environment = "APP_NAME=Fixture\n{$line}\nKEEP_SETTING=before\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        File::put(database_path('database.sqlite'), 'REL003 existing database');
        config(['app.key' => $key, 'app.cipher' => 'AES-256-CBC']);
        $encrypted = $this->encrypter($key)->encryptString('REL003 effective key fixture');
        $calls = 0;
        $this->replaceCommand('key:generate {--force} {--show}', function () use (&$calls): int {
            $calls++;

            return Command::FAILURE;
        });

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->artisan('app:init', ['--skip-storage-link' => true])->assertSuccessful();
            $this->assertSame($environment, File::get(base_path('.env')));
            $this->assertSame($key, config('app.key'));
            $this->assertSame('REL003 effective key fixture', $this->encrypter(config('app.key'))->decryptString($encrypted));
            $this->assertSame('REL003 private config', File::get(base_path('.config')));
            $this->assertSame('REL003 existing database', File::get(database_path('database.sqlite')));
        }

        $this->assertSame(0, $calls, 'An effective configured key must bypass key generation.');
    }

    /** @return array<string, array{string}> */
    public static function externalKeyEnvironment(): array
    {
        return ['missing environment key' => [''], 'blank environment key' => ['APP_KEY=']];
    }

    public function test_invalid_effective_key_with_blank_environment_key_fails_without_rotation_or_other_commands(): void
    {
        $environment = "APP_NAME=Fixture\nAPP_KEY=\nKEEP_SETTING=before\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        config(['app.key' => 'REL003-invalid-effective-key', 'app.cipher' => 'AES-256-CBC']);
        $calls = 0;
        foreach (['key:generate {--force} {--show}', 'storage:link {--force}'] as $signature) {
            $this->replaceCommand($signature, function () use (&$calls): int {
                $calls++;

                return Command::SUCCESS;
            });
        }

        $this->assertInitFails();

        $this->assertSame(0, $calls);
        $this->assertSame('REL003-invalid-effective-key', config('app.key'));
        $this->assertSame($environment, File::get(base_path('.env')));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertFileDoesNotExist(database_path('database.sqlite'));
    }

    #[DataProvider('emptyKeySyntax')]
    public function test_missing_or_blank_key_is_persisted_once_without_losing_other_environment_values(string $line): void
    {
        $environment = "APP_NAME=Fixture\n{$line}\nKEEP_SETTING=before\n# REL003 retained comment\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        File::put(database_path('database.sqlite'), 'REL003 existing database');
        config(['app.key' => '', 'app.cipher' => 'AES-256-CBC']);

        $this->artisan('app:init', ['--skip-storage-link' => true])->assertSuccessful();
        $after = File::get(base_path('.env'));
        $values = Dotenv::parse($after);
        $this->assertArrayHasKey('APP_KEY', $values);
        $this->assertIsString($values['APP_KEY']);
        $this->assertStringStartsWith('base64:', $values['APP_KEY']);
        $this->assertSame('Fixture', $values['APP_NAME']);
        $this->assertSame('before', $values['KEEP_SETTING']);
        $this->assertStringContainsString('# REL003 retained comment', $after);
        $encrypted = $this->encrypter($values['APP_KEY'])->encryptString('REL003 generated key fixture');

        $this->artisan('app:init', ['--skip-storage-link' => true])->assertSuccessful();
        $this->assertSame($after, File::get(base_path('.env')));
        $this->assertSame('REL003 generated key fixture', $this->encrypter(Dotenv::parse($after)['APP_KEY'])->decryptString($encrypted));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertSame('REL003 existing database', File::get(database_path('database.sqlite')));
    }

    /** @return array<string, array{string}> */
    public static function emptyKeySyntax(): array
    {
        return [
            'absent' => [''],
            'empty' => ['APP_KEY='],
            'double quoted empty' => ['APP_KEY=""'],
            'single quoted empty' => ["APP_KEY=''"],
            'comment only' => ['APP_KEY= # no key'],
            'quoted whitespace' => ['APP_KEY="   "'],
            'unquoted whitespace' => ['APP_KEY=   '],
        ];
    }

    #[DataProvider('keyFailures')]
    public function test_key_failure_or_false_success_stops_before_storage_and_database(int $keyExit, bool $writeInvalidKey): void
    {
        File::put(base_path('.env'), "APP_NAME=Fixture\nAPP_KEY=\n");
        File::put(base_path('.config'), 'REL003 private config');
        config(['app.key' => '']);
        $keyCalls = 0;
        $storageCalls = 0;
        $this->replaceCommand('key:generate {--force} {--show}', function () use (&$keyCalls, $keyExit, $writeInvalidKey): int {
            $keyCalls++;
            if ($writeInvalidKey) {
                File::put(base_path('.env'), "APP_NAME=Fixture\nAPP_KEY=REL003-invalid-generated-key\n");
            }

            return $keyExit;
        });
        $this->replaceCommand('storage:link {--force}', function () use (&$storageCalls): int {
            $storageCalls++;

            return Command::SUCCESS;
        });

        $this->assertInitFails();

        $this->assertSame(1, $keyCalls);
        $this->assertSame(0, $storageCalls);
        $this->assertSame($writeInvalidKey ? 'REL003-invalid-generated-key' : '', Dotenv::parse(File::get(base_path('.env')))['APP_KEY']);
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertFileDoesNotExist(database_path('database.sqlite'));
    }

    /** @return array<string, array{int, bool}> */
    public static function keyFailures(): array
    {
        return [
            'nonzero key command' => [Command::FAILURE, false],
            'zero without a persisted key' => [Command::SUCCESS, false],
            'zero with an invalid generated key' => [Command::SUCCESS, true],
        ];
    }

    #[DataProvider('storageFailures')]
    public function test_storage_link_failure_stops_before_database_and_does_not_claim_success(int $storageExit): void
    {
        $environment = 'APP_KEY='.$this->fixtureKey()."\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        $target = storage_path('app/public');
        File::ensureDirectoryExists($target);
        config(['filesystems.links' => [public_path('storage') => $target]]);
        $calls = 0;
        $this->replaceCommand('storage:link {--force}', function () use (&$calls, $storageExit): int {
            $calls++;

            return $storageExit;
        });

        $this->assertInitFails();

        $this->assertSame(1, $calls);
        $this->assertSame($environment, File::get(base_path('.env')));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertFileDoesNotExist(database_path('database.sqlite'));
    }

    /** @return array<string, array{int}> */
    public static function storageFailures(): array
    {
        return ['nonzero storage command' => [Command::FAILURE], 'zero without a configured link' => [Command::SUCCESS]];
    }

    public function test_empty_link_configuration_does_not_require_a_default_public_storage_link(): void
    {
        File::put(base_path('.env'), 'APP_KEY='.$this->fixtureKey()."\n");
        File::put(base_path('.config'), 'REL003 private config');
        config(['filesystems.links' => []]);
        $this->replaceCommand('storage:link {--force}', fn (): int => Command::SUCCESS);

        $this->artisan('app:init')->assertSuccessful();

        $this->assertSame('', File::get(database_path('database.sqlite')));
        $this->assertFileDoesNotExist(public_path('storage'));
    }

    public function test_configured_paths_already_resolving_to_the_target_skip_the_storage_command(): void
    {
        $environment = 'APP_KEY='.$this->fixtureKey()."\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        $target = storage_path('app/public');
        File::ensureDirectoryExists($target);
        config(['filesystems.links' => [$target.DIRECTORY_SEPARATOR.'.' => $target]]);
        $calls = 0;
        $this->replaceCommand('storage:link {--force}', function () use (&$calls): int {
            $calls++;

            return Command::FAILURE;
        });

        $this->artisan('app:init')->assertSuccessful();

        $this->assertSame(0, $calls);
        $this->assertSame($environment, File::get(base_path('.env')));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertSame('', File::get(database_path('database.sqlite')));
    }

    #[DataProvider('fileFailures')]
    public function test_filesystem_false_results_abort_the_remaining_initialization_steps(string $failure): void
    {
        $environment = 'APP_KEY='.$this->fixtureKey()."\n";
        if ($failure !== 'copy') {
            File::put(base_path('.env'), $environment);
        }
        if ($failure === 'sqlite') {
            File::put(base_path('.config'), 'REL003 private config');
        }
        $keyCalls = 0;
        $storageCalls = 0;
        $this->replaceCommand('key:generate {--force} {--show}', function () use (&$keyCalls): int {
            $keyCalls++;

            return Command::SUCCESS;
        });
        $this->replaceCommand('storage:link {--force}', function () use (&$storageCalls): int {
            $storageCalls++;

            return Command::SUCCESS;
        });
        $files = File::getFacadeRoot();
        $this->assertInstanceOf(Filesystem::class, $files);
        if ($failure === 'copy') {
            $expectation = File::partialMock()->shouldReceive('copy');
            $this->assertInstanceOf(CompositeExpectation::class, $expectation);
            $expectation->__call('andReturnUsing', [
                fn (mixed $source, mixed $target): bool => $target === base_path('.env') ? false : $files->copy($source, $target),
            ]);
        } else {
            $target = in_array($failure, ['config', 'config-short'], true) ? base_path('.config') : database_path('database.sqlite');
            $expectation = File::partialMock()->shouldReceive('put');
            $this->assertInstanceOf(CompositeExpectation::class, $expectation);
            $expectation->__call('andReturnUsing', [
                function (mixed $path, mixed $contents, mixed $lock = false) use ($target, $failure, $files): int|false {
                    if ($path !== $target) {
                        return $files->put($path, $contents, $lock);
                    }

                    return $failure === 'config-short' ? $files->put($path, substr($contents, 0, 3), $lock) : false;
                },
            ]);
        }

        $this->assertInitFails($failure === 'sqlite' ? ['--skip-storage-link' => true] : []);

        $this->assertSame(0, $keyCalls);
        $this->assertSame(0, $storageCalls);
        $this->assertFileDoesNotExist(database_path('database.sqlite'));
        if ($failure === 'copy') {
            $this->assertFileDoesNotExist(base_path('.env'));
            $this->assertFileDoesNotExist(base_path('.config'));
        } else {
            $this->assertSame($environment, File::get(base_path('.env')));
            if ($failure === 'config') {
                $this->assertFileDoesNotExist(base_path('.config'));
            } elseif ($failure === 'config-short') {
                if (File::exists(base_path('.config'))) {
                    $this->assertSame(3, strlen(File::get(base_path('.config'))));
                }
            } else {
                $this->assertSame('REL003 private config', File::get(base_path('.config')));
            }
        }
    }

    /** @return array<string, array{string}> */
    public static function fileFailures(): array
    {
        return ['environment copy failed' => ['copy'], 'private configuration write failed' => ['config'], 'private configuration short write' => ['config-short'], 'database write failed' => ['sqlite']];
    }

    public function test_invalid_dotenv_fails_without_replacing_any_existing_key_or_running_commands(): void
    {
        $environment = "APP_KEY=\"unterminated\nKEEP_SETTING=before\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        $calls = 0;
        foreach (['key:generate {--force} {--show}', 'storage:link {--force}'] as $signature) {
            $this->replaceCommand($signature, function () use (&$calls): int {
                $calls++;

                return Command::SUCCESS;
            });
        }

        $this->assertInitFails();

        $this->assertSame(0, $calls);
        $this->assertSame($environment, File::get(base_path('.env')));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertFileDoesNotExist(database_path('database.sqlite'));
    }

    public function test_complete_multiline_environment_and_existing_probe_names_are_preserved(): void
    {
        $environment = 'APP_KEY='.$this->fixtureKey()."\nCERTIFICATE=\"first line\nsecond line\"\nAPP_INIT_DOTENV_COMPLETION_CHECK=keep\nAPP_INIT_DOTENV_COMPLETION_CHECK_=keep-too\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        File::put(database_path('database.sqlite'), 'REL003 existing database');
        $values = Dotenv::parse($environment);
        $this->assertSame("first line\nsecond line", $values['CERTIFICATE']);
        $this->assertSame('keep', $values['APP_INIT_DOTENV_COMPLETION_CHECK']);
        $this->assertSame('keep-too', $values['APP_INIT_DOTENV_COMPLETION_CHECK_']);

        $this->artisan('app:init', ['--skip-storage-link' => true])->assertSuccessful();

        $this->assertSame($environment, File::get(base_path('.env')));
        $this->assertSame($values, Dotenv::parse(File::get(base_path('.env'))));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertSame('REL003 existing database', File::get(database_path('database.sqlite')));
    }

    public function test_existing_native_file_hard_link_skips_the_storage_command(): void
    {
        $environment = 'APP_KEY='.$this->fixtureKey()."\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        $target = storage_path('app/native-target.txt');
        $link = public_path('native-alias.txt');
        File::ensureDirectoryExists(dirname($target));
        File::ensureDirectoryExists(dirname($link));
        File::put($target, 'REL003 hardlink fixture');
        $this->assertTrue(link($target, $link));
        $this->assertNotSame(realpath($target), realpath($link));
        $this->assertSame(stat($target)['ino'], stat($link)['ino']);
        config(['filesystems.links' => [$link => $target]]);
        $calls = 0;
        $this->replaceCommand('storage:link {--force}', function () use (&$calls): int {
            $calls++;

            return Command::FAILURE;
        });

        $this->artisan('app:init')->assertSuccessful();

        $this->assertSame(0, $calls);
        $this->assertSame($environment, File::get(base_path('.env')));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertSame('', File::get(database_path('database.sqlite')));
        $this->assertSame('REL003 hardlink fixture', File::get($target));
        $this->assertSame('REL003 hardlink fixture', File::get($link));
    }

    public function test_new_native_file_link_is_created_by_the_real_storage_command(): void
    {
        $environment = 'APP_KEY='.$this->fixtureKey()."\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        $target = storage_path('app/native-target.txt');
        $link = public_path('native-alias.txt');
        File::ensureDirectoryExists(dirname($target));
        File::ensureDirectoryExists(dirname($link));
        File::put($target, 'REL003 real storage link fixture');
        config(['filesystems.links' => [$link => $target]]);
        $this->assertFileDoesNotExist($link);
        $output = new BufferedOutput;

        $status = app(ConsoleKernel::class)->call('app:init', [], $output);

        $this->assertFileExists($link);
        $this->assertSame('REL003 real storage link fixture', File::get($link));
        $this->assertSame(stat($target)['dev'], stat($link)['dev']);
        $this->assertSame(stat($target)['ino'], stat($link)['ino']);
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('Start kit initialized.', $output->fetch());
        $this->assertSame($environment, File::get(base_path('.env')));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertSame('', File::get(database_path('database.sqlite')));
    }

    public function test_copied_file_is_not_a_storage_link_even_with_identical_contents(): void
    {
        $environment = 'APP_KEY='.$this->fixtureKey()."\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        $target = storage_path('app/native-target.txt');
        $link = public_path('independent-copy.txt');
        File::ensureDirectoryExists(dirname($target));
        File::ensureDirectoryExists(dirname($link));
        File::put($target, 'REL003 identical contents');
        File::copy($target, $link);
        $this->assertSame(File::get($target), File::get($link));
        $this->assertNotSame(stat($target)['ino'], stat($link)['ino']);
        config(['filesystems.links' => [$link => $target]]);
        $calls = 0;
        $this->replaceCommand('storage:link {--force}', function () use (&$calls): int {
            $calls++;

            return Command::SUCCESS;
        });

        $this->assertInitFails();

        $this->assertSame(1, $calls);
        $this->assertSame($environment, File::get(base_path('.env')));
        $this->assertSame('REL003 private config', File::get(base_path('.config')));
        $this->assertFileDoesNotExist(database_path('database.sqlite'));
        $this->assertSame('REL003 identical contents', File::get($target));
        $this->assertSame('REL003 identical contents', File::get($link));
    }

    public function test_real_storage_directory_link_is_created_and_reused_without_errors(): void
    {
        $environment = 'APP_KEY='.$this->fixtureKey()."\n";
        File::put(base_path('.env'), $environment);
        File::put(base_path('.config'), 'REL003 private config');
        $target = storage_path('app/public');
        $link = public_path('storage');
        File::ensureDirectoryExists($target);
        File::ensureDirectoryExists(dirname($link));
        File::put($target.'/ready.txt', 'REL003 directory target fixture');
        config(['filesystems.links' => [$link => $target]]);

        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $output = new BufferedOutput;
                $status = app(ConsoleKernel::class)->call('app:init', [], $output);
                $text = $output->fetch();
                $this->assertDirectoryExists($link);
                $targetIdentity = stat($target);
                $linkIdentity = stat($link);
                $this->assertIsArray($targetIdentity);
                $this->assertIsArray($linkIdentity);
                $this->assertGreaterThan(0, $targetIdentity['ino']);
                $this->assertSame($targetIdentity['dev'], $linkIdentity['dev']);
                $this->assertSame($targetIdentity['ino'], $linkIdentity['ino']);
                $this->assertSame(Command::SUCCESS, $status, 'Attempt '.$attempt.': '.$text.' target='.var_export(realpath($target), true).' link='.var_export(realpath($link), true));
                $this->assertSame('REL003 directory target fixture', File::get($link.'/ready.txt'));
                $this->assertStringContainsString('Start kit initialized.', $text);
                $this->assertStringNotContainsString('already exists', $text);
                $this->assertSame($environment, File::get(base_path('.env')));
                $this->assertSame('REL003 private config', File::get(base_path('.config')));
                $this->assertSame('', File::get(database_path('database.sqlite')));
            }
        } finally {
            if (PHP_OS_FAMILY === 'Windows' && is_dir($link)) {
                rmdir($link);
            } elseif (is_link($link)) {
                unlink($link);
            }
        }
    }

    private function fixtureKey(): string
    {
        return 'base64:'.base64_encode(str_repeat('K', 32));
    }

    private function encrypter(string $key): Encrypter
    {
        $this->assertStringStartsWith('base64:', $key);
        $decoded = base64_decode(substr($key, 7), true);
        $this->assertIsString($decoded);
        $this->assertSame(32, strlen($decoded));

        return new Encrypter($decoded, 'AES-256-CBC');
    }

    private function replaceCommand(string $signature, Closure $handler): void
    {
        $command = new class($signature, $handler) extends Command
        {
            public function __construct(string $signature, private readonly Closure $handler)
            {
                $this->signature = $signature;
                parent::__construct();
            }

            public function handle(): int
            {
                return ($this->handler)();
            }
        };
        $kernel = app(ConsoleKernel::class);
        $this->assertInstanceOf(Kernel::class, $kernel);
        $kernel->registerCommand($command);
    }

    /** @param array<string, bool> $options */
    private function assertInitFails(array $options = []): void
    {
        $output = new BufferedOutput;
        try {
            $status = app(ConsoleKernel::class)->call('app:init', $options, $output);
        } catch (Throwable $exception) {
            $this->fail('app:init must return failure without throwing: '.$exception::class);
        }

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringNotContainsString('Start kit initialized.', $output->fetch());
    }
}
