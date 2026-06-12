<?php

namespace App\Modules\Permissions\Database\Seeders;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->normalizeLegacyPanelPermission();

        $permissions = [
            ['name' => 'Acessar painel', 'slug' => 'access-panel', 'group' => 'painel', 'description' => 'Permite acessar o painel administrativo'],
            ['name' => 'Gerenciar usuarios', 'slug' => 'users.manage', 'group' => 'users', 'description' => 'Permite criar, editar e remover usuarios'],
            ['name' => 'Gerenciar roles', 'slug' => 'roles.manage', 'group' => 'roles', 'description' => 'Permite gerenciar perfis de acesso'],
            ['name' => 'Gerenciar permissoes', 'slug' => 'permissions.manage', 'group' => 'permissions', 'description' => 'Permite gerenciar permissoes'],

            ['name' => 'Visualizar empresas', 'slug' => 'companies.view', 'group' => 'companies', 'description' => 'Permite visualizar empresas'],
            ['name' => 'Gerenciar empresas', 'slug' => 'companies.manage', 'group' => 'companies', 'description' => 'Permite criar, editar e excluir empresas'],

            ['name' => 'Visualizar clientes', 'slug' => 'clients.view', 'group' => 'clients', 'description' => 'Permite visualizar clientes'],
            ['name' => 'Gerenciar clientes', 'slug' => 'clients.manage', 'group' => 'clients', 'description' => 'Permite criar, editar e excluir clientes'],

            ['name' => 'Gerenciar enderecos', 'slug' => 'addresses.manage', 'group' => 'addresses', 'description' => 'Permite gerenciar enderecos'],

            ['name' => 'Visualizar Serviço', 'slug' => 'services.view', 'group' => 'services', 'description' => 'Permite visualizar Serviço'],
            ['name' => 'Criar Serviço', 'slug' => 'services.create', 'group' => 'services', 'description' => 'Permite criar Serviço'],
            ['name' => 'Editar Serviço', 'slug' => 'services.update', 'group' => 'services', 'description' => 'Permite editar Serviço'],
            ['name' => 'Excluir Serviço', 'slug' => 'services.delete', 'group' => 'services', 'description' => 'Permite excluir ou arquivar Serviço'],
            ['name' => 'Restaurar Serviço', 'slug' => 'services.restore', 'group' => 'services', 'description' => 'Permite restaurar Serviço arquivados'],

            ['name' => 'Criar vendas', 'slug' => 'sales.create', 'group' => 'sales', 'description' => 'Permite criar vendas'],
            ['name' => 'Confirmar vendas', 'slug' => 'sales.confirm', 'group' => 'sales', 'description' => 'Permite confirmar vendas'],
            ['name' => 'Cancelar vendas', 'slug' => 'sales.cancel', 'group' => 'sales', 'description' => 'Permite cancelar vendas'],

            ['name' => 'Gerenciar playlists', 'slug' => 'playlists.manage', 'group' => 'playlists', 'description' => 'Permite gerenciar playlists, TOPs e vagas'],

            ['name' => 'Visualizar financeiro', 'slug' => 'finance.view', 'group' => 'finance', 'description' => 'Permite visualizar dados financeiros'],
            ['name' => 'Gerenciar financeiro', 'slug' => 'finance.manage', 'group' => 'finance', 'description' => 'Permite gerenciar recebimentos, pagamentos, baixas e bancos'],
        ];

        foreach ($permissions as $permission) {
            $this->normalizeGeneratedSlug($permission['slug']);

            Permission::withoutEvents(function () use ($permission): void {
                Permission::query()->updateOrCreate(
                    ['slug' => $permission['slug']],
                    [
                        'name' => $permission['name'],
                        'group' => $permission['group'],
                        'description' => $permission['description'],
                    ]
                );
            });
        }

        $permissionSlugs = collect($permissions)->pluck('slug')->all();

        $permissionIds = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id', 'slug')
            ->all();

        $allPermissionSlugs = array_keys($permissionIds);
        $rolePermissions = [
            'developer' => $allPermissionSlugs,
            'super-admin' => $allPermissionSlugs,
            'admin' => [
                'access-panel',
                'users.manage',
                'companies.view',
                'companies.manage',
                'clients.view',
                'clients.manage',
                'addresses.manage',
                'services.view',
                'services.create',
                'services.update',
                'services.delete',
                'services.restore',
                'sales.create',
                'sales.confirm',
                'sales.cancel',
                'playlists.manage',
                'finance.view',
                'finance.manage',
            ],
            'user' => [
                'access-panel',
            ],
            'finance' => [
                'access-panel',
                'companies.view',
                'clients.view',
                'addresses.manage',
                'finance.view',
                'finance.manage',
            ],
            'manager' => [
                'access-panel',
                'companies.view',
                'companies.manage',
                'clients.view',
                'clients.manage',
                'addresses.manage',
                'services.view',
                'services.create',
                'services.update',
                'services.delete',
                'services.restore',
                'sales.create',
                'sales.confirm',
                'sales.cancel',
                'playlists.manage',
                'finance.view',
            ],
            'commercial' => [
                'access-panel',
                'companies.view',
                'companies.manage',
                'clients.view',
                'clients.manage',
                'addresses.manage',
                'services.view',
                'sales.create',
                'sales.confirm',
                'sales.cancel',
            ],
            'seller' => [
                'access-panel',
                'companies.view',
                'clients.view',
                'clients.manage',
                'addresses.manage',
                'services.view',
                'sales.create',
            ],
            'operation' => [
                'access-panel',
                'companies.view',
                'clients.view',
                'playlists.manage',
            ],
        ];

        Role::query()
            ->whereIn('slug', array_keys($rolePermissions))
            ->get()
            ->each(function (Role $role) use ($permissionIds, $rolePermissions): void {
                $role->permissions()->sync(
                    collect($rolePermissions[$role->slug])
                        ->map(fn (string $slug): int => $permissionIds[$slug])
                        ->all()
                );
            });
    }

    private function normalizeLegacyPanelPermission(): void
    {
        $legacyPermission = Permission::query()->where('slug', 'access-painel')->first();

        if (! $legacyPermission || Permission::query()->where('slug', 'access-panel')->exists()) {
            return;
        }

        Permission::withoutEvents(function () use ($legacyPermission): void {
            $legacyPermission->update([
                'slug' => 'access-panel',
                'name' => 'Acessar painel',
                'group' => 'painel',
                'description' => 'Permite acessar o painel administrativo',
            ]);
        });
    }

    private function normalizeGeneratedSlug(string $slug): void
    {
        $generatedSlug = str_replace('.', '-', $slug);

        if ($generatedSlug === $slug || Permission::query()->where('slug', $slug)->exists()) {
            return;
        }

        $permission = Permission::query()->where('slug', $generatedSlug)->first();

        if (! $permission) {
            return;
        }

        Permission::withoutEvents(function () use ($permission, $slug): void {
            $permission->update(['slug' => $slug]);
        });
    }
}
