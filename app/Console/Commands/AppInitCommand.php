<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class AppInitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:init
        {--skip-storage-link : Do not create the public storage symlink}
        {--force-storage-link : Recreate the public storage symlink if it already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare the local start kit environment.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->createEnvironmentFile();
            $this->createPrivateConfigFile();
            $this->generateApplicationKey();
            $this->createStorageLink();
            $this->createSqliteDatabase();
        } catch (Throwable) {
            $this->components->error('Initialization failed. Check file permissions, application key configuration, and storage links.');

            return self::FAILURE;
        }

        $this->components->info('Start kit initialized.');

        return self::SUCCESS;
    }

    private function createEnvironmentFile(): void
    {
        $environmentPath = base_path('.env');

        if (File::exists($environmentPath)) {
            $this->components->twoColumnDetail('.env', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        $template = File::get(base_path('.env.example'));
        if (! File::copy(base_path('.env.example'), $environmentPath) || File::get($environmentPath) !== $template) {
            throw new RuntimeException('Unable to create the environment file.');
        }

        $this->components->twoColumnDetail('.env', '<fg=green;options=bold>CREATED</>');
    }

    private function createPrivateConfigFile(): void
    {
        $configPath = base_path('.config');

        if (File::exists($configPath)) {
            $this->components->twoColumnDetail('.config', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        $this->writeFile($configPath, $this->privateConfigTemplate());

        $this->components->twoColumnDetail('.config', '<fg=green;options=bold>CREATED</>');
    }

    private function generateApplicationKey(): void
    {
        $environment = File::get(base_path('.env'));
        $values = $this->parseEnvironment($environment);
        $key = $values['APP_KEY'] ?? '';

        if (trim($key) !== '') {
            $this->components->twoColumnDetail('APP_KEY', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        $configuredKey = config('app.key');
        if ($configuredKey !== null && $configuredKey !== '') {
            if (! is_string($configuredKey) || ! $this->validApplicationKey($configuredKey)) {
                throw new RuntimeException('The configured application key is invalid.');
            }

            $this->components->twoColumnDetail('APP_KEY', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        $normalized = preg_replace('/^[\t ]*(?:export[\t ]+)?APP_KEY[\t ]*=[^\r\n]*(?:\r\n|\r|\n|$)/m', 'APP_KEY='.PHP_EOL, $environment, -1, $replacements);
        if ($normalized === null) {
            throw new RuntimeException('Unable to prepare the application key entry.');
        }
        if ($replacements === 0) {
            $normalized = rtrim($normalized, "\r\n").PHP_EOL.'APP_KEY='.PHP_EOL;
        }
        $normalizedValues = $this->parseEnvironment($normalized);
        unset($values['APP_KEY'], $normalizedValues['APP_KEY']);
        if ($values !== $normalizedValues) {
            throw new RuntimeException('Unable to safely prepare the application key entry.');
        }
        if ($normalized !== $environment) {
            $this->writeFile(base_path('.env'), $normalized);
        }

        if ($this->call('key:generate', ['--no-interaction' => true]) !== self::SUCCESS) {
            throw new RuntimeException('Application key generation failed.');
        }

        $persistedKey = $this->parseEnvironment(File::get(base_path('.env')))['APP_KEY'] ?? '';
        if (! $this->validApplicationKey($persistedKey)) {
            throw new RuntimeException('A valid application key was not persisted.');
        }

        config(['app.key' => $persistedKey]);
    }

    /** @return array<string, string|null> */
    private function parseEnvironment(string $environment): array
    {
        $probe = 'APP_INIT_DOTENV_COMPLETION_CHECK';
        while (str_contains($environment, $probe)) {
            $probe .= '_';
        }

        $values = Dotenv::parse($environment.PHP_EOL.$probe.'=ready'.PHP_EOL);
        if (($values[$probe] ?? null) !== 'ready') {
            throw new RuntimeException('The environment file is incomplete.');
        }
        unset($values[$probe]);

        return $values;
    }

    private function validApplicationKey(string $key): bool
    {
        $cipher = config('app.cipher');
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        return is_string($cipher) && is_string($decoded) && Encrypter::supported($decoded, $cipher);
    }

    private function createStorageLink(): void
    {
        if ($this->option('skip-storage-link')) {
            $this->components->twoColumnDetail('storage:link', '<fg=yellow;options=bold>SKIPPED</>');

            return;
        }

        $links = config('filesystems.links') ?? [public_path('storage') => storage_path('app/public')];
        if (! is_array($links)) {
            throw new RuntimeException('Invalid storage link configuration.');
        }
        if ($links === []) {
            $this->components->twoColumnDetail('storage:link', '<fg=yellow;options=bold>SKIPPED</>');

            return;
        }

        $allLinked = true;
        foreach ($links as $link => $target) {
            if (! is_string($link) || ! is_string($target)) {
                throw new RuntimeException('Invalid storage link configuration.');
            }
            if (! $this->storageLinkMatchesTarget($link, $target)) {
                $allLinked = false;
            }
        }
        if ($allLinked && ! $this->option('force-storage-link')) {
            $this->components->twoColumnDetail('storage:link', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        if ($this->call('storage:link', [
            '--force' => (bool) $this->option('force-storage-link'),
            '--no-interaction' => true,
        ]) !== self::SUCCESS) {
            throw new RuntimeException('Storage link creation failed.');
        }

        foreach ($links as $link => $target) {
            if (! $this->storageLinkMatchesTarget($link, $target)) {
                throw new RuntimeException('A configured storage link is unavailable.');
            }
        }
    }

    private function storageLinkMatchesTarget(string $link, string $target): bool
    {
        clearstatcache(true, $link);
        clearstatcache(true, $target);
        $resolvedTarget = realpath($target);
        if ($resolvedTarget !== false && realpath($link) === $resolvedTarget) {
            return true;
        }
        if (! file_exists($link) || ! file_exists($target)) {
            return false;
        }

        $linkStat = stat($link);
        $targetStat = stat($target);

        return $linkStat !== false && $targetStat !== false
            && $linkStat['dev'] >= 0
            && $linkStat['dev'] === $targetStat['dev']
            && $linkStat['ino'] > 0
            && $linkStat['ino'] === $targetStat['ino'];
    }

    private function createSqliteDatabase(): void
    {
        $databasePath = database_path('database.sqlite');

        if (File::exists($databasePath)) {
            $this->components->twoColumnDetail('database/database.sqlite', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        $this->writeFile($databasePath, '');

        $this->components->twoColumnDetail('database/database.sqlite', '<fg=green;options=bold>CREATED</>');
    }

    private function writeFile(string $path, string $contents): void
    {
        if (File::put($path, $contents) !== strlen($contents) || File::get($path) !== $contents) {
            throw new RuntimeException('Unable to write an initialization file.');
        }
    }

    private function privateConfigTemplate(): string
    {
        return <<<'CONFIG'
# Configuracoes privadas do projeto
#
# Este arquivo e local e esta ignorado pelo Git.
# Use para guardar anotacoes sensiveis, credenciais e comandos recorrentes.

[servidores]
producao_host=
producao_usuario=
producao_porta=22
producao_caminho=

homologacao_host=
homologacao_usuario=
homologacao_porta=22
homologacao_caminho=

[banco_de_dados]
local_connection=sqlite
local_database=database/database.sqlite
local_username=
local_password=

producao_connection=mysql
producao_host=
producao_porta=3306
producao_database=
producao_username=
producao_password=

[laravel_comandos]
dev=composer run dev
serve=php artisan serve
vite=npm run dev
build=npm run build
test=php artisan test --compact
test_arquivo=php artisan test --compact tests/Feature/ExampleTest.php
pint=vendor/bin/pint --dirty --format agent
routes=php artisan route:list --except-vendor
migrate=php artisan migrate
migrate_fresh=php artisan migrate:fresh --seed
cache_clear=php artisan optimize:clear
queue=php artisan queue:listen --timeout=960 --tries=3 --backoff=30 --sleep=1
pail=php artisan pail
telescope=php artisan telescope:clear
pulse_check=php artisan pulse:check
wayfinder=php artisan wayfinder:generate

[deploy_anotacoes]
branch=
comando_ssh=
comando_deploy=
observacoes=

CONFIG;
    }
}
