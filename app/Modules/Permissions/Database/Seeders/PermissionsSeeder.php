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
        $this->removeDeferredFinancePermissions();

        $permissions = [
            ['name' => 'Acessar painel', 'slug' => 'access-panel', 'group' => 'painel', 'description' => 'Permite acessar o painel administrativo'],
            ['name' => 'Gerenciar usuarios', 'slug' => 'users.manage', 'group' => 'users', 'description' => 'Permite criar, editar e remover usuarios'],
            ['name' => 'Gerenciar roles', 'slug' => 'roles.manage', 'group' => 'roles', 'description' => 'Permite gerenciar perfis de acesso'],
            ['name' => 'Gerenciar permissoes', 'slug' => 'permissions.manage', 'group' => 'permissions', 'description' => 'Permite gerenciar permissoes'],

            ['name' => 'Visualizar empresas', 'slug' => 'companies.view', 'group' => 'companies', 'description' => 'Permite visualizar empresas'],
            ['name' => 'Gerenciar empresas', 'slug' => 'companies.manage', 'group' => 'companies', 'description' => 'Permite criar, editar e excluir empresas'],

            ['name' => 'Visualizar clientes', 'slug' => 'clients.view', 'group' => 'clients', 'description' => 'Permite visualizar clientes'],
            ['name' => 'Gerenciar clientes', 'slug' => 'clients.manage', 'group' => 'clients', 'description' => 'Permite criar, editar e excluir clientes'],

            ['name' => 'Visualizar fornecedores', 'slug' => 'suppliers.view', 'group' => 'suppliers', 'description' => 'Permite visualizar fornecedores'],
            ['name' => 'Criar fornecedores', 'slug' => 'suppliers.create', 'group' => 'suppliers', 'description' => 'Permite criar fornecedores'],
            ['name' => 'Editar fornecedores', 'slug' => 'suppliers.update', 'group' => 'suppliers', 'description' => 'Permite editar fornecedores'],
            ['name' => 'Excluir fornecedores', 'slug' => 'suppliers.delete', 'group' => 'suppliers', 'description' => 'Permite excluir ou arquivar fornecedores'],

            ['name' => 'Gerenciar enderecos', 'slug' => 'addresses.manage', 'group' => 'addresses', 'description' => 'Permite gerenciar enderecos'],

            ['name' => 'Visualizar Serviço', 'slug' => 'services.view', 'group' => 'services', 'description' => 'Permite visualizar Serviço'],
            ['name' => 'Criar Serviço', 'slug' => 'services.create', 'group' => 'services', 'description' => 'Permite criar Serviço'],
            ['name' => 'Editar Serviço', 'slug' => 'services.update', 'group' => 'services', 'description' => 'Permite editar Serviço'],
            ['name' => 'Excluir Serviço', 'slug' => 'services.delete', 'group' => 'services', 'description' => 'Permite excluir ou arquivar Serviço'],
            ['name' => 'Restaurar Serviço', 'slug' => 'services.restore', 'group' => 'services', 'description' => 'Permite restaurar Serviço arquivados'],

            ['name' => 'Visualizar vendas', 'slug' => 'sales.view', 'group' => 'sales', 'description' => 'Permite visualizar todas as vendas'],
            ['name' => 'Visualizar próprias vendas', 'slug' => 'sales.view-own', 'group' => 'sales', 'description' => 'Permite visualizar vendas próprias'],
            ['name' => 'Criar vendas', 'slug' => 'sales.create', 'group' => 'sales', 'description' => 'Permite criar vendas'],
            ['name' => 'Editar vendas', 'slug' => 'sales.update', 'group' => 'sales', 'description' => 'Permite editar vendas em rascunho ou orçamento'],
            ['name' => 'Enviar orçamento', 'slug' => 'sales.quote', 'group' => 'sales', 'description' => 'Permite marcar venda como orçamento'],
            ['name' => 'Confirmar vendas', 'slug' => 'sales.confirm', 'group' => 'sales', 'description' => 'Permite confirmar vendas'],
            ['name' => 'Finalizar campanha', 'slug' => 'sales.finish', 'group' => 'sales', 'description' => 'Permite finalizar campanha de venda'],
            ['name' => 'Cancelar vendas', 'slug' => 'sales.cancel', 'group' => 'sales', 'description' => 'Permite cancelar vendas'],
            ['name' => 'Arquivar vendas', 'slug' => 'sales.delete', 'group' => 'sales', 'description' => 'Permite arquivar vendas canceladas ou rascunhos'],
            ['name' => 'Visualizar valores de vendas', 'slug' => 'sales.amounts.view', 'group' => 'sales', 'description' => 'Permite visualizar totais comerciais de vendas'],
            ['name' => 'Adicionar item de venda', 'slug' => 'sales.items.create', 'group' => 'sales', 'description' => 'Permite adicionar itens em vendas editáveis'],
            ['name' => 'Editar item de venda', 'slug' => 'sales.items.update', 'group' => 'sales', 'description' => 'Permite editar itens em vendas editáveis'],
            ['name' => 'Remover item de venda', 'slug' => 'sales.items.delete', 'group' => 'sales', 'description' => 'Permite remover itens em vendas editáveis'],
            ['name' => 'Vender vaga de playlist', 'slug' => 'sales.items.playlist-slot.create', 'group' => 'sales', 'description' => 'Permite vender vagas de playlist como itens de venda'],
            ['name' => 'Consultar música no Spotify', 'slug' => 'sales.items.spotify-track.lookup', 'group' => 'sales', 'description' => 'Permite enriquecer músicas de itens pelo Spotify'],
            ['name' => 'Verificar música na playlist Spotify', 'slug' => 'sales.items.spotify-playlist.verify', 'group' => 'sales', 'description' => 'Permite verificar se a música vendida está na playlist do Spotify'],
            ['name' => 'Aplicar desconto em item', 'slug' => 'sales.items.discount', 'group' => 'sales', 'description' => 'Permite aplicar desconto comercial em itens de venda'],

            ['name' => 'Visualizar playlists', 'slug' => 'playlists.view', 'group' => 'playlists', 'description' => 'Permite visualizar playlists'],
            ['name' => 'Criar playlist', 'slug' => 'playlists.create', 'group' => 'playlists', 'description' => 'Permite criar playlists'],
            ['name' => 'Editar playlist', 'slug' => 'playlists.update', 'group' => 'playlists', 'description' => 'Permite editar playlists'],
            ['name' => 'Excluir playlist', 'slug' => 'playlists.delete', 'group' => 'playlists', 'description' => 'Permite excluir ou arquivar playlists'],
            ['name' => 'Restaurar playlist', 'slug' => 'playlists.restore', 'group' => 'playlists', 'description' => 'Permite restaurar playlists arquivadas'],
            ['name' => 'Gerenciar gêneros musicais', 'slug' => 'playlists.genres.manage', 'group' => 'playlists', 'description' => 'Permite gerenciar gêneros e subgêneros musicais'],
            ['name' => 'Ver disponibilidade de playlists', 'slug' => 'playlists.availability.view', 'group' => 'playlists', 'description' => 'Permite visualizar disponibilidade futura de playlists'],
            ['name' => 'Visualizar TOPs de playlists', 'slug' => 'playlist-tops.view', 'group' => 'playlist-tops', 'description' => 'Permite visualizar TOPs e vínculos comerciais de playlists'],
            ['name' => 'Criar TOP global', 'slug' => 'playlist-tops.create', 'group' => 'playlist-tops', 'description' => 'Permite criar TOPs no catálogo global'],
            ['name' => 'Editar TOP global', 'slug' => 'playlist-tops.update', 'group' => 'playlist-tops', 'description' => 'Permite editar TOPs do catálogo global'],
            ['name' => 'Excluir TOP global', 'slug' => 'playlist-tops.delete', 'group' => 'playlist-tops', 'description' => 'Permite arquivar TOPs do catálogo global'],
            ['name' => 'Restaurar TOP global', 'slug' => 'playlist-tops.restore', 'group' => 'playlist-tops', 'description' => 'Permite restaurar TOPs arquivados'],
            ['name' => 'Atrelar TOP à playlist', 'slug' => 'playlist-tops.attach', 'group' => 'playlist-tops', 'description' => 'Permite vincular TOPs existentes a playlists'],
            ['name' => 'Configurar TOP da playlist', 'slug' => 'playlist-tops.configure', 'group' => 'playlist-tops', 'description' => 'Permite configurar capacidade, preço e métricas do TOP na playlist'],
            ['name' => 'Remover TOP da playlist', 'slug' => 'playlist-tops.detach', 'group' => 'playlist-tops', 'description' => 'Permite remover ou arquivar o vínculo entre playlist e TOP'],
            ['name' => 'Ver disponibilidade de vagas', 'slug' => 'playlist-slots.availability.view', 'group' => 'playlist-slots', 'description' => 'Permite consultar disponibilidade de vagas por período'],
            ['name' => 'Reservar vaga de playlist', 'slug' => 'playlist-slots.reserve', 'group' => 'playlist-slots', 'description' => 'Permite reservar vagas de playlist'],
            ['name' => 'Ativar vaga de playlist', 'slug' => 'playlist-slots.activate', 'group' => 'playlist-slots', 'description' => 'Permite ativar vagas de playlist'],
            ['name' => 'Agendar vaga de playlist', 'slug' => 'playlist-slots.schedule', 'group' => 'playlist-slots', 'description' => 'Permite agendar vagas pendentes de playlist'],
            ['name' => 'Cancelar vaga de playlist', 'slug' => 'playlist-slots.cancel', 'group' => 'playlist-slots', 'description' => 'Permite cancelar vagas de playlist'],
            ['name' => 'Visualizar vagas de playlist', 'slug' => 'playlist-slots.view', 'group' => 'playlist-slots', 'description' => 'Permite visualizar ocupações de playlist'],
            ['name' => 'Visualizar fila de playlists', 'slug' => 'playlist-delivery-tasks.view', 'group' => 'playlist-delivery-tasks', 'description' => 'Permite visualizar tarefas operacionais de playlists'],
            ['name' => 'Concluir checklist de playlist', 'slug' => 'playlist-delivery-tasks.complete', 'group' => 'playlist-delivery-tasks', 'description' => 'Permite marcar tarefas operacionais de playlists como concluídas'],
            ['name' => 'Trocar música de vaga iniciada', 'slug' => 'playlist-delivery-tasks.change-track', 'group' => 'playlist-delivery-tasks', 'description' => 'Permite trocar a música de uma entrega de playlist em campanha iniciada'],

            ['name' => 'Visualizar financeiro', 'slug' => 'finance.view', 'group' => 'finance', 'description' => 'Permite visualizar dados financeiros'],
            ['name' => 'Gerenciar financeiro', 'slug' => 'finance.manage', 'group' => 'finance', 'description' => 'Permite gerenciar recebimentos, pagamentos, baixas e bancos'],
            ['name' => 'Gerenciar configuracoes financeiras', 'slug' => 'finance.settings.manage', 'group' => 'finance', 'description' => 'Permite gerenciar configuracoes do financeiro'],
            ['name' => 'Visualizar categorias financeiras', 'slug' => 'finance.categories.view', 'group' => 'finance', 'description' => 'Permite visualizar categorias financeiras'],
            ['name' => 'Criar categorias financeiras', 'slug' => 'finance.categories.create', 'group' => 'finance', 'description' => 'Permite criar categorias financeiras'],
            ['name' => 'Editar categorias financeiras', 'slug' => 'finance.categories.update', 'group' => 'finance', 'description' => 'Permite editar categorias financeiras'],
            ['name' => 'Excluir categorias financeiras', 'slug' => 'finance.categories.delete', 'group' => 'finance', 'description' => 'Permite excluir categorias financeiras sem vinculos'],
            ['name' => 'Visualizar metodos de pagamento', 'slug' => 'finance.payment-methods.view', 'group' => 'finance', 'description' => 'Permite visualizar metodos de pagamento'],
            ['name' => 'Criar metodos de pagamento', 'slug' => 'finance.payment-methods.create', 'group' => 'finance', 'description' => 'Permite criar metodos de pagamento'],
            ['name' => 'Editar metodos de pagamento', 'slug' => 'finance.payment-methods.update', 'group' => 'finance', 'description' => 'Permite editar metodos de pagamento'],
            ['name' => 'Excluir metodos de pagamento', 'slug' => 'finance.payment-methods.delete', 'group' => 'finance', 'description' => 'Permite excluir metodos de pagamento'],
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
                'suppliers.view',
                'suppliers.create',
                'suppliers.update',
                'suppliers.delete',
                'addresses.manage',
                'services.view',
                'services.create',
                'services.update',
                'services.delete',
                'services.restore',
                'sales.view',
                'sales.view-own',
                'sales.create',
                'sales.update',
                'sales.quote',
                'sales.confirm',
                'sales.finish',
                'sales.cancel',
                'sales.delete',
                'sales.amounts.view',
                'sales.items.create',
                'sales.items.update',
                'sales.items.delete',
                'sales.items.playlist-slot.create',
                'sales.items.spotify-track.lookup',
                'sales.items.spotify-playlist.verify',
                'sales.items.discount',
                'playlists.view',
                'playlists.create',
                'playlists.update',
                'playlists.delete',
                'playlists.restore',
                'playlists.genres.manage',
                'playlists.availability.view',
                'playlist-tops.view',
                'playlist-tops.create',
                'playlist-tops.update',
                'playlist-tops.delete',
                'playlist-tops.restore',
                'playlist-tops.attach',
                'playlist-tops.configure',
                'playlist-tops.detach',
                'playlist-slots.availability.view',
                'playlist-slots.reserve',
                'playlist-slots.activate',
                'playlist-slots.schedule',
                'playlist-slots.cancel',
                'playlist-slots.view',
                'playlist-delivery-tasks.view',
                'playlist-delivery-tasks.complete',
                'playlist-delivery-tasks.change-track',
                'finance.view',
                'finance.manage',
                'finance.settings.manage',
                'finance.categories.view',
                'finance.categories.create',
                'finance.categories.update',
                'finance.categories.delete',
                'finance.payment-methods.view',
                'finance.payment-methods.create',
                'finance.payment-methods.update',
                'finance.payment-methods.delete',
            ],
            'user' => [
                'access-panel',
            ],
            'finance' => [
                'access-panel',
                'companies.view',
                'clients.view',
                'suppliers.view',
                'suppliers.create',
                'suppliers.update',
                'suppliers.delete',
                'addresses.manage',
                'finance.view',
                'finance.manage',
                'finance.settings.manage',
                'finance.categories.view',
                'finance.categories.create',
                'finance.categories.update',
                'finance.categories.delete',
                'finance.payment-methods.view',
                'finance.payment-methods.create',
                'finance.payment-methods.update',
                'finance.payment-methods.delete',
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
                'sales.view',
                'sales.view-own',
                'sales.create',
                'sales.update',
                'sales.quote',
                'sales.confirm',
                'sales.finish',
                'sales.cancel',
                'sales.delete',
                'sales.amounts.view',
                'sales.items.create',
                'sales.items.update',
                'sales.items.delete',
                'sales.items.playlist-slot.create',
                'sales.items.spotify-track.lookup',
                'sales.items.spotify-playlist.verify',
                'sales.items.discount',
                'playlists.view',
                'playlists.create',
                'playlists.update',
                'playlists.delete',
                'playlists.restore',
                'playlists.genres.manage',
                'playlists.availability.view',
                'playlist-tops.view',
                'playlist-tops.create',
                'playlist-tops.update',
                'playlist-tops.delete',
                'playlist-tops.restore',
                'playlist-tops.attach',
                'playlist-tops.configure',
                'playlist-tops.detach',
                'playlist-slots.availability.view',
                'playlist-slots.reserve',
                'playlist-slots.activate',
                'playlist-slots.schedule',
                'playlist-slots.cancel',
                'playlist-slots.view',
                'playlist-delivery-tasks.view',
                'playlist-delivery-tasks.complete',
                'playlist-delivery-tasks.change-track',
                'finance.view',
                'finance.categories.view',
                'finance.payment-methods.view',
            ],
            'commercial' => [
                'access-panel',
                'companies.view',
                'companies.manage',
                'clients.view',
                'clients.manage',
                'addresses.manage',
                'services.view',
                'sales.view',
                'sales.view-own',
                'sales.create',
                'sales.update',
                'sales.quote',
                'sales.confirm',
                'sales.finish',
                'sales.cancel',
                'sales.amounts.view',
                'sales.items.create',
                'sales.items.update',
                'sales.items.delete',
                'sales.items.playlist-slot.create',
                'sales.items.spotify-track.lookup',
                'sales.items.spotify-playlist.verify',
                'sales.items.discount',
                'playlist-tops.view',
                'playlist-slots.availability.view',
                'playlist-slots.reserve',
                'playlist-slots.activate',
                'playlist-slots.schedule',
                'playlist-slots.cancel',
                'playlist-slots.view',
            ],
            'seller' => [
                'access-panel',
                'companies.view',
                'clients.view',
                'clients.manage',
                'addresses.manage',
                'services.view',
                'sales.view-own',
                'sales.create',
                'sales.update',
                'sales.quote',
                'sales.amounts.view',
                'sales.items.create',
                'sales.items.update',
                'sales.items.delete',
                'sales.items.playlist-slot.create',
                'sales.items.spotify-track.lookup',
                'sales.items.spotify-playlist.verify',
                'sales.items.discount',
                'playlist-tops.view',
                'playlist-slots.availability.view',
            ],
            'operation' => [
                'access-panel',
                'companies.view',
                'clients.view',
                'playlists.view',
                'playlists.update',
                'playlists.availability.view',
                'playlist-tops.view',
                'playlist-tops.attach',
                'playlist-tops.configure',
                'playlist-tops.detach',
                'playlist-slots.availability.view',
                'playlist-slots.reserve',
                'playlist-slots.activate',
                'playlist-slots.schedule',
                'playlist-slots.cancel',
                'playlist-slots.view',
                'playlist-delivery-tasks.view',
                'playlist-delivery-tasks.complete',
                'playlist-delivery-tasks.change-track',
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

    private function removeDeferredFinancePermissions(): void
    {
        Permission::query()
            ->whereIn('slug', [
                'finance.entries.view',
                'finance.entries.create',
                'finance.entries.update',
                'finance.entries.cancel',
                'finance.entries.renegotiate',
                'finance.amounts.view',
                'finance.settlements.create',
                'finance.settlements.reverse',
                'finance.reports.view',
                'finance.reconciliation.manage',
            ])
            ->delete();
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
