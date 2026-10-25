<?php

declare(strict_types=1);

namespace App\Modules\SeederGenerator\Services;

use App\Modules\SeederGenerator\DTOs\SeederBlueprint;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

class SeederGeneratorService
{
    public function __construct(private Filesystem $files) {}

    /** @return array<int, array<string, mixed>> */
    public function getTables(): array
    {
        $this->assertEnvironment();
        $allowed = config('seeder-generator.allowed_tables', []);

        if (! is_array($allowed) || $allowed === []) {
            return [];
        }

        $tables = [];
        foreach (array_keys($allowed) as $table) {
            if (! is_string($table)) {
                $this->refuse('table', 'Invalid table policy.');
            }
            $policy = $this->tablePolicy($table);
            $hasFactory = false;
            if (in_array('factory', $policy['modes'], true)) {
                try {
                    $this->trustedFactory($table, $policy);
                    $hasFactory = true;
                } catch (ValidationException) {
                    $hasFactory = false;
                }
            }
            $tables[] = [
                'name' => $table,
                'count' => DB::table($table)->count(),
                'hasModel' => ($policy['model'] ?? null) !== null,
                'hasFactory' => $hasFactory,
                'modelClass' => $policy['model'] ?? null,
                'columns' => $this->projectedColumns($table, $policy),
            ];
        }

        return $tables;
    }

    /** @return array<int, array{name: string, type: string, nullable: bool}> */
    public function getTableColumns(string $table): array
    {
        $this->assertEnvironment();

        return $this->projectedColumns($table, $this->tablePolicy($table, 'preview'));
    }

    /** @return array<int, array<string, mixed>> */
    public function getSampleData(string $table, int $limit): array
    {
        $this->assertEnvironment();
        $this->assertLimit($limit);
        $policy = $this->tablePolicy($table, 'preview', 'data');

        return $this->sampleRows($table, $limit, $policy);
    }

    public function generateSeederCode(SeederBlueprint $blueprint): string
    {
        $this->assertEnvironment();
        $policy = $this->validateBlueprint($blueprint, 'preview');

        return $this->artifact($this->descriptor($blueprint, $policy));
    }

    public function saveSeeder(SeederBlueprint $blueprint): string
    {
        $this->assertEnvironment();

        return $this->withProjectLock(function () use ($blueprint): string {
            $policy = $this->validateBlueprint($blueprint, 'generate');
            $path = $this->seederPath($blueprint->className());
            $code = $this->artifact($this->descriptor($blueprint, $policy));

            if ($this->files->exists($path)) {
                $existing = $this->files->get($path);
                $this->readArtifact($existing, $blueprint->className(), 'generate');
                if ($existing !== $code) {
                    $this->refuse('seeder_name', 'An existing artifact cannot be changed. Choose another seeder name.');
                }

                return $path;
            }

            $parentExisted = $this->directoryExists(dirname($path));
            $this->files->ensureDirectoryExists(dirname($path));
            try {
                $this->writeArtifact($path, $code);
            } catch (Throwable $error) {
                $this->assertDestination($path);
                if (! $parentExisted && $this->directoryExists(dirname($path)) && scandir(dirname($path)) === ['.', '..']) {
                    rmdir(dirname($path));
                }

                throw new RuntimeException('Unable to save the generated seeder.', 0, $error);
            }

            return $path;
        });
    }

    /** @return array{success: bool, message: string, records: int, exit_code: int} */
    public function executeDirectly(SeederBlueprint $blueprint): array
    {
        $this->assertEnvironment();

        return $this->withProjectLock(function () use ($blueprint): array {
            $policy = $this->validateBlueprint($blueprint, 'execute');
            $this->assertTransactionalTable($blueprint->table);

            return $this->execute($blueprint, $policy);
        });
    }

    /** @return array{output: string, exit_code: int} */
    public function runSeeder(string $seederClass): array
    {
        $this->assertEnvironment();
        $class = $this->withProjectLock(function () use ($seederClass): string {
            $path = $this->seederPath($seederClass);
            if (! $this->files->isFile($path)) {
                $this->refuse('class', 'The generated seeder does not exist.');
            }
            $contents = $this->files->get($path);
            [$blueprint] = $this->readArtifact($contents, $seederClass, 'run');
            $this->assertTransactionalTable($blueprint->table);
            $class = 'Database\\Seeders\\'.$seederClass;
            if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
                $this->refuse('class', 'The seeder class is already loaded. Use a fresh process.');
            }
            $this->assertDestination($path);
            if ($this->files->get($path) !== $contents) {
                $this->refuse('class', 'The generated seeder changed during validation.');
            }

            require $path;

            return $class;
        });

