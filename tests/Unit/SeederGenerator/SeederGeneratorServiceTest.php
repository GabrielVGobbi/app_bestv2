<?php

declare(strict_types=1);

namespace Tests\Unit\SeederGenerator;

use App\Modules\SeederGenerator\DTOs\SeederBlueprint;
use App\Modules\SeederGenerator\Http\Requests\SeederGeneratorRequest;
use App\Modules\SeederGenerator\Services\SeederGeneratorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Fixtures\SeederGenerator\AllowedRecord;
use Tests\Fixtures\SeederGenerator\AllowedRecordFactory;
use Tests\TestCase;
use Tests\TestEnvironment;
use Throwable;

class SeederGeneratorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $this->assertStringStartsWith(str_replace('\\', '/', $this->testEnvironment->path).'/', str_replace('\\', '/', base_path()));
        File::ensureDirectoryExists(database_path('seeders'));

        Artisan::shouldReceive('call')->zeroOrMoreTimes()->andReturn(0);
        Artisan::shouldReceive('output')->zeroOrMoreTimes()->andReturn('Synthetic seeder output');

        Schema::create('allowed_records', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('unique_key')->unique();
            $table->string('secret')->nullable();
        });
        config(['seeder-generator.allowed_tables' => ['allowed_records' => $this->policy()]]);
    }

    /** @return array<string, mixed> */
    private function policy(array $overrides = []): array
    {
        return array_replace([
            'columns' => ['label', 'unique_key'],
            'unique_columns' => ['unique_key'],
            'modes' => ['data', 'factory'],
            'operations' => ['preview', 'generate', 'execute', 'run'],
            'model' => AllowedRecord::class,
            'factory' => AllowedRecordFactory::class,
            'factory_trusted' => true,
            'truncate' => false,
        ], $overrides);
    }

    private function blueprint(array $overrides = []): SeederBlueprint
    {
        return new SeederBlueprint(...array_replace([
            'table' => 'allowed_records', 'mode' => 'data', 'limit' => 2,
            'truncate' => false, 'idempotent' => false, 'uniqueColumn' => null,
            'seederName' => 'SafeRecords',
        ], $overrides));
    }

    private function generator(): SeederGeneratorService
    {
        return app(SeederGeneratorService::class);
    }

    private function save(SeederBlueprint $blueprint): string
    {
        $generator = $this->generator();

        return $generator->saveSeeder($blueprint, $generator->generateSeederCode($blueprint));
    }

    private function seedRows(string $label = 'Synthetic one'): void
    {
        DB::table('allowed_records')->insert([
            ['label' => $label, 'unique_key' => 'fixture-one', 'secret' => 'SECRET_FIXTURE_ONE'],
            ['label' => 'Synthetic two', 'unique_key' => 'fixture-two', 'secret' => 'SECRET_FIXTURE_TWO'],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return DB::table('allowed_records')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
    }

    private function assertRejected(callable $action, string $exceptionClass = ValidationException::class): void
    {
        try {
            $action();
        } catch (Throwable $exception) {
            $this->assertInstanceOf($exceptionClass, $exception, $exception::class.': '.$exception->getMessage());

            return;
        }

        $this->fail('The operation accepted a prohibited target or capability.');
    }

    /** @return array<string, string> */
    private function projectSnapshot(): array
    {
        $snapshot = [];
        foreach (File::allFiles(base_path(), true) as $file) {
            if ($file->getRelativePathname() === '.start-kit-generator.lock') {
                continue;
            }
            $snapshot[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }
        ksort($snapshot);

        return $snapshot;
    }

    private function invoke(string $operation, ?SeederBlueprint $blueprint = null): mixed
    {
        $blueprint ??= $this->blueprint();

        return match ($operation) {
            'tables' => $this->generator()->getTables(),
            'columns' => $this->generator()->getTableColumns($blueprint->table),
            'sample' => $this->generator()->getSampleData($blueprint->table, $blueprint->limit),
            'preview' => $this->generator()->generateSeederCode($blueprint),
            'generate' => $this->generator()->saveSeeder($blueprint, '<?php // supplied fixture'),
            'execute' => $this->generator()->executeDirectly($blueprint),
            'run' => $this->generator()->runSeeder($blueprint->className()),
            default => throw new RuntimeException('Unknown synthetic operation.'),
        };
    }

    public function test_empty_default_policy_lists_nothing_without_querying_data(): void
    {
        config(['seeder-generator.allowed_tables' => []]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame([], $this->generator()->getTables());
        $this->assertSame([], DB::getQueryLog());
    }

    #[DataProvider('rawNameRequests')]
    public function test_generator_paths_preserve_raw_json_and_form_names_for_strict_validation(string $path, string $format, bool $preserved): void
    {
        $payload = ['table' => 'allowed_records', 'mode' => 'data', 'limit' => 1, 'seeder_name' => "SafeRecords\n"];
        $request = $format === 'json'
            ? Request::create($path, 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, JSON_THROW_ON_ERROR))
            : Request::create($path, 'POST', $payload);
        $processed = (new TrimStrings)->handle($request, fn (Request $request): Request => $request);

        $this->assertSame($preserved ? "SafeRecords\n" : 'SafeRecords', $processed->input('seeder_name'));
        $validator = Validator::make($processed->all(), (new SeederGeneratorRequest)->rules());
        $this->assertSame($preserved, $validator->fails());
        $this->assertSame($preserved ? ['seeder_name'] : [], $validator->errors()->keys());
    }

    public static function rawNameRequests(): array
    {
        $cases = [];
        foreach (['json', 'form'] as $format) {
            foreach ([
                '/admin/dev/seeder-generator' => true,
                '/admin/dev/seeder-generator/preview' => true,
                '/admin/dev/seeder-generator-other/preview' => false,
                '/profile' => false,
            ] as $path => $preserved) {
                $cases[$format.'-'.$path] = [$path, $format, $preserved];
            }
        }

        return $cases;
    }

    #[DataProvider('directOperations')]
    public function test_empty_policy_denies_every_direct_capability_before_queries_or_writes(string $operation): void
    {
        config(['seeder-generator.allowed_tables' => []]);
        $before = $this->projectSnapshot();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertRejected(fn () => $this->invoke($operation));
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame($before, $this->projectSnapshot());
        Artisan::shouldNotHaveReceived('call');
    }

    public static function directOperations(): array
    {
        return [['columns'], ['sample'], ['preview'], ['generate'], ['execute'], ['run']];
    }

    #[DataProvider('unsafeEnvironments')]
    public function test_public_services_refuse_unsafe_or_disagreeing_environments(string $operation, string $environment, string $configured): void
    {
        $original = $this->app['env'];
        $this->app->instance('env', $environment);
        config(['app.env' => $configured]);
        $before = $this->projectSnapshot();
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $this->assertRejected(fn () => $this->invoke($operation), AuthorizationException::class);
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame($before, $this->projectSnapshot());
            Artisan::shouldNotHaveReceived('call');
        } finally {
            $this->app->instance('env', $original);
            config(['app.env' => 'testing']);
        }
    }

    public static function unsafeEnvironments(): array
    {
        $cases = [];
        foreach (['tables', 'columns', 'sample', 'preview', 'generate', 'execute', 'run'] as $operation) {
            foreach ([['production', 'production'], ['staging', 'staging'], ['testing', 'local'], ['local', 'testing']] as [$environment, $configured]) {
                $cases[$operation.'-'.$environment.'-'.$configured] = [$operation, $environment, $configured];
            }
        }

        return $cases;
    }

    public function test_listing_metadata_and_samples_only_expose_configured_projection(): void
    {
        $this->seedRows();
        Schema::create('hidden_records', fn (Blueprint $table) => $table->string('private_payload'));
        DB::table('hidden_records')->insert(['private_payload' => 'HIDDEN_FIXTURE']);

        $tables = $this->generator()->getTables();
        $this->assertSame(['allowed_records'], array_column($tables, 'name'));
        $this->assertSame(2, $tables[0]['count']);
        $this->assertSame(['label', 'unique_key'], array_column($tables[0]['columns'], 'name'));
        $this->assertTrue($tables[0]['hasFactory']);
        $this->assertSame(AllowedRecord::class, $tables[0]['modelClass']);
        $this->assertSame([
            ['label' => 'Synthetic one', 'unique_key' => 'fixture-one'],
            ['label' => 'Synthetic two', 'unique_key' => 'fixture-two'],
        ], $this->generator()->getSampleData('allowed_records', 2));
        $this->assertSame(['label', 'unique_key'], array_column($this->generator()->getTableColumns('allowed_records'), 'name'));
    }

    #[DataProvider('prohibitedTables')]
    public function test_even_explicit_configuration_cannot_authorize_protected_tables(string $table): void
    {
        config(['seeder-generator.allowed_tables' => [$table => $this->policy(['model' => null, 'factory' => null, 'factory_trusted' => false])]]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertRejected(fn () => $this->generator()->getSampleData($table, 1));
        $this->assertSame([], DB::getQueryLog());
    }

    public static function prohibitedTables(): array
    {
        return [
            ['users'], ['roles'], ['users_roles'], ['users_permissions'], ['roles_permissions'],
            ['personal_access_tokens'], ['external_api_connections'], ['app_settings'], ['email_logs'], ['sessions'],
            ['uploadeds'], ['backup_histories'], ['zip_requests'], ['email_webhook_events'], ['rich_texts'], ['timeline_comments'],
        ];
    }

    #[DataProvider('invalidPolicies')]
    public function test_incomplete_or_sensitive_projection_is_rejected_before_data_query(array $overrides): void
    {
        config(['seeder-generator.allowed_tables.allowed_records' => $this->policy($overrides)]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertRejected(fn () => $this->generator()->generateSeederCode($this->blueprint()));
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/\Aselect\s+.*\sfrom\s+["`]?allowed_records/i', $query['query']);
        }
    }

    public static function invalidPolicies(): array
    {
        return [
            'no columns' => [['columns' => []]],
            'no modes' => [['modes' => []]],
            'no operations' => [['operations' => []]],
            'secret column' => [['columns' => ['label', 'secret']]],
            'missing column' => [['columns' => ['label', 'not_present']]],
            'expression column' => [['columns' => ['label as stolen']]],
            'non-string column' => [['columns' => ['label', 1]]],
        ];
    }

    #[DataProvider('sensitiveExistingColumns')]
    public function test_existing_secret_or_content_columns_cannot_be_authorized_by_configuration(string $column): void
    {
        Schema::table('allowed_records', fn (Blueprint $table) => $table->string($column)->nullable());
        $this->seedRows();
        DB::table('allowed_records')->update([$column => 'SENSITIVE_COLUMN_FIXTURE']);
        config(['seeder-generator.allowed_tables.allowed_records.columns' => ['label', 'unique_key', $column]]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertRejected(fn () => $this->generator()->getSampleData('allowed_records', 2));
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/\Aselect\s+.*\sfrom\s+["`]?allowed_records/i', $query['query']);
        }
    }

    public static function sensitiveExistingColumns(): array
    {
        return [['api_key'], ['access_key'], ['private_key'], ['webhook_url'], ['body'], ['content']];
    }

    #[DataProvider('invalidBlueprints')]
    public function test_direct_blueprint_cannot_bypass_validation(array $overrides): void
    {
        $before = $this->projectSnapshot();
        $this->assertRejected(fn () => $this->generator()->generateSeederCode($this->blueprint($overrides)));
        $this->assertSame($before, $this->projectSnapshot());
    }

    public static function invalidBlueprints(): array
    {
        return [
            'limit zero' => [['limit' => 0]],
            'limit too large' => [['limit' => 1001]],
            'unknown mode' => [['mode' => 'anything']],
            'table escape' => [['table' => '../allowed_records']],
            'table quote' => [['table' => "allowed_records'); die('fixture');//"]],
            'name escape' => [['seederName' => '../Escape']],
            'name trailing newline' => [['seederName' => "Safe\n"]],
            'unique PHP injection' => [['idempotent' => true, 'uniqueColumn' => "unique_key'); die('fixture');//"]],
            'unprojected unique' => [['idempotent' => true, 'uniqueColumn' => 'secret']],
            'missing unique' => [['idempotent' => true, 'uniqueColumn' => null]],
            'truncate without opt-in' => [['truncate' => true]],
        ];
    }

    public function test_unique_column_must_have_a_real_simple_nonnullable_unique_constraint(): void
    {
        config(['seeder-generator.allowed_tables.allowed_records.unique_columns' => ['label']]);
        $this->seedRows();

        $this->assertRejected(fn () => $this->generator()->generateSeederCode($this->blueprint(['idempotent' => true, 'uniqueColumn' => 'label'])));
    }

    #[DataProvider('unsafeUniqueConstraints')]
    public function test_nullable_or_composite_unique_constraints_do_not_authorize_idempotence(bool $nullable, bool $composite): void
    {
        Schema::drop('allowed_records');
        Schema::create('allowed_records', function (Blueprint $table) use ($nullable, $composite): void {
            $table->id();
            $table->string('label');
            $table->string('unique_key')->nullable($nullable);
            $table->string('secret')->nullable();
            $table->unique($composite ? ['unique_key', 'label'] : ['unique_key']);
        });

        $this->assertRejected(fn () => $this->generator()->generateSeederCode($this->blueprint(['idempotent' => true, 'uniqueColumn' => 'unique_key'])));
    }

    public static function unsafeUniqueConstraints(): array
    {
        return [[true, false], [false, true]];
    }

    public function test_generated_data_preserves_php_literal_bytes_without_exporting_secret_columns(): void
    {
        $label = "Quotes '\" backslash \\ dollar \$ nul \0 newline\nUnicode ç 漢字";
        $this->seedRows($label);
        $before = $this->projectSnapshot();
        $code = $this->generator()->generateSeederCode($this->blueprint(['idempotent' => true, 'uniqueColumn' => 'unique_key']));

        $this->assertStringNotContainsString('SECRET_FIXTURE', $code);
        $this->assertStringNotContainsString("'secret'", $code);
        $this->assertStringContainsString(var_export($label, true), $code);
        $this->assertNotEmpty(token_get_all($code, TOKEN_PARSE));
        $this->assertSame($before, $this->projectSnapshot());
        $lintPath = database_path('seeders/literal-lint.php');
        File::put($lintPath, $code);
        [$exit, $output] = $this->lintOwnedPhp($lintPath);
        $this->assertSame(0, $exit, $output);
    }

    /** @return array{int, string} */
    private function lintOwnedPhp(string $path): array
    {
        $this->assertStringStartsWith(realpath(base_path()).DIRECTORY_SEPARATOR, realpath($path));
        $process = new Process([PHP_BINARY, '-n', '-l', $path], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'PHPRC' => false, 'PHP_INI_SCAN_DIR' => false,
        ], timeout: 10);
        $exit = $process->run();

        return [$exit, $process->getOutput().$process->getErrorOutput()];
    }

    public function test_data_execution_counts_only_actual_inserts_and_is_idempotent(): void
    {
        $this->seedRows();
        $before = $this->rows();
        $result = $this->generator()->executeDirectly($this->blueprint());

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['records']);
        $this->assertSame($before, $this->rows());
        $result = $this->generator()->executeDirectly($this->blueprint(['idempotent' => true, 'uniqueColumn' => 'unique_key']));
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['records']);
    }

    public function test_opted_in_truncate_uses_transactional_delete_without_disabling_foreign_keys(): void
    {
        $this->seedRows();
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $result = $this->generator()->executeDirectly($this->blueprint(['truncate' => true]));

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['records']);
        $this->assertSame(2, DB::table('allowed_records')->count());
        $this->assertSame([null, null], DB::table('allowed_records')->orderBy('id')->pluck('secret')->all());
        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
        $this->assertMatchesRegularExpression('/delete\s+from\s+["`]?allowed_records/i', $queries);
        $this->assertDoesNotMatchRegularExpression('/truncate|foreign_key_checks|foreign_keys\s*=\s*(?:off|0)/i', $queries);
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
    }

    public function test_partial_replacement_refuses_to_delete_rows_outside_the_sample(): void
    {
        $this->seedRows();
        DB::table('allowed_records')->insert(['label' => 'Outside sample', 'unique_key' => 'fixture-three', 'secret' => 'PRESERVE_OUTSIDE_SAMPLE']);
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        $before = $this->rows();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $result = $this->generator()->executeDirectly($this->blueprint(['truncate' => true]));

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['records']);
        $this->assertSame($before, $this->rows());
        $this->assertDoesNotMatchRegularExpression('/\bdelete\s+from\b/i', implode("\n", array_column(DB::getQueryLog(), 'query')));
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
    }

    #[DataProvider('replacementCaptureOperations')]
    public function test_partial_replacement_cannot_be_previewed_or_saved(string $operation): void
    {
        $this->seedRows();
        DB::table('allowed_records')->insert(['label' => 'Outside sample', 'unique_key' => 'fixture-three']);
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        $before = $this->rows();
        $filesBefore = $this->projectSnapshot();
        try {
            $this->generator()->{$operation}($this->blueprint(['truncate' => true]));
            $this->fail('An incomplete sample cannot declare whole-table replacement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('truncate', $exception->errors());
        }
        $this->assertSame($before, $this->rows());
        $this->assertSame($filesBefore, $this->projectSnapshot());
    }

    public static function replacementCaptureOperations(): iterable
    {
        yield 'preview' => ['generateSeederCode'];
        yield 'save' => ['saveSeeder'];
    }

    public function test_old_snapshot_cannot_replace_a_larger_destination_even_below_the_blueprint_limit(): void
    {
        $this->seedRows();
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        $descriptor = $this->artifactDescriptor($this->blueprint(['truncate' => true, 'limit' => 10]));
        DB::table('allowed_records')->insert(['label' => 'Newer row', 'unique_key' => 'fixture-three', 'secret' => 'NEWER_SECRET']);
        $before = $this->rows();
        $failed = false;
        try {
            $this->generator()->executeGenerated($descriptor);
        } catch (RuntimeException $exception) {
            $failed = true;
            $this->assertNotSame('', $exception->getMessage());
        }
        $this->assertTrue($failed, 'A two-row snapshot cannot replace a three-row destination.');
        $this->assertSame($before, $this->rows());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
    }

    public function test_full_replacement_preserves_duplicate_projected_rows(): void
    {
        $this->seedRows('Same projection');
        DB::table('allowed_records')->update(['label' => 'Same projection']);
        Schema::table('allowed_records', function (Blueprint $table): void {
            $table->string('unique_key')->nullable()->change();
        });
        config(['seeder-generator.allowed_tables.allowed_records' => $this->policy([
            'columns' => ['label'], 'unique_columns' => [], 'truncate' => true,
        ])]);

        $result = $this->generator()->executeDirectly($this->blueprint(['truncate' => true]));

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['records']);
        $this->assertSame(['Same projection', 'Same projection'], DB::table('allowed_records')->orderBy('id')->pluck('label')->all());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
    }

    public function test_duplicate_unique_key_in_replacement_rolls_back_instead_of_skipping_a_snapshot_row(): void
    {
        $this->seedRows();
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        $descriptor = $this->artifactDescriptor($this->blueprint(['truncate' => true, 'idempotent' => true, 'uniqueColumn' => 'unique_key']));
        $descriptor['rows'][1]['unique_key'] = $descriptor['rows'][0]['unique_key'];
        $before = $this->rows();
        $failed = false;
        try {
            $this->generator()->executeGenerated($descriptor);
        } catch (RuntimeException) {
            $failed = true;
        }
        $this->assertTrue($failed);
        $this->assertSame($before, $this->rows());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
    }

    public function test_empty_snapshot_cannot_erase_a_nonempty_destination(): void
    {
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        $descriptor = $this->artifactDescriptor($this->blueprint(['truncate' => true]));
        $this->assertSame([], $descriptor['rows']);
        $this->seedRows();
        $before = $this->rows();
        $failed = false;
        try {
            $this->generator()->executeGenerated($descriptor);
        } catch (RuntimeException) {
            $failed = true;
        }
        $this->assertTrue($failed);
        $this->assertSame($before, $this->rows());
    }

    public function test_an_ordinary_limited_sample_remains_nondestructive(): void
    {
        $this->seedRows();
        DB::table('allowed_records')->insert(['label' => 'Outside sample', 'unique_key' => 'fixture-three', 'secret' => 'PRESERVE_OUTSIDE_SAMPLE']);
        $before = $this->rows();
        $descriptor = $this->artifactDescriptor($this->blueprint());
        $this->assertCount(2, $descriptor['rows']);
        $result = $this->generator()->executeDirectly($this->blueprint());
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['records']);
        $this->assertSame($before, $this->rows());
    }

    public function test_failed_insert_after_delete_rolls_back_data_and_does_not_expose_sql_or_secrets(): void
    {
        $this->seedRows();
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        DB::unprepared("CREATE TRIGGER fixture_fail_insert BEFORE INSERT ON allowed_records WHEN NEW.unique_key = 'fixture-two' BEGIN SELECT RAISE(ABORT, 'SECRET_FIXTURE_TRIGGER'); END");
        $before = $this->rows();

        $result = $this->generator()->executeDirectly($this->blueprint(['truncate' => true]));

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['records']);
        $this->assertSame(1, $result['exit_code']);
        $this->assertSame($before, $this->rows());
        $this->assertDoesNotMatchRegularExpression('/SECRET_FIXTURE|SQLSTATE|insert into|FOREIGN_KEY_CHECKS|fixture-two/i', $result['message']);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
    }

    public function test_required_column_failure_after_delete_cannot_commit_an_empty_destination(): void
    {
        Schema::drop('allowed_records');
        Schema::create('allowed_records', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('unique_key')->unique();
            $table->string('secret');
        });
        $this->seedRows();
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        $before = $this->rows();

        $result = $this->generator()->executeDirectly($this->blueprint(['truncate' => true]));

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['records']);
        $this->assertSame(1, $result['exit_code']);
        $this->assertSame($before, $this->rows());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDoesNotMatchRegularExpression('/SQLSTATE|SECRET_FIXTURE|insert into|NOT NULL/i', $result['message']);
    }

    #[DataProvider('untrustedFactories')]
    public function test_factory_execution_requires_explicit_trusted_compatible_mapping(array $overrides): void
    {
        config(['seeder-generator.allowed_tables.allowed_records' => $this->policy($overrides)]);
        $this->assertRejected(fn () => $this->generator()->executeDirectly($this->blueprint(['mode' => 'factory'])));
        $this->assertSame(0, DB::table('allowed_records')->count());
    }

    public static function untrustedFactories(): array
    {
        return [
            'no trust' => [['factory_trusted' => false]],
            'no factory' => [['factory' => null]],
            'no model' => [['model' => null]],
            'wrong factory' => [['factory' => \stdClass::class]],
            'wrong model' => [['model' => \stdClass::class]],
        ];
    }

    public function test_trusted_factory_ignores_hidden_data_flags_and_preserves_existing_rows(): void
    {
        $this->seedRows();
        $result = $this->generator()->executeDirectly($this->blueprint([
            'mode' => 'factory', 'truncate' => true, 'idempotent' => true, 'uniqueColumn' => 'not_a_column',
        ]));

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['records']);
        $this->assertSame(4, DB::table('allowed_records')->count());
        $this->assertSame(2, DB::table('allowed_records')->whereNotNull('secret')->count());
        $this->assertSame(2, DB::table('allowed_records')->whereNull('secret')->count());
    }

    public function test_save_produces_its_own_code_and_identical_retry_is_a_noop(): void
    {
        $this->seedRows();
        $blueprint = $this->blueprint();
        $path = $this->generator()->saveSeeder($blueprint, "<?php throw new \\RuntimeException('CLIENT_CODE_FIXTURE');");

        $this->assertSame(database_path('seeders/'.$blueprint->fileName()), $path);
        $code = File::get($path);
        $this->assertStringNotContainsString('CLIENT_CODE_FIXTURE', $code);
        $this->assertStringNotContainsString('SECRET_FIXTURE', $code);
        $this->assertStringContainsString('GeneratedSafeRecordsSeeder', $code);
        $this->assertNotEmpty(token_get_all($code, TOKEN_PARSE));
        touch($path, 1234567890);
        clearstatcache(true, $path);
        $before = $this->projectSnapshot();
        $this->assertSame($path, $this->save($blueprint));
        clearstatcache(true, $path);
        $this->assertSame(1234567890, filemtime($path));
        $this->assertSame($before, $this->projectSnapshot());
    }

    public function test_changed_artifact_requires_another_name_and_preserves_previous_bytes(): void
    {
        $this->seedRows();
        $this->save($this->blueprint());
        DB::table('allowed_records')->where('unique_key', 'fixture-one')->update(['label' => 'Changed synthetic row']);
        $before = $this->projectSnapshot();

        $this->assertRejected(fn () => $this->save($this->blueprint()));
        $this->assertSame($before, $this->projectSnapshot());
        $path = $this->save($this->blueprint(['seederName' => 'OtherRecords']));
        $this->assertFileExists($path);
        $this->assertStringContainsString('Changed synthetic row', File::get($path));
    }

    public function test_save_refuses_manual_legacy_file_without_adopting_it(): void
    {
        $path = database_path('seeders/'.$this->blueprint()->fileName());
        File::put($path, "<?php // MANUAL_FIXTURE\n");
        $before = $this->projectSnapshot();

        $this->assertRejected(fn () => $this->generator()->saveSeeder($this->blueprint(), '<?php // untrusted replacement'));
        $this->assertSame($before, $this->projectSnapshot());
    }

    #[DataProvider('unsafeDestinations')]
    public function test_save_refuses_redirected_linked_or_case_colliding_destinations(string $kind): void
    {
        $outside = $this->testEnvironment->path.'/seeder-outside';
        File::ensureDirectoryExists($outside);
        $sentinel = $outside.'/sentinel.php';
        File::put($sentinel, 'OUTSIDE_FIXTURE');
        $destination = database_path('seeders/'.$this->blueprint()->fileName());
        $linkPath = null;
        $originalDatabase = database_path();

        if ($kind === 'database redirect') {
            $this->app->useDatabasePath($outside);
        } elseif ($kind === 'case collision') {
            File::put(database_path('seeders/'.strtolower($this->blueprint()->fileName())), 'CASE_FIXTURE');
        } elseif ($kind === 'hardlink') {
            if (! @link($sentinel, $destination)) {
                $this->markTestSkipped('Native hardlinks are unavailable; Linux proof is required.');
            }
            $linkPath = $destination;
        } else {
            $linkPath = $kind === 'directory symlink' ? database_path('seeders') : $destination;
            if ($kind === 'directory symlink') {
                rmdir($linkPath);
            }
            if (! @symlink($kind === 'directory symlink' ? $outside : $sentinel, $linkPath)) {
                $this->markTestSkipped('Native symlinks are unavailable; Linux proof is required.');
            }
        }

        $before = $this->projectSnapshot();
        try {
            $this->assertRejected(fn () => $this->generator()->saveSeeder($this->blueprint(), '<?php // unsafe replacement'));
            $this->assertSame('OUTSIDE_FIXTURE', File::get($sentinel));
            $this->assertSame($before, $this->projectSnapshot());
        } finally {
            $this->app->useDatabasePath($originalDatabase);
            if ($linkPath !== null && (is_link($linkPath) || $kind === 'hardlink')) {
                unlink($linkPath);
            }
        }
    }

    public static function unsafeDestinations(): array
    {
        return [['database redirect'], ['case collision'], ['hardlink'], ['file symlink'], ['directory symlink']];
    }

    public function test_occupied_project_lock_refuses_generation_without_writes(): void
    {
        $lock = fopen(base_path('.start-kit-generator.lock'), 'c+b');
        $this->assertIsResource($lock);
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        $before = $this->projectSnapshot();
        try {
            $this->assertRejected(fn () => $this->generator()->saveSeeder($this->blueprint(), '<?php // lock bypass'));
            $this->assertSame($before, $this->projectSnapshot());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    #[DataProvider('arbitrarySeederClasses')]
    public function test_run_cannot_reach_arbitrary_legacy_or_qualified_seeders(string $class): void
    {
        File::put(database_path('seeders/GeneratedManualSeeder.php'), "<?php // MANUAL_FIXTURE\n");
        $before = $this->projectSnapshot();

        $this->assertRejected(fn () => $this->generator()->runSeeder($class));
        Artisan::shouldNotHaveReceived('call');
        $this->assertSame($before, $this->projectSnapshot());
    }

    public static function arbitrarySeederClasses(): array
    {
        return [['DatabaseSeeder'], ['UserSeeder'], ['Database\\Seeders\\GeneratedSafeRecordsSeeder'], ['GeneratedManualSeeder'], ['GeneratedMissingSeeder'], ["GeneratedSafeRecordsSeeder\n"]];
    }

    public function test_run_accepts_only_saved_current_artifact_and_does_not_force_seed(): void
    {
        $this->seedRows();
        $blueprint = $this->blueprint();
        $this->save($blueprint);
        $result = $this->generator()->runSeeder($blueprint->className());

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('Synthetic seeder output', $result['output']);
        Artisan::shouldHaveReceived('call')->once()->withArgs(function (string $command, array $arguments) use ($blueprint): bool {
            return $command === 'db:seed' && ($arguments['--class'] ?? null) === 'Database\\Seeders\\'.$blueprint->className()
                && ($arguments['--no-interaction'] ?? false) === true && ! array_key_exists('--force', $arguments);
        });
    }

    #[DataProvider('artifactInvalidations')]
    public function test_run_revalidates_provenance_projection_and_current_policy(string $change): void
    {
        $this->seedRows();
        $blueprint = $this->blueprint(['seederName' => 'Revoked'.ucfirst($change)]);
        $path = $this->save($blueprint);
        if ($change === 'body') {
            File::append($path, "\n// TAMPER_FIXTURE\n");
        } elseif ($change === 'table') {
            config(['seeder-generator.allowed_tables' => []]);
        } elseif ($change === 'projection') {
            config(['seeder-generator.allowed_tables.allowed_records.columns' => ['unique_key']]);
        } else {
            config(['seeder-generator.allowed_tables.allowed_records.operations' => ['preview', 'generate', 'execute']]);
        }
        $before = $this->projectSnapshot();

        $this->assertFalse(class_exists('Database\\Seeders\\'.$blueprint->className(), false));
        $this->assertRejected(fn () => $this->generator()->runSeeder($blueprint->className()));
        Artisan::shouldNotHaveReceived('call');
        $this->assertSame($before, $this->projectSnapshot());
    }

    public static function artifactInvalidations(): array
    {
        return [['body'], ['table'], ['projection'], ['operation']];
    }

    public function test_run_rejects_extra_php_even_with_recomputed_metadata_hash(): void
    {
        $this->seedRows();
        $blueprint = $this->blueprint(['seederName' => 'ForgedFixture']);
        $path = $this->save($blueprint);
        $code = File::get($path);
        $this->assertSame(1, preg_match('/^\/\/ @start-kit-seeder:([A-Za-z0-9+\/=]+)\R/m', $code, $matches));
        $metadata = json_decode(base64_decode($matches[1], true), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($metadata);
        $body = str_replace($matches[0], '', $code)."\nthrow new \\RuntimeException('FORGED_FIXTURE');\n";
        $metadata['sha256'] = hash('sha256', $body);
        $marker = '// @start-kit-seeder:'.base64_encode(json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."\n";
        $forged = preg_replace_callback('/\A<\?php\R/', fn (array $match): string => $match[0].$marker, $body, 1, $count);
        $this->assertSame(1, $count);
        File::put($path, $forged);
        $before = $this->projectSnapshot();

        $this->assertFalse(class_exists('Database\\Seeders\\'.$blueprint->className(), false));
        $this->assertRejected(fn () => $this->generator()->runSeeder($blueprint->className()));
        Artisan::shouldNotHaveReceived('call');
        $this->assertSame($before, $this->projectSnapshot());
    }

    public function test_run_refuses_a_class_already_loaded_from_another_file(): void
    {
        $blueprint = $this->blueprint(['seederName' => 'LoadedFixture']);
        $this->save($blueprint);
        $class = 'Database\\Seeders\\'.$blueprint->className();
        $this->assertFalse(class_exists($class, false));
        $this->assertTrue(class_alias(AllowedRecord::class, $class));

        $this->assertRejected(fn () => $this->generator()->runSeeder($blueprint->className()));
        Artisan::shouldNotHaveReceived('call');
    }

    private function artifactDescriptor(SeederBlueprint $blueprint): array
    {
        $code = $this->generator()->generateSeederCode($blueprint);
        $this->assertSame(1, preg_match('/^\/\/ @start-kit-seeder:([A-Za-z0-9+\/=]+)\R/m', $code, $matches));
        $descriptor = json_decode(base64_decode($matches[1], true), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($descriptor);
        unset($descriptor['sha256']);

        return $descriptor;
    }

    public function test_generated_runtime_rechecks_live_policy_before_dml(): void
    {
        $this->seedRows();
        $descriptor = $this->artifactDescriptor($this->blueprint(['seederName' => 'RuntimeRevoked']));
        $before = $this->rows();
        config(['seeder-generator.allowed_tables' => []]);

        $this->assertRejected(fn () => $this->generator()->executeGenerated($descriptor));
        $this->assertSame($before, $this->rows());
        Artisan::shouldNotHaveReceived('call');
    }

    public function test_generated_runtime_uses_immutable_snapshot_with_current_transaction_policy(): void
    {
        $this->seedRows();
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        $descriptor = $this->artifactDescriptor($this->blueprint(['truncate' => true, 'seederName' => 'RuntimeSnapshot']));
        $this->assertSame(['label', 'unique_key'], array_keys($descriptor['rows'][0]));
        DB::table('allowed_records')->update(['label' => 'Changed live data']);

        $this->generator()->executeGenerated($descriptor);

        $this->assertSame(['Synthetic one', 'Synthetic two'], DB::table('allowed_records')->orderBy('unique_key')->pluck('label')->all());
        $this->assertSame([null, null], DB::table('allowed_records')->orderBy('unique_key')->pluck('secret')->all());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_execution_refuses_disabled_foreign_keys_before_mutation(): void
    {
        $this->seedRows();
        $before = $this->rows();
        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            $this->assertRejected(fn () => $this->generator()->executeDirectly($this->blueprint()));
            $this->assertSame($before, $this->rows());
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    public function test_invalid_utf8_is_refused_without_replacement_or_saved_artifact(): void
    {
        $this->seedRows("Invalid fixture \xFF");
        $before = $this->projectSnapshot();

        $this->assertRejected(fn () => $this->generator()->generateSeederCode($this->blueprint()));
        $this->assertSame($before, $this->projectSnapshot());
        $this->assertSame("Invalid fixture \xFF", DB::table('allowed_records')->where('unique_key', 'fixture-one')->value('label'));
    }

    public function test_partial_write_failure_cleans_owned_staging_and_retry_succeeds(): void
    {
        $this->seedRows();
        $files = new class(base_path()) extends Filesystem
        {
            private bool $failOnce = true;

            public function __construct(private string $root) {}

            private function assertOwnedPath(string $path): void
            {
                if (! str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $this->root).'/')) {
                    throw new RuntimeException('Injected filesystem refuses writes outside the owned fixture.');
                }
            }

            public function replace($path, $content, $mode = null): void
            {
                $this->assertOwnedPath($path);
                if ($this->failOnce) {
                    $this->failOnce = false;
                    parent::replace($path, substr($content, 0, 20), $mode);
                    $this->put(dirname($path).'/residue.tmp', 'PRIVATE_CODE_FIXTURE');
                    throw new RuntimeException('Injected partial replacement.');
                }
                parent::replace($path, $content, $mode);
            }

            public function put($path, $contents, $lock = false)
            {
                $this->assertOwnedPath($path);

                return parent::put($path, $contents, $lock);
            }

            public function move($path, $target)
            {
                $this->assertOwnedPath($path);
                $this->assertOwnedPath($target);

                return parent::move($path, $target);
            }
        };
        $this->app->instance(Filesystem::class, $files);
        $before = $this->projectSnapshot();

        $this->assertRejected(fn () => $this->save($this->blueprint()), RuntimeException::class);
        $this->assertSame($before, $this->projectSnapshot());
        $this->assertSame([], glob(database_path('seeders/.start-kit-seeder-*')));
        $path = $this->save($this->blueprint());
        $this->assertFileExists($path);
        $this->assertSame([], glob(database_path('seeders/.start-kit-seeder-*')));
    }

    public function test_failed_publication_cannot_delete_a_foreign_file_created_at_destination(): void
    {
        $files = new class(base_path()) extends Filesystem
        {
            public function __construct(private string $root) {}

            public function move($path, $target)
            {
                foreach ([$path, $target] as $candidate) {
                    if (! str_starts_with(str_replace('\\', '/', $candidate), str_replace('\\', '/', $this->root).'/')) {
                        throw new RuntimeException('Injected publication refuses paths outside the owned fixture.');
                    }
                }
                parent::put($target, 'MANUAL_CONCURRENT_FIXTURE');

                return false;
            }
        };
        $this->app->instance(Filesystem::class, $files);
        $path = database_path('seeders/'.$this->blueprint()->fileName());

        $this->assertRejected(fn () => $this->save($this->blueprint()), RuntimeException::class);
        $this->assertFileExists($path);
        $this->assertSame('MANUAL_CONCURRENT_FIXTURE', File::get($path));
        $this->assertSame([], glob(database_path('seeders/.start-kit-seeder-*')));
    }

    public function test_post_publication_failure_recovers_its_receipt_and_allows_retry(): void
    {
        $this->seedRows();
        $path = database_path('seeders/'.$this->blueprint()->fileName());
        $files = new class(base_path(), $path) extends Filesystem
        {
            private bool $failOnce = true;

            public function __construct(private string $root, private string $target) {}

            public function get($path, $lock = false)
            {
                if (! str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $this->root).'/')) {
                    throw new RuntimeException('Injected filesystem refuses reads outside the owned fixture.');
                }
                if ($path === $this->target && $this->failOnce && is_file($path)) {
                    $this->failOnce = false;
                    throw new RuntimeException('Injected post-publication read failure.');
                }

                return parent::get($path, $lock);
            }
        };
        $this->app->instance(Filesystem::class, $files);
        $before = $this->projectSnapshot();

        $this->assertRejected(fn () => $this->save($this->blueprint()), RuntimeException::class);
        $this->assertSame($before, $this->projectSnapshot());
        $this->assertFileDoesNotExist($path);
        $this->assertSame([], glob(database_path('seeders/.start-kit-seeder-*')));
        $this->assertSame($path, $this->save($this->blueprint()));
        $this->assertFileExists($path);
        $this->assertStringContainsString('GeneratedSafeRecordsSeeder', File::get($path));
        $this->assertSame([], glob(database_path('seeders/.start-kit-seeder-*')));
    }
}
