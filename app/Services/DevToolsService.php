<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;

class DevToolsService
{
    /**
     * Coletainformações do sistema para exibição no painel dev tools.
     *
     * @return array<string, mixed>
     */
    public function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'cache_driver' => config('cache.default'),
            'queue_connection' => config('queue.default'),
            'database_connection' => config('database.default'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'telescope_enabled' => $this->isTelescopeEnabled(),
            'telescope_capture_all' => $this->isTelescopeCaptureAll(),
        ];
    }

    /**
     * Lê variáveis do arquivo .env e retorna como array chave => valor.
     * Exclui chaves sensíveis de segredos de terceiros.
     *
     * @return array<string, string>
     */
    public function getEnvVariables(): array
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return [];
        }

        $lines = File::lines($envPath);
        $variables = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $variables[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        // Remove chaves com segredos sensíveis que não devem aparecer na UI
        $sensitiveKeys = ['DB_PASSWORD', 'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'STRIPE_SECRET'];
        foreach ($sensitiveKeys as $key) {
            if (isset($variables[$key])) {
                $variables[$key] = str_repeat('•', 8);
            }
        }

        return $variables;
    }

    /**
     * Ativa ou desativa o modo de manutenção.
     */
    public function toggleMaintenance(): void
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        } else {
            Artisan::call('down', [
                '--secret' => config('app.maintenance.secret', 'dev-bypass'),
                '--retry' => 60,
            ]);
        }
    }

    /**
     * Limpa todos os caches da aplicação.
     */
    public function clearAllCaches(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
    }

    /**
     * Limpa um tipo específico de cache.
     *
     * @throws \InvalidArgumentException
     */
    public function clearSpecificCache(string $type): void
    {
        $commands = [
            'cache' => 'cache:clear',
            'config' => 'config:clear',
            'route' => 'route:clear',
            'view' => 'view:clear',
        ];

        if (! array_key_exists($type, $commands)) {
            throw new \InvalidArgumentException("Tipo de cache inválido: {$type}");
        }

        Artisan::call($commands[$type]);
    }

    /**
     * Otimiza a aplicação (cache de config, rotas e views).
     */
    public function optimize(): void
    {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
    }

    /**
     * Alterna o estado habilitado/desabilitado do Telescope no .env.
     */
    public function toggleTelescope(): void
    {
        $current = env('TELESCOPE_ENABLED', true);
        $newValue = $current ? 'false' : 'true';
        $this->updateEnvVariable('TELESCOPE_ENABLED', $newValue);
    }

    /**
     * Alterna o modo de captura total do Telescope no .env.
     */
    public function toggleTelescopeCaptureAll(): void
    {
        $current = env('TELESCOPE_CAPTURE_ALL', false);
        $newValue = $current ? 'false' : 'true';
        $this->updateEnvVariable('TELESCOPE_CAPTURE_ALL', $newValue);
    }

    /**
     * Atualiza ou insere uma variável no arquivo .env.
     *
     * @throws \RuntimeException
     */
    public function updateEnvVariable(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            throw new \RuntimeException('Arquivo .env não encontrado.');
        }

        $content = File::get($envPath);

        // Escapar caracteres especiais no valor se necessário
        $escapedValue = str_contains($value, ' ') ? "\"{$value}\"" : $value;

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$escapedValue}",
                $content,
            );
        } else {
            $content .= "\n{$key}={$escapedValue}";
        }

        File::put($envPath, $content);
    }

    /**
     * Retorna a lista de rotas registradas na aplicação.
     *
     * @return array<int, array{method: string, uri: string, name: string, action: string, middleware: string[]}>
     */
    public function getRouteList(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->map(fn (Route $route) => [
                'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'name' => $route->getName() ?? '',
                'action' => $route->getActionName(),
                'middleware' => array_values(array_map(
                    fn ($m) => is_string($m) ? class_basename($m) : (string) $m,
                    $route->gatherMiddleware(),
                )),
            ])
            ->values()
            ->all();
    }

    /**
     * Verifica se o Telescope está habilitado (via env).
     */
    private function isTelescopeEnabled(): bool
    {
        return filter_var(env('TELESCOPE_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Verifica se o Telescope está em modo de captura total.
     */
    private function isTelescopeCaptureAll(): bool
    {
        return filter_var(env('TELESCOPE_CAPTURE_ALL', false), FILTER_VALIDATE_BOOLEAN);
    }
}
