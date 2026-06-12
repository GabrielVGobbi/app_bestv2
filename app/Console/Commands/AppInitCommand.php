<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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
        $this->createEnvironmentFile();
        $this->createPrivateConfigFile();
        $this->generateApplicationKey();
        $this->createStorageLink();
        $this->createSqliteDatabase();

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

        File::copy(base_path('.env.example'), $environmentPath);

        $this->components->twoColumnDetail('.env', '<fg=green;options=bold>CREATED</>');
    }

    private function createPrivateConfigFile(): void
    {
        $configPath = base_path('.config');

        if (File::exists($configPath)) {
            $this->components->twoColumnDetail('.config', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        File::put($configPath, $this->privateConfigTemplate());

        $this->components->twoColumnDetail('.config', '<fg=green;options=bold>CREATED</>');
    }

    private function generateApplicationKey(): void
    {
        $environment = File::get(base_path('.env'));

        if (preg_match('/^APP_KEY=.+$/m', $environment) === 1) {
            $this->components->twoColumnDetail('APP_KEY', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        $this->call('key:generate', ['--no-interaction' => true]);
    }

    private function createStorageLink(): void
    {
        if ($this->option('skip-storage-link')) {
            $this->components->twoColumnDetail('storage:link', '<fg=yellow;options=bold>SKIPPED</>');

            return;
        }

        if (File::exists(public_path('storage')) && ! $this->option('force-storage-link')) {
            $this->components->twoColumnDetail('storage:link', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        $this->call('storage:link', [
            '--force' => (bool) $this->option('force-storage-link'),
            '--no-interaction' => true,
        ]);
    }

    private function createSqliteDatabase(): void
    {
        $databasePath = database_path('database.sqlite');

        if (File::exists($databasePath)) {
            $this->components->twoColumnDetail('database/database.sqlite', '<fg=yellow;options=bold>EXISTS</>');

            return;
        }

        File::put($databasePath, '');

        $this->components->twoColumnDetail('database/database.sqlite', '<fg=green;options=bold>CREATED</>');
    }

    private function privateConfigTemplate(): string
    {
        return <<<'CONFIG'
# Configurações privadas do projeto
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
queue=php artisan queue:listen --tries=1
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
