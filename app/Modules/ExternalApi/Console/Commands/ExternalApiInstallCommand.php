<?php

namespace App\Modules\ExternalApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ExternalApiInstallCommand extends Command
{
    protected $signature = 'external-api:install {--force : sobrescreve config/external-apis.php se ela já existir}';

    protected $description = 'Publica a configuração do módulo ExternalApi para config/external-apis.php';

    public function handle(Filesystem $files): int
    {
        $source = app_path('Modules/ExternalApi/config/external-apis.php');
        $target = config_path('external-apis.php');
        $force = (bool) $this->option('force');

        if (! $files->exists($source)) {
            $this->error("Config fonte não encontrada: {$source}");

            return self::FAILURE;
        }

        if ($files->exists($target) && ! $force) {
            $this->warn("Config já existe: {$target}");
            $this->line('Use --force para sobrescrever.');

            return self::SUCCESS;
        }

        $files->ensureDirectoryExists(dirname($target));
        $files->copy($source, $target);

        $this->info("Config publicada em: {$target}");
        $this->line('Próximos passos: configure as variáveis SPOTIFY_*, IUGU_* ou ASAAS_* e rode php artisan migrate.');

        return self::SUCCESS;
    }
}
