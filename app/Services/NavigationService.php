<?php

namespace App\Services;

use App\Models\User;

/**
 * Processa e disponibiliza a estrutura de navegação definida em config/navigation.php.
 *
 * A estrutura autenticada é memoizada somente durante a requisição, pois o
 * resultado depende das permissões do usuário atual.
 */
class NavigationService
{
    /** @var array{global: array<int, mixed>, modules: array<int, mixed>, footer: array<int, mixed>}|null */
    private ?array $resolvedNavigation = null;

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
        $this->resolvedNavigation = null;
    }

    /** @return array{global: array<int, mixed>, modules: array<int, mixed>, footer: array<int, mixed>} */
    public function resolved(): array
    {
        return $this->resolvedNavigation ??= $this->build();
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
            ->filter(fn (array $m) => $this->passesRoleFilter($m))
            ->filter(fn (array $m) => $this->passesPermissionFilter($m))
            ->map(function (array $m): array {
                $items = $this->processItems($m['items'] ?? []);
                $href = isset($m['href']) ? $this->resolveHref($m['href']) : null;
                $defaultHref = isset($m['default_href']) ? $this->resolveHref($m['default_href']) : null;

                return [
                    'slug' => $m['slug'],
                    'label' => $m['label'],
                    'icon' => $m['icon'] ?? null,
                    'default' => $m['default'] ?? false,
                    'routePrefixes' => $m['route_prefixes'] ?? [],
                    'href' => $href,
                    'defaultHref' => $this->resolveVisibleDefaultHref($defaultHref, $items) ?? $href,
                    'items' => $items,
                ];
            })
            ->filter(fn (array $m) => $m['href'] !== null || $m['items'] !== [])
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
            ->filter(fn (array $item) => $this->passesRoleFilter($item))
            ->filter(fn (array $item) => $this->passesPermissionFilter($item))
            ->sortBy('order')
            ->map(fn (array $item) => $this->normalizeItem($item))
            ->filter(fn (array $item) => ! in_array($item['type'], ['group', 'section'], true) || ! empty($item['items']))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function passesRoleFilter(array $entry): bool
    {
        if (empty($entry['roles'])) {
            return true;
        }

        $user = $this->currentUser();

        if (! $user) {
            return false;
        }

        $roles = is_array($entry['roles']) ? $entry['roles'] : [$entry['roles']];

        return $user->hasRole(...$roles);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function passesPermissionFilter(array $entry): bool
    {
        if (empty($entry['permissions'])) {
            return true;
        }

        $user = $this->currentUser();

        if (! $user) {
            return false;
        }

        $permissions = is_array($entry['permissions']) ? $entry['permissions'] : [$entry['permissions']];

        return $user->hasAnyPermission(...$permissions);
    }

    private function currentUser(): ?User
    {
        $requestUser = request()->user();

        if ($requestUser instanceof User) {
            return $requestUser;
        }

        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
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

        if (isset($item['tone']) && in_array($item['tone'], ['inflow', 'outflow'], true)) {
            $normalized['tone'] = $item['tone'];
        }

        if (! empty($item['items'])) {
            $normalized['items'] = $this->processItems($item['items']);
        }

        return $normalized;
    }

    /**
     * Prefers a default href that the user can actually see in the sidebar.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function resolveVisibleDefaultHref(?string $configured, array $items): ?string
    {
        $hrefs = $this->collectHrefs($items);

        if ($configured !== null && in_array($configured, $hrefs, true)) {
            return $configured;
        }

        return $hrefs[0] ?? $configured;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<string>
     */
    private function collectHrefs(array $items): array
    {
        $hrefs = [];

        foreach ($items as $item) {
            if (isset($item['href']) && is_string($item['href'])) {
                $hrefs[] = $item['href'];
            }

            if (! empty($item['items']) && is_array($item['items'])) {
                foreach ($this->collectHrefs($item['items']) as $childHref) {
                    $hrefs[] = $childHref;
                }
            }
        }

        return $hrefs;
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
