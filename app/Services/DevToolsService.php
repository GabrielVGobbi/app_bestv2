<?php

declare(strict_types=1);

namespace App\Services;

use Dotenv\Exception\InvalidFileException;
use Dotenv\Parser\Parser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cookie as CookieFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

class DevToolsService
{
    public const OPERATIONAL_CONFIG_KEYS = [
        'APP_NAME' => 'app.name',
        'APP_LOCALE' => 'app.locale',
        'APP_FALLBACK_LOCALE' => 'app.fallback_locale',
        'TELESCOPE_ENABLED' => 'telescope.enabled',
    ];

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
        ];
    }

    /** @return array{APP_NAME: string, APP_LOCALE: string, APP_FALLBACK_LOCALE: string, TELESCOPE_ENABLED: bool} */
    public function getOperationalSettings(): array
    {
        return [
            'APP_NAME' => (string) config('app.name'),
            'APP_LOCALE' => (string) config('app.locale'),
            'APP_FALLBACK_LOCALE' => (string) config('app.fallback_locale'),
            'TELESCOPE_ENABLED' => $this->isTelescopeEnabled(),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function environmentRules(?string $key): array
    {
        $valueRules = match ($key) {
            'APP_NAME' => ['required', 'string', 'max:80', 'regex:/\A[\pL\pN][\pL\pN ._-]{0,79}\z/u'],
            'APP_LOCALE', 'APP_FALLBACK_LOCALE' => ['required', 'string', Rule::in(['en', 'pt_BR'])],
            'TELESCOPE_ENABLED' => ['required', 'string', Rule::in(['true', 'false'])],
            default => ['required', 'string', 'max:80'],
        };

        return [
            'key' => ['required', 'string', Rule::in(array_keys(self::OPERATIONAL_CONFIG_KEYS))],
            'value' => $valueRules,
        ];
    }

    public static function validateOperationalSetting(string $key, string $value): string
    {
        Validator::make(compact('key', 'value'), self::environmentRules($key))->validate();

        return $value;
    }

    /**
     * Ativa ou desativa o modo de manutenção.
     */
    public function toggleMaintenance(): Cookie
    {
        $this->assertOperationalEnvironment();

        if (config('app.maintenance.driver', 'file') !== 'file') {
            throw ValidationException::withMessages(['maintenance' => 'O controle web exige manutenção em arquivos locais da aplicação.']);
        }

        $this->assertContainedPath(storage_path('framework'), [storage_path()]);
        $this->assertContainedPath(storage_path('framework/down'), [storage_path('framework')]);
        $this->assertContainedPath(storage_path('framework/maintenance.php'), [storage_path('framework')]);

        if (app()->isDownForMaintenance()) {
            app()->maintenanceMode()->deactivate();
            File::delete(storage_path('framework/maintenance.php'));

            return CookieFactory::forget('laravel_maintenance', '/', null)->withDomain(null);
        }

        $secret = Str::random(64);
        app()->maintenanceMode()->activate(['retry' => 60, 'secret' => $secret, 'status' => 503]);

        return MaintenanceModeBypassCookie::create($secret)
            ->withDomain(null)->withPath('/')->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_LAX)->withSecure(request()->isSecure());
    }

    /**
     * Limpa todos os caches da aplicação.
     */
    public function clearAllCaches(): void
    {
        foreach (['cache', 'config', 'route', 'view'] as $type) {
            $this->assertCacheDestination($type);
        }

        foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $command) {
            $this->runCommand($command);
        }
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

        $this->assertCacheDestination($type);
        $this->runCommand($commands[$type]);
    }

    /**
     * Otimiza a aplicação (cache de config, rotas e views).
     */
    public function optimize(): void
    {
        foreach (['config', 'route', 'view'] as $type) {
            $this->assertCacheDestination($type);
        }

        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            $this->runCommand($command);
        }
    }

    /**
     * Alterna o estado habilitado/desabilitado do Telescope no .env.
     */
    public function toggleTelescope(): void
    {
        $current = $this->isTelescopeEnabled();
        $newValue = $current ? 'false' : 'true';
        $this->updateEnvVariable('TELESCOPE_ENABLED', $newValue);
    }

    /**
     * Alterna o modo de captura total do Telescope no .env.
     */
    public function toggleTelescopeCaptureAll(): void
    {
        throw ValidationException::withMessages(['setting' => 'O controle de captura total não é suportado.']);
    }

    /**
     * Atualiza ou insere uma variável no arquivo .env.
     *
     * @throws RuntimeException
     */
    public function updateEnvVariable(string $key, string $value): void
    {
        $value = self::validateOperationalSetting($key, $value);
        $this->assertOperationalEnvironment();
        $envPath = app()->environmentFilePath();

        if (! is_file($envPath) || is_link($envPath)) {
            throw new RuntimeException('Arquivo .env não encontrado.');
        }

        clearstatcache(true, app()->getCachedConfigPath());

        if (app()->configurationIsCached() || is_file(app()->getCachedConfigPath())) {
            throw ValidationException::withMessages(['value' => 'Limpe o cache de configuração antes de editar as configurações operacionais.']);
        }

        $lockPath = $envPath.'.lock';

        if (is_link($lockPath)) {
            throw new RuntimeException('Destino de lock de configuração inválido.');
        }

        $lock = fopen($lockPath, 'c+b');

        if ($lock === false) {
            throw new RuntimeException('Não foi possível bloquear a configuração operacional.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Não foi possível bloquear a configuração operacional.');
            }

            if (! is_file($envPath) || is_link($envPath)) {
                throw new RuntimeException('Arquivo de configuração indisponível.');
            }

            $content = File::get($envPath);
            $assignmentCount = $this->countSingleLineEnvironmentAssignments($content, $key);
            $pattern = '/^[ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=[^\r\n]*/m';
            $count = preg_match_all($pattern, $content);

            if ($count === false || $assignmentCount > 1 || $count !== $assignmentCount) {
                throw ValidationException::withMessages(['key' => 'A configuração contém uma chave operacional duplicada ou ambígua.']);
            }

            $serialized = $key === 'APP_NAME' ? '"'.$value.'"' : $value;
            $line = $key.'='.$serialized;
            $lineEnding = str_contains($content, "\r\n") ? "\r\n" : "\n";
            $updated = $count === 1
                ? preg_replace_callback($pattern, fn (): string => $line, $content)
                : $content.($content !== '' && ! str_ends_with($content, "\n") ? $lineEnding : '').$line.$lineEnding;
            $this->replaceEnvironmentFile($envPath, $updated);
            config([self::OPERATIONAL_CONFIG_KEYS[$key] => $key === 'TELESCOPE_ENABLED' ? $value === 'true' : $value]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function replaceEnvironmentFile(string $path, string $content): void
    {
        $temporaryPath = tempnam(dirname($path), '.operational-env-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Não foi possível preparar a configuração operacional.');
        }

        try {
            if (realpath(dirname($temporaryPath)) !== realpath(dirname($path))) {
                throw new RuntimeException('Destino temporário de configuração inválido.');
            }

            $permissions = fileperms($path);

            if ($permissions === false || ! chmod($temporaryPath, $permissions & 0777)) {
                throw new RuntimeException('Não foi possível preservar as permissões da configuração operacional.');
            }

            if (File::put($temporaryPath, $content, true) !== strlen($content) || ! rename($temporaryPath, $path)) {
                throw new RuntimeException('Não foi possível atualizar a configuração operacional.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function countSingleLineEnvironmentAssignments(string $content, string $key): int
    {
        try {
            $entries = (new Parser)->parse($content);
        } catch (InvalidFileException) {
            throw ValidationException::withMessages(['value' => 'O arquivo de configuração precisa de revisão antes da edição operacional.']);
        }

        $assignmentCount = 0;

        foreach ($entries as $entry) {
            $value = $entry->getValue()->getOrElse(null);

            if ($value !== null && preg_match('/[\r\n]/', $value->getChars())) {
                throw ValidationException::withMessages(['value' => 'Arquivos com valores multilinha exigem edição manual.']);
            }

            if ($entry->getName() === $key) {
                $assignmentCount++;
            }
        }

        return $assignmentCount;
    }

    private function assertOperationalEnvironment(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true) || config('app.env') !== app()->environment()) {
            throw new AuthorizationException('Ferramentas operacionais indisponíveis neste ambiente.');
        }
    }

    private function assertCacheDestination(string $type): void
    {
        $this->assertOperationalEnvironment();

        if ($type === 'cache') {
            $store = config('cache.stores.'.config('cache.default'), []);

            if (($store['driver'] ?? null) === 'array') {
                return;
            }

            if (($store['driver'] ?? null) !== 'file' || ! is_string($store['path'] ?? null)) {
                throw ValidationException::withMessages(['type' => 'O cache deve usar um armazenamento local dedicado.']);
            }

            $this->assertContainedPath($store['path'], [storage_path()]);

            return;
        }

        $path = match ($type) {
            'config' => app()->getCachedConfigPath(),
            'route' => app()->getCachedRoutesPath(),
            'view' => (string) config('view.compiled'),
        };
        $this->assertContainedPath($path, $type === 'view' ? [storage_path()] : [app()->bootstrapPath()]);
    }

    /** @param list<string> $roots */
    private function assertContainedPath(string $path, array $roots): void
    {
        $resolved = realpath($path);

        if ($resolved === false) {
            $parent = realpath(dirname($path));
            $resolved = $parent === false ? false : $parent.DIRECTORY_SEPARATOR.basename($path);
        }

        if ($resolved !== false && ! is_link($path)) {
            $normalize = fn (string $value): string => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $value)) : str_replace('\\', '/', $value);

            foreach ($roots as $root) {
                $resolvedRoot = realpath($root);

                if ($resolvedRoot !== false && ! is_link($root) && str_starts_with($normalize($resolved), rtrim($normalize($resolvedRoot), '/').'/')) {
                    return;
                }
            }
        }

        throw ValidationException::withMessages(['type' => 'Destino operacional fora dos diretórios locais da aplicação.']);
    }

    private function runCommand(string $command): void
    {
        if (Artisan::call($command) !== 0) {
            throw ValidationException::withMessages(['operation' => 'A operação de cache não foi concluída.']);
        }
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
        return filter_var(config('telescope.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}