        $exitCode = Artisan::call('db:seed', ['--class' => $class, '--no-interaction' => true]);

        return ['output' => Artisan::output(), 'exit_code' => $exitCode];
    }

    /** @param array<string, mixed> $descriptor */
    public function executeGenerated(array $descriptor): void
    {
        $this->assertEnvironment();
        $this->withProjectLock(function () use ($descriptor): void {
            [$blueprint, $policy] = $this->validateDescriptor($descriptor, 'run');
            $this->assertTransactionalTable($blueprint->table);
            $result = $this->execute($blueprint, $policy, $descriptor['rows']);
            if (! $result['success']) {
                throw new RuntimeException($result['message']);
            }
        });
    }

    public function findModelForTable(string $table): ?string
    {
        $this->assertEnvironment();
        $policy = $this->tablePolicy($table);

        return $policy['model'] ?? null;
    }

    public function hasFactory(string $modelClass): bool
    {
        $this->assertEnvironment();
        $allowed = config('seeder-generator.allowed_tables', []);
        if (! is_array($allowed)) {
            return false;
        }
        foreach ($allowed as $table => $entry) {
            if (is_string($table) && is_array($entry) && ($entry['model'] ?? null) === $modelClass) {
                $policy = $this->tablePolicy($table, null, 'factory');
                $this->trustedFactory($table, $policy);

                return true;
            }
        }

        return false;
    }

    private function assertEnvironment(): void
    {
        $environment = app()->environment();
        if (! in_array($environment, ['local', 'testing'], true) || config('app.env') !== $environment) {
            throw new AuthorizationException('Seeder Generator is available only in coherent local/testing environments.');
        }
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > 1000) {
            $this->refuse('limit', 'The record limit must be between 1 and 1000.');
        }
    }

    private function refuse(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function identifier(string $value): bool
    {
        return strlen($value) <= 64 && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $value) === 1;
    }

    private function protectedTable(string $table): bool
    {
        $table = strtolower($table);

        return in_array($table, [
            'users', 'roles', 'permissions', 'role_user', 'permission_user', 'permission_role',
            'user_roles', 'user_permissions', 'role_permissions', 'users_roles', 'users_permissions', 'roles_permissions', 'migrations',
            'password_reset_tokens', 'personal_access_tokens', 'external_api_connections',
            'app_settings', 'activity_log', 'notifications', 'files', 'attachments', 'media',
            'uploads', 'uploadeds', 'exports', 'database_exports', 'backup_histories', 'zip_requests',
            'email_webhook_events', 'rich_texts', 'timeline_comments', 'sessions', 'jobs', 'job_batches',
            'failed_jobs', 'cache', 'cache_locks',
        ], true) || preg_match('/(?:\A|_)(?:tokens?|passwords?|credentials?|settings?|logs?|sessions?|jobs?|cache|notifications?|attachments?|uploads?|exports?|telescope|pulse)(?:_|\z)/', $table) === 1;
    }

    private function sensitiveColumn(string $column): bool
    {
        return preg_match('/(?:password|secret|token|credential|two_factor|remember_token)/i', $column) === 1
            || preg_match('/(?:\A|_)(?:api|access|private|signing|encryption)_?key(?:_|\z)/i', $column) === 1
            || in_array(strtolower($column), ['webhook_url', 'webhook_urls', 'body', 'content', 'payload', 'metadata', 'value', 'values', 'data', 'properties', 'variables', 'settings', 'config', 'configuration', 'headers', 'request', 'response'], true);
    }

    /** @return array<string, mixed> */
    private function tablePolicy(string $table, ?string $operation = null, ?string $mode = null): array
    {
        if (! $this->identifier($table) || $this->protectedTable($table)) {
            $this->refuse('table', 'This table is not available to Seeder Generator.');
        }
        $allowed = config('seeder-generator.allowed_tables', []);
        $policy = is_array($allowed) ? ($allowed[$table] ?? null) : null;
        if (! is_array($policy)) {
            $this->refuse('table', 'The table has no explicit Seeder Generator policy.');
        }

        foreach (['columns', 'unique_columns', 'modes', 'operations'] as $key) {
            $values = $policy[$key] ?? null;
            if (! is_array($values) || ! array_is_list($values) || ($key !== 'unique_columns' && $values === [])) {
                $this->refuse('table', 'The table policy must declare explicit columns, modes and operations.');
            }
            foreach ($values as $value) {
                if (! is_string($value)) {
                    $this->refuse('table', 'The table policy contains an invalid value.');
                }
            }
            if (count(array_unique($values)) !== count($values)) {
                $this->refuse('table', 'The table policy contains duplicate values.');
            }
        }
        if (array_diff($policy['modes'], ['data', 'factory']) !== [] || array_diff($policy['operations'], ['preview', 'generate', 'execute', 'run']) !== []) {
            $this->refuse('table', 'The table policy contains unsupported capabilities.');
        }
        if ($operation !== null && ! in_array($operation, $policy['operations'], true)) {
            $this->refuse('table', 'The operation is not enabled for this table.');
        }
        if ($mode !== null && ! in_array($mode, $policy['modes'], true)) {
            $this->refuse('mode', 'The mode is not enabled for this table.');
        }

        foreach ($policy['columns'] as $column) {
            if (! $this->identifier($column) || $this->sensitiveColumn($column)) {
                $this->refuse('table', 'The policy includes a sensitive or invalid column.');
            }
        }
        foreach ($policy['unique_columns'] as $column) {
            if (! in_array($column, $policy['columns'], true)) {
                $this->refuse('unique_column', 'Unique columns must belong to the authorized projection.');
            }
        }

        $model = $this->configuredModel($table, $policy);
        if ($model !== null) {
            foreach ($policy['columns'] as $column) {
                $cast = $model->getCasts()[$column] ?? '';
                if (in_array($column, $model->getHidden(), true) || (is_string($cast) && str_starts_with($cast, 'encrypted'))) {
                    $this->refuse('table', 'Hidden or encrypted model columns cannot be exported.');
                }
            }
        }
        $schemaColumns = array_column(Schema::getColumns($table), null, 'name');
        if ($schemaColumns === [] || array_diff($policy['columns'], array_keys($schemaColumns)) !== []) {
            $this->refuse('table', 'The authorized projection does not match the table schema.');
        }

        return $policy;
    }

    /** @param array<string, mixed> $policy */
    private function configuredModel(string $table, array $policy): ?Model
    {
        $class = $policy['model'] ?? null;
        if ($class === null) {
            return null;
        }
        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            $this->refuse('mode', 'The configured model is invalid.');
        }
        $model = new $class;
        if ($model->getTable() !== $table || ($model->getConnectionName() ?? DB::getDefaultConnection()) !== DB::getDefaultConnection()) {
            $this->refuse('mode', 'The configured model must use the authorized table and connection.');
        }

        return $model;
    }

    /** @param array<string, mixed> $policy */
    private function trustedFactory(string $table, array $policy): Factory
    {
        $class = $policy['factory'] ?? null;
        $model = $this->configuredModel($table, $policy);
        if (($policy['factory_trusted'] ?? false) !== true || $model === null || ! is_string($class) || ! is_subclass_of($class, Factory::class)) {
            $this->refuse('mode', 'Factory generation requires an explicit trusted model and factory mapping.');
        }
        $factory = $class::new();
        if ($factory->modelName() !== $model::class || ! in_array($factory->getConnectionName(), [null, DB::getDefaultConnection()], true)) {
            $this->refuse('mode', 'The configured factory must use the authorized model and connection.');
        }

        return $factory;
    }

    /** @return array<string, mixed> */
    private function validateBlueprint(SeederBlueprint $blueprint, string $operation): array
    {
        $this->assertLimit($blueprint->limit);
        if (! in_array($blueprint->mode, ['data', 'factory'], true)) {
            $this->refuse('mode', 'Unsupported seeder mode.');
        }
        if (strlen($blueprint->seederName) > 64 || preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/', $blueprint->seederName) !== 1) {
            $this->refuse('seeder_name', 'Invalid seeder name.');
        }
        $policy = $this->tablePolicy($blueprint->table, $operation, $blueprint->mode);
        if ($blueprint->mode === 'factory') {
            $this->trustedFactory($blueprint->table, $policy);

            return $policy;
        }
        if ($blueprint->truncate && ($policy['truncate'] ?? false) !== true) {
            $this->refuse('truncate', 'Transactional deletion is not enabled for this table.');
        }
        if ($blueprint->idempotent) {
            $column = $blueprint->uniqueColumn;
            if ($column === null || ! in_array($column, $policy['unique_columns'], true)) {
                $this->refuse('unique_column', 'Choose an explicitly authorized unique column.');
            }
            $columns = array_column(Schema::getColumns($blueprint->table), null, 'name');
            $validIndex = false;
            foreach (Schema::getIndexes($blueprint->table) as $index) {
                if (($index['unique'] ?? false) && ($index['columns'] ?? []) === [$column]) {
                    $validIndex = true;
                }
            }
            if (! $validIndex || ($columns[$column]['nullable'] ?? true)) {
                $this->refuse('unique_column', 'Idempotence requires a simple nonnullable unique constraint.');
            }
        }

        return $policy;
    }

    /** @param array<string, mixed> $policy
     * @return array<int, array{name: string, type: string, nullable: bool}>
     */
    private function projectedColumns(string $table, array $policy): array
    {
        $columns = array_column(Schema::getColumns($table), null, 'name');
        $result = [];
        foreach ($policy['columns'] as $name) {
            $column = $columns[$name];
            $result[] = ['name' => $name, 'type' => $column['type_name'] ?? $column['type'] ?? 'string', 'nullable' => (bool) $column['nullable']];
        }

        return $result;
    }

    /** @param array<string, mixed> $policy
     * @return array<int, array<string, mixed>>
     */
    private function sampleRows(string $table, int $limit, array $policy, bool $requireComplete = false): array
    {
        $rows = DB::table($table)->select($policy['columns'])->orderBy($policy['columns'][0])->limit($limit + ($requireComplete ? 1 : 0))->get()
            ->map(fn (object $row): array => (array) $row)->all();

        if ($requireComplete && count($rows) > $limit) {
            $this->refuse('truncate', 'A limited sample cannot replace the whole table. Increase the limit to cover every authorized row or disable replacement.');
        }

        $this->validateRows($rows, $policy['columns'], $limit);

        return $rows;
    }

    /** @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private function descriptor(SeederBlueprint $blueprint, array $policy): array
    {
        return [
            'version' => 1,
            'blueprint' => [
                'table' => $blueprint->table, 'mode' => $blueprint->mode, 'limit' => $blueprint->limit,
                'truncate' => $blueprint->mode === 'data' && $blueprint->truncate,
                'idempotent' => $blueprint->mode === 'data' && $blueprint->idempotent,
                'unique_column' => $blueprint->mode === 'data' && $blueprint->idempotent ? $blueprint->uniqueColumn : null,
                'seeder_name' => $blueprint->seederName,
            ],
            'columns' => $policy['columns'],
            'rows' => $blueprint->mode === 'data' ? $this->sampleRows($blueprint->table, $blueprint->limit, $policy, $blueprint->truncate) : [],
        ];
    }

    /** @param array<string, mixed> $descriptor */
    private function artifact(array $descriptor): string
    {
        $blueprint = SeederBlueprint::fromArray($descriptor['blueprint']);
        $payload = var_export($descriptor, true);
        $class = $blueprint->className();
        $service = self::class;
        $body = "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\n\nclass {$class} extends Seeder\n{\n    public function run(): void\n    {\n        app(\\{$service}::class)->executeGenerated({$payload});\n    }\n}\n";
        $metadata = $descriptor + ['sha256' => hash('sha256', $body)];
        try {
            $json = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException) {
            $this->refuse('generation', 'The selected data cannot be serialized safely.');
        }
        $contents = "<?php\n// @start-kit-seeder:".base64_encode($json)."\n".substr($body, strlen("<?php\n"));
        if (token_get_all($contents, TOKEN_PARSE) === []) {
            $this->refuse('generation', 'The generated seeder is invalid.');
        }

        return $contents;
    }

    /** @return array{SeederBlueprint, array<string, mixed>} */
    private function readArtifact(string $contents, string $class, string $operation): array
    {
        if (preg_match('/\A<\?php\n\/\/ @start-kit-seeder:([A-Za-z0-9+\/=]+)\n/', $contents, $match) !== 1) {
            $this->refuse('class', 'The file is not a recognized generated seeder.');
        }
        $json = base64_decode($match[1], true);
        try {
            $metadata = $json === false ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->refuse('class', 'The generated seeder metadata is invalid.');
        }
        if (! is_array($metadata) || ! is_string($metadata['sha256'] ?? null)) {
            $this->refuse('class', 'The generated seeder metadata is invalid.');
        }
        $hash = $metadata['sha256'];
        unset($metadata['sha256']);
        [$blueprint, $policy] = $this->validateDescriptor($metadata, $operation);
        $body = "<?php\n".substr($contents, strlen($match[0]));
        if ($blueprint->className() !== $class || ! hash_equals(hash('sha256', $body), $hash) || $this->artifact($metadata) !== $contents) {
            $this->refuse('class', 'The generated seeder content does not match its canonical declaration.');
        }

        return [$blueprint, $policy];
    }

    /** @param array<string, mixed> $descriptor
     * @return array{SeederBlueprint, array<string, mixed>}
     */
    private function validateDescriptor(array $descriptor, string $operation): array
    {
        if (array_keys($descriptor) !== ['version', 'blueprint', 'columns', 'rows'] || $descriptor['version'] !== 1 || ! is_array($descriptor['blueprint'])) {
            $this->refuse('class', 'Unsupported generated seeder descriptor.');
        }
        $data = $descriptor['blueprint'];
        if (array_keys($data) !== ['table', 'mode', 'limit', 'truncate', 'idempotent', 'unique_column', 'seeder_name']
            || ! is_string($data['table']) || ! is_string($data['mode']) || ! is_int($data['limit'])
            || ! is_bool($data['truncate']) || ! is_bool($data['idempotent'])
            || (! is_string($data['unique_column']) && $data['unique_column'] !== null) || ! is_string($data['seeder_name'])) {
            $this->refuse('class', 'Invalid generated seeder blueprint.');
        }
        $blueprint = SeederBlueprint::fromArray($data);
        $policy = $this->validateBlueprint($blueprint, $operation);
        if ($descriptor['columns'] !== $policy['columns']) {
            $this->refuse('class', 'The current policy no longer authorizes this artifact projection.');
        }
        $this->validateRows($descriptor['rows'], $policy['columns'], $blueprint->limit);
        if ($blueprint->mode === 'factory' && ($descriptor['rows'] !== [] || $blueprint->truncate || $blueprint->idempotent || $blueprint->uniqueColumn !== null)) {
            $this->refuse('class', 'Factory artifacts cannot contain data operations.');
        }

        return [$blueprint, $policy];
    }

    /** @param array<int, string> $columns */
    private function validateRows(mixed $rows, array $columns, int $limit): void
    {
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) > $limit) {
            $this->refuse('class', 'Invalid generated seeder snapshot.');
        }
        foreach ($rows as $row) {
            if (! is_array($row) || array_keys($row) !== $columns) {
                $this->refuse('class', 'Snapshot columns must exactly match the authorized projection.');
            }
            foreach ($row as $value) {
                if ((! is_scalar($value) && $value !== null) || (is_float($value) && ! is_finite($value)) || (is_string($value) && preg_match('//u', $value) !== 1)) {
                    $this->refuse('class', 'Snapshot data cannot be serialized safely.');
                }
            }
        }
    }

    private function assertTransactionalTable(string $table): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            if ((int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
                $this->refuse('generation', 'Foreign-key enforcement must remain enabled.');
            }

            return;
        }
        if ($driver === 'mysql') {
            $session = DB::selectOne('SELECT @@SESSION.sql_mode AS sql_mode');
            $modes = array_map(fn (string $mode): string => strtoupper(trim($mode)), explode(',', (string) ($session->sql_mode ?? '')));
            if (array_intersect(['STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES'], $modes) === []) {
                $this->refuse('generation', 'Execution requires an effective strict MySQL session.');
            }
            foreach (Schema::getTables() as $metadata) {
                if ($metadata['name'] === $table && strcasecmp($metadata['engine'] ?? '', 'InnoDB') === 0
                    && (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled === 1) {
                    return;
                }
            }
        }
        $this->refuse('generation', 'Execution requires a supported transactional table and enabled foreign keys.');
    }

    /** @param array<string, mixed> $policy
     * @param  array<int, array<string, mixed>>|null  $snapshot
     * @return array{success: bool, message: string, records: int, exit_code: int}
     */
    private function execute(SeederBlueprint $blueprint, array $policy, ?array $snapshot = null): array
    {
        try {
            $records = DB::transaction(function () use ($blueprint, $policy, $snapshot): int {
                if ($blueprint->mode === 'factory') {
                    return $this->trustedFactory($blueprint->table, $policy)->count($blueprint->limit)->create()->count();
                }
                $rows = $snapshot ?? $this->sampleRows($blueprint->table, $blueprint->limit, $policy, $blueprint->truncate);
                if ($blueprint->truncate) {
                    if (DB::table($blueprint->table)->selectRaw('1')->limit(count($rows) + 1)->get()->count() > count($rows)) {
                        throw new RuntimeException('The replacement snapshot is smaller than the current destination.');
                    }

                    $deleted = DB::table($blueprint->table)->delete();

                    if ($deleted > count($rows)) {
                        throw new RuntimeException('The destination grew before replacement; the deletion must be rolled back.');
                    }
                }
                $inserted = 0;
                foreach ($rows as $row) {
                    if (! $blueprint->truncate && $this->alreadyPresent($blueprint, $row)) {
                        continue;
                    }
                    try {
                        if (! DB::table($blueprint->table)->insert($row)) {
                            throw new RuntimeException('The authorized row was not inserted.');
                        }
                        $inserted++;
                    } catch (UniqueConstraintViolationException $error) {
                        if ($blueprint->truncate || ! $this->alreadyPresent($blueprint, $row)) {
                            throw $error;
                        }
                    }
                }

                return $inserted;
            });

            return ['success' => true, 'message' => "{$records} registros inseridos na tabela autorizada.", 'records' => $records, 'exit_code' => 0];
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Não foi possível executar o seeder autorizado.', 'records' => 0, 'exit_code' => 1];
        }
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @phpstan-impure
     */
    private function alreadyPresent(SeederBlueprint $blueprint, array $row): bool
    {
        $query = DB::table($blueprint->table);
        if ($blueprint->idempotent && $blueprint->uniqueColumn !== null) {
            return $query->where($blueprint->uniqueColumn, $row[$blueprint->uniqueColumn])->exists();
        }
        foreach ($row as $column => $value) {
            $query->where($column, $value);
        }

        return $query->exists();
    }

    private function seederPath(string $class): string
    {
        if (preg_match('/\AGenerated[A-Za-z0-9]+Seeder\z/', $class) !== 1 || strlen($class) > 128) {
            $this->refuse('class', 'Only a generated short seeder class is accepted.');
        }
        if ($this->normalize(database_path()) !== $this->normalize(base_path('database'))) {
            $this->refuse('generation', 'The seeder destination must remain in the project database directory.');
        }
        $path = database_path('seeders/'.$class.'.php');
        $this->assertDestination($path);

        return $path;
    }

    /** @phpstan-impure */
    private function directoryExists(string $path): bool
    {
        clearstatcache(true, $path);

        return is_dir($path);
    }

    private function normalize(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }

    private function assertDestination(string $path): void
    {
        $base = $this->normalize(base_path());
        $root = realpath(base_path());
        if ($root === false || is_link(base_path()) || ! str_starts_with($this->normalize($path), $base.'/')) {
            $this->refuse('generation', 'Seeder destinations must remain inside the project.');
        }
        $parts = explode('/', substr(str_replace('\\', '/', $path), strlen($base) + 1));
        $current = base_path();
        foreach ($parts as $index => $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_contains($part, ':') || str_contains($part, "\0")) {
                $this->refuse('generation', 'Invalid seeder destination.');
            }
            if (is_dir($current)) {
                $siblings = scandir($current);
                if ($siblings === false) {
                    $this->refuse('generation', 'Unable to inspect seeder destinations.');
                }
                foreach ($siblings as $sibling) {
                    if ($sibling !== $part && strcasecmp($sibling, $part) === 0) {
                        $this->refuse('generation', 'A destination already exists with different letter casing.');
                    }
                }
            }
            $current .= DIRECTORY_SEPARATOR.$part;
            clearstatcache(true, $current);
            if (! file_exists($current) && ! is_link($current)) {
                continue;
            }
            $stat = lstat($current);
            $resolved = realpath($current);
            $isFile = $index === count($parts) - 1;
            if ($stat === false || is_link($current) || $resolved === false
                || ! str_starts_with($this->normalize($resolved), $this->normalize($root).'/')
                || ($stat['mode'] & 0170000) !== ($isFile ? 0100000 : 0040000)
                || ($isFile && $stat['nlink'] > 1)) {
                $this->refuse('generation', 'Seeder operations refuse linked, special or redirected paths.');
            }
        }
    }

    private function withProjectLock(callable $action): mixed
    {
        $this->assertEnvironment();
        $path = base_path('.start-kit-generator.lock');
        $this->assertDestination($path);
        $lock = fopen($path, 'c+b');
        if ($lock === false) {
            $this->refuse('generation', 'Unable to open the project generator lock.');
        }
        try {
            $this->assertDestination($path);
            $stat = fstat($lock);
            $named = lstat($path);
            if ($stat === false || $named === false || $stat['nlink'] !== 1 || ($stat['mode'] & 0170000) !== 0100000
                || $stat['ino'] !== $named['ino'] || $stat['dev'] !== $named['dev']) {
                $this->refuse('generation', 'The project generator lock is unsafe.');
            }
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                $this->refuse('generation', 'Another generator operation is already running for this project.');
            }

            return $action();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function writeArtifact(string $path, string $contents): void
    {
        $publication = null;
        $mode = PHP_OS_FAMILY === 'Windows' ? 0666 & ~umask() : 0600;
        $temporaryDirectory = dirname($path).'/.start-kit-seeder-'.bin2hex(random_bytes(16));
        $temporary = $temporaryDirectory.'/content';
        $this->assertDestination($temporary);
        if (! $this->files->makeDirectory($temporaryDirectory, 0700)) {
            throw new RuntimeException('Unable to create private seeder staging.');
        }
        try {
            try {
                $this->files->replace($temporary, $contents, $mode);
                $this->assertDestination($temporary);
                if (! is_file($temporary) || $this->files->get($temporary) !== $contents || (fileperms($temporary) & 0777) !== $mode) {
                    throw new RuntimeException('The seeder staging file was not written completely.');
                }
                $this->assertDestination($path);
                $staged = lstat($temporary);
                if ($this->files->exists($path) || ! $this->files->move($temporary, $path)) {
                    throw new RuntimeException('Unable to publish the generated seeder.');
                }
                $this->assertDestination($path);
                $published = lstat($path);
                if ($staged === false || $published === false || $staged['dev'] !== $published['dev'] || $staged['ino'] !== $published['ino']) {
                    throw new RuntimeException('Unable to establish ownership of the published seeder.');
                }
                $publication = ['dev' => $published['dev'], 'ino' => $published['ino'], 'sha256' => hash('sha256', $contents)];
                if ($this->files->get($path) !== $contents) {
                    throw new RuntimeException('The generated seeder bytes were not written completely.');
                }
            } finally {
                $entries = scandir($temporaryDirectory);
                if ($entries === false) {
                    throw new RuntimeException('Unable to inspect private seeder staging.');
                }
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $temporaryPath = $temporaryDirectory.'/'.$entry;
                    $this->assertDestination($temporaryPath);
                    if (! $this->files->delete($temporaryPath)) {
                        throw new RuntimeException('Unable to remove an owned seeder temporary file.');
                    }
                }
                if (! rmdir($temporaryDirectory)) {
                    throw new RuntimeException('Unable to remove private seeder staging.');
                }
            }
        } catch (Throwable $error) {
            if ($publication !== null) {
                $this->assertDestination($path);
                $current = lstat($path);
                if ($current === false || $current['dev'] !== $publication['dev'] || $current['ino'] !== $publication['ino']
                    || hash_file('sha256', $path) !== $publication['sha256'] || ! $this->files->delete($path)) {
                    throw new RuntimeException('Seeder write recovery could not safely remove its published artifact.', 0, $error);
                }
            }

            throw $error;
        }
    }
}
