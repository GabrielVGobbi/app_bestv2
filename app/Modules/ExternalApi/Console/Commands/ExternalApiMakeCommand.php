<?php

namespace App\Modules\ExternalApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ExternalApiMakeCommand extends Command
{
    protected $signature = 'external-api:make
        {name : Nome do modulo, exemplo Spotify}
        {--force : Sobrescreve arquivos existentes}
        {--with-oauth : Gera marcadores de OAuth no manager}
        {--register : Registra o modulo na config fonte e publicada}';

    protected $description = 'Cria um modulo ExternalApi com provider, configuration, clients, manager, DTO, servico, mock e teste PHPUnit.';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $key = Str::kebab($name);
        $force = (bool) $this->option('force');

        $replacements = [
            '{{ namespace }}' => "App\\Modules\\ExternalApi\\Modules\\{$name}",
            '{{ test_namespace }}' => 'Tests\\Feature\\ExternalApi',
            '{{ class }}' => $name,
            '{{ key }}' => $key,
            '{{ alias }}' => Str::camel($name),
            '{{ test_method }}' => Str::snake($name),
            '{{ oauth_marker }}' => $this->option('with-oauth') ? "\n    // Add OAuth services here when this module needs user authorization.\n" : '',
        ];

        foreach ($this->files($name) as $stub => $target) {
            if ($files->exists($target) && ! $force) {
                $this->warn("Arquivo ja existe: {$target}");

                continue;
            }

            $files->ensureDirectoryExists(dirname($target));
            $files->put($target, strtr($files->get($this->stubPath($stub)), $replacements));
            $this->line("Criado: {$target}");
        }

        if ($this->option('register')) {
            $this->registerModule($files, $name, $key);
        }

        $this->info("Modulo {$name} criado.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function files(string $name): array
    {
        $base = app_path("Modules/ExternalApi/Modules/{$name}");

        return [
            'configuration.stub' => "{$base}/Configuration/{$name}Configuration.php",
            'http-client.stub' => "{$base}/Http/{$name}HttpClient.php",
            'mock-http-client.stub' => "{$base}/Http/{$name}MockHttpClient.php",
            'manager.stub' => "{$base}/Services/{$name}Manager.php",
            'sample-service.stub' => "{$base}/Services/SampleService.php",
            'sample-dto.stub' => "{$base}/DTOs/SampleResource.php",
            'mock-data.stub' => "{$base}/Mocks/{$name}MockData.php",
            'provider.stub' => "{$base}/Providers/{$name}ServiceProvider.php",
            'facade.stub' => app_path("Modules/ExternalApi/Facades/{$name}.php"),
            'feature-test.stub' => base_path("tests/Feature/ExternalApi/{$name}Test.php"),
        ];
    }

    private function stubPath(string $stub): string
    {
        return app_path("Modules/ExternalApi/stubs/{$stub}");
    }

    private function registerModule(Filesystem $files, string $name, string $key): void
    {
        foreach ([app_path('Modules/ExternalApi/config/external-apis.php'), config_path('external-apis.php')] as $path) {
            if (! $files->exists($path)) {
                continue;
            }

            $contents = $files->get($path);

            if (str_contains($contents, "'{$key}' => [")) {
                continue;
            }

            $block = $this->configBlock($name, $key);
            $needle = "    ],\n\n    'monitoring' => [";
            $contents = str_replace($needle, $block."\n    ],\n\n    'monitoring' => [", $contents);
            $files->put($path, $contents);
            $this->line("Registrado em: {$path}");
        }
    }

    private function configBlock(string $name, string $key): string
    {
        $envPrefix = Str::upper(Str::snake($name));

        return <<<PHP

        '{$key}' => [
            'enabled' => env('{$envPrefix}_ENABLED', true),
            'provider' => App\\Modules\\ExternalApi\\Modules\\{$name}\\Providers\\{$name}ServiceProvider::class,
            'manager' => App\\Modules\\ExternalApi\\Modules\\{$name}\\Services\\{$name}Manager::class,
            'configuration' => App\\Modules\\ExternalApi\\Modules\\{$name}\\Configuration\\{$name}Configuration::class,
            'http_client' => App\\Modules\\ExternalApi\\Modules\\{$name}\\Http\\{$name}HttpClient::class,
            'mock_client' => App\\Modules\\ExternalApi\\Modules\\{$name}\\Http\\{$name}MockHttpClient::class,
            'fake' => env('{$envPrefix}_FAKE_ENABLED', env('EXTERNAL_API_FAKE_ENABLED', true)),
            'base_url' => env('{$envPrefix}_BASE_URL', 'https://api.example.com'),
            'api_key' => env('{$envPrefix}_API_KEY'),
            'timeout' => env('{$envPrefix}_TIMEOUT', 30),
            'connect_timeout' => env('{$envPrefix}_CONNECT_TIMEOUT', 10),
            'retry_attempts' => env('{$envPrefix}_RETRY_ATTEMPTS', 3),
            'rate_limit' => env('{$envPrefix}_RATE_LIMIT', 100),
            'endpoints' => [],
        ],
PHP;
    }
}
