<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Processa e disponibiliza a estrutura de navegação definida em config/navigation.php.
 *
 * Cache: em ambientes não-locais, o resultado é persistido indefinidamente.
 * Para invalidar: NavigationService::flush() ou php artisan cache:clear.
 */
class NavigationService
{
    private const CACHE_KEY = 'navigation.v4';

    public function style(): string
    {
        return config('navigation.style', 'modules');
    }

    public function modulesMenuPosition(): string
    {
        $position = config('navigation.modules_menu.position', 'sidebar');

        return in_array($position, ['sidebar', 'header'], true) ? $position : 'sidebar';
    }

    /** @return array<int, array<string, mixed>> */
    public function global(): array
    {
        return $this->resolved()['global'];
    }

    /** @return array<int, array<string, mixed>> */
    public function modules(): array
    {
        return $this->resolved()['modules'];
    }

    /** @return array<int, array<string, mixed>> */
    public function footer(): array
    {
        return $this->resolved()['footer'];
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{modules: array<int, mixed>, footer: array<int, mixed>} */
    private function resolved(): array
    {
        if (app()->isLocal()) {
            return $this->build();
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->build());
    }

    /** @return array{global: array<int, mixed>, modules: array<int, mixed>, footer: array<int, mixed>} */
    private function build(): array
    {
        return [
            'global' => $this->processItems(config('navigation.global', [])),
            'modules' => $this->processModules(config('navigation.modules', [])),
            'footer' => $this->processItems(config('navigation.footer', [])),
        ];
    }

    /**
     * Processa a lista de módulos.
     *
     * @param  array<int, array<string, mixed>>  $modules
     * @return array<int, array<string, mixed>>
     */
    private function processModules(array $modules): array
    {
        return collect($modules)
            ->filter(fn (array $m) => $m['enabled'] ?? true)
            ->map(fn (array $m) => [
                'slug' => $m['slug'],
                'label' => $m['label'],
                'icon' => $m['icon'] ?? null,
                'default' => $m['default'] ?? false,
                'routePrefixes' => $m['route_prefixes'] ?? [],
                'href' => isset($m['href']) ? $this->resolveHref($m['href']) : null,
                'defaultHref' => isset($m['default_href']) ? $this->resolveHref($m['default_href']) : null,
                'items' => $this->processItems($m['items'] ?? []),
            ])
            ->values()
            ->all();
    }

    /**
     * Filtra, ordena e normaliza uma lista de itens de navegação.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function processItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => $item['enabled'] ?? true)
            ->sortBy('order')
            ->map(fn (array $item) => $this->normalizeItem($item))
            ->values()
            ->all();
    }

    /**
     * Converte um item do config para o formato consumido pelo frontend.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        $normalized = [
            'title' => $item['label'],
            'type' => $item['type'] ?? (! empty($item['items']) ? 'group' : 'link'),
            'icon' => $item['icon'] ?? null,
            'visible' => $item['visible'] ?? true,
        ];

        if (isset($item['href'])) {
            $normalized['href'] = $this->resolveHref($item['href']);
        }

        if (! empty($item['items'])) {
            $normalized['items'] = $this->processItems($item['items']);
        }

        return $normalized;
    }

    /**
     * Resolve href: string literal (externo) ou named route (relativo).
     *
     * @param  string|array{route: string, params?: array<string, mixed>}  $href
     */
    private function resolveHref(string|array $href): string
    {
        if (is_string($href)) {
            return $href;
        }

        return route($href['route'], $href['params'] ?? [], absolute: false);
    }
}
