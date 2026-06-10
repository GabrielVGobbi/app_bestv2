<?php

declare(strict_types=1);

namespace App\Modules\SeederGenerator\Services;

use App\Modules\SeederGenerator\DTOs\SeederBlueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeederGeneratorService
{
    /**
     * @return array<int, array{name: string, count: int, hasModel: bool, hasFactory: bool, modelClass: string|null, columns: array<int, array{name: string, type: string, nullable: bool}>}>
     */
    public function getTables(): array
    {
        $currentDb = DB::connection()->getDatabaseName();
        $excluded = ['migrations', 'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring', 'pulse_entries', 'pulse_aggregates', 'pulse_values', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'personal_access_tokens'];

        $tableNames = collect(DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [$currentDb, 'BASE TABLE']))
            ->pluck('TABLE_NAME')
            ->reject(fn (string $name) => in_array($name, $excluded, true))
            ->values();

        return $tableNames->map(function (string $name) {
            $modelClass = $this->findModelForTable($name);

            try {
                $count = DB::table($name)->count();
            } catch (\Throwable) {
                $count = 0;
            }

            try {
                $columns = $this->getTableColumns($name);
            } catch (\Throwable) {
                $columns = [];
            }

            return [
                'name' => $name,
                'count' => $count,
                'hasModel' => $modelClass !== null,
                'hasFactory' => $modelClass !== null && $this->hasFactory($modelClass),
                'modelClass' => $modelClass,
                'columns' => $columns,
            ];
        })->all();
    }

    /**
     * @return array<int, array{name: string, type: string, nullable: bool}>
     */
    public function getTableColumns(string $table): array
    {
        return collect(Schema::getColumns($table))
            ->map(fn (array $col) => [
                'name' => $col['name'],
                'type' => $col['type_name'] ?? $col['type'] ?? 'string',
                'nullable' => (bool) ($col['nullable'] ?? false),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSampleData(string $table, int $limit): array
    {
        return DB::table($table)->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
    }

    public function generateSeederCode(SeederBlueprint $blueprint): string
    {
        if ($blueprint->mode === 'factory') {
            return $this->generateFactorySeeder($blueprint);
        }

        return $this->generateDataSeeder($blueprint);
    }

    public function saveSeeder(SeederBlueprint $blueprint, string $code): string
    {
        $path = database_path('seeders/'.$blueprint->fileName());
        file_put_contents($path, $code);

        return $path;
    }

    /**
     * @return array{success: bool, message: string, records: int, exit_code: int}
     */
    public function executeDirectly(SeederBlueprint $blueprint): array
    {
        DB::beginTransaction();

        try {
            [$message, $records] = $blueprint->mode === 'factory'
                ? $this->doExecuteFactory($blueprint)
                : $this->doExecuteData($blueprint);

            DB::commit();

            return ['success' => true, 'message' => $message, 'records' => $records, 'exit_code' => 0];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage(), 'records' => 0, 'exit_code' => 1];
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function doExecuteFactory(SeederBlueprint $blueprint): array
    {
        $modelClass = $this->findModelForTable($blueprint->table);

        if ($modelClass === null) {
            throw new \RuntimeException("Nenhum model encontrado para a tabela '{$blueprint->table}'.");
        }

        if (! $this->hasFactory($modelClass)) {
            throw new \RuntimeException("Nenhuma factory encontrada para {$modelClass}.");
        }

        $modelClass::factory()->count($blueprint->limit)->create();

        return ["{$blueprint->limit} registros criados via factory em '{$blueprint->table}'.", $blueprint->limit];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function doExecuteData(SeederBlueprint $blueprint): array
    {
        $rows = $this->getSampleData($blueprint->table, $blueprint->limit);

        if (empty($rows)) {
            return ["Nenhum registro encontrado em '{$blueprint->table}'.", 0];
        }

        if ($blueprint->truncate) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table($blueprint->table)->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $inserted = 0;

        if ($blueprint->idempotent && $blueprint->uniqueColumn) {
            $col = $blueprint->uniqueColumn;
            foreach ($rows as $row) {
                if (! DB::table($blueprint->table)->where($col, $row[$col])->exists()) {
                    DB::table($blueprint->table)->insertOrIgnore([$row]);
                    $inserted++;
                }
            }
        } else {
            DB::table($blueprint->table)->insertOrIgnore($rows);
            $inserted = count($rows);
        }

        return ["{$inserted} registros inseridos em '{$blueprint->table}'.", $inserted];
    }

    /**
     * @return array{output: string, exit_code: int}
     */
    public function runSeeder(string $seederClass): array
    {
        $exitCode = Artisan::call('db:seed', [
            '--class' => $seederClass,
            '--no-interaction' => true,
            '--force' => true,
        ]);

        return [
            'output' => Artisan::output(),
            'exit_code' => $exitCode,
        ];
    }

    public function findModelForTable(string $table): ?string
    {
        $singular = Str::studly(Str::singular($table));

        $candidates = [
            ...glob(app_path('Models/*.php')) ?: [],
            ...glob(app_path('Modules/*/Models/*.php')) ?: [],
        ];

        foreach ($candidates as $file) {
            $basename = basename($file, '.php');
            if ($basename === $singular) {
                return $this->resolveClassFromFile($file);
            }
        }

        return null;
    }

    public function hasFactory(string $modelClass): bool
    {
        $short = class_basename($modelClass);
        $factoryPaths = [
            database_path("factories/{$short}Factory.php"),
            ...glob(app_path("Modules/*/Database/Factories/{$short}Factory.php")) ?: [],
        ];

        foreach ($factoryPaths as $path) {
            if (file_exists($path)) {
                return true;
            }
        }

        return false;
    }

    private function generateDataSeeder(SeederBlueprint $blueprint): string
    {
        $rows = $this->getSampleData($blueprint->table, $blueprint->limit);
        $className = $blueprint->className();
        $table = $blueprint->table;

        $dataExport = $this->exportRowsAsPhp($rows);

        $body = '';

        if ($blueprint->truncate) {
            $body .= "        DB::table('{$table}')->truncate();\n\n";
        }

        $body .= "        \$data = {$dataExport};\n\n";

        if ($blueprint->idempotent && $blueprint->uniqueColumn) {
            $col = $blueprint->uniqueColumn;
            $body .= "        foreach (\$data as \$row) {\n";
            $body .= "            DB::table('{$table}')\n";
            $body .= "                ->where('{$col}', \$row['{$col}'])\n";
            $body .= "                ->exists() ?: DB::table('{$table}')->insertOrIgnore([\$row]);\n";
            $body .= "        }\n";
        } else {
            $body .= "        DB::table('{$table}')->insertOrIgnore(\$data);\n";
        }

        return <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class {$className} extends Seeder
{
    public function run(): void
    {
{$body}    }
}
PHP;
    }

    private function generateFactorySeeder(SeederBlueprint $blueprint): string
    {
        $className = $blueprint->className();
        $modelClass = $this->findModelForTable($blueprint->table);

        if ($modelClass === null) {
            $modelClass = 'App\\Models\\'.Str::studly(Str::singular($blueprint->table));
        }

        $short = class_basename($modelClass);
        $count = $blueprint->limit;

        return <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use {$modelClass};

class {$className} extends Seeder
{
    public function run(): void
    {
        {$short}::factory()->count({$count})->create();
    }
}
PHP;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function exportRowsAsPhp(array $rows): string
    {
        if (empty($rows)) {
            return '[]';
        }

        $lines = ['['];

        foreach ($rows as $row) {
            $pairs = [];
            foreach ($row as $key => $value) {
                $exportedValue = match (true) {
                    $value === null => 'null',
                    is_bool($value) => $value ? 'true' : 'false',
                    is_int($value) || is_float($value) => (string) $value,
                    default => "'".addslashes((string) $value)."'",
                };
                $pairs[] = "'{$key}' => {$exportedValue}";
            }
            $lines[] = '            ['.implode(', ', $pairs).'],';
        }

        $lines[] = '        ]';

        return implode("\n", $lines);
    }

    private function resolveClassFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return null;
        }

        if (preg_match('/^namespace\s+([^;]+);/m', $content, $nsMatch) &&
            preg_match('/^class\s+(\w+)/m', $content, $clsMatch)) {
            return $nsMatch[1].'\\'.$clsMatch[1];
        }

        return null;
    }
}
