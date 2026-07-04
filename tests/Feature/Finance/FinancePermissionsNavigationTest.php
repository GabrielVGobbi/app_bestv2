<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePermissionsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_v2_seeds_entry_and_settlement_permissions(): void
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $this->assertTrue(Permission::query()->where('slug', 'finance.entries.view')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.entries.create')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.entries.update')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.entries.cancel')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.settlements.create')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.settlements.reverse')->exists());
        $this->assertFalse(Permission::query()->where('slug', 'finance.reconciliation.manage')->exists());
        $this->assertFalse(Permission::query()->where('slug', 'finance.reports.view')->exists());
    }

    public function test_finance_user_sees_v2_entries_and_foundation_navigation(): void
    {
        $financeUser = $this->userWithRole('finance');

        $this->actingAs($financeUser);

        $modules = app(NavigationService::class)->modules();
        $financeModule = collect($modules)->firstWhere('label', 'Financeiro');

        $this->assertIsArray($financeModule);
        $this->assertSame('/financial-entries', $financeModule['defaultHref']);

        $operations = collect($financeModule['items'])->firstWhere('title', 'Operacoes');
        $settings = collect($financeModule['items'])->firstWhere('title', 'Configuracoes');

        $this->assertIsArray($operations);
        $this->assertIsArray($settings);
        $this->assertEqualsCanonicalizing(
            ['Lançamentos financeiros'],
            collect($operations['items'])->pluck('title')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Categorias financeiras', 'Metodos de pagamento'],
            collect($settings['items'])->pluck('title')->all(),
        );

        $this->assertNotContains('Conciliacao', collect($operations['items'])->pluck('title')->all());
    }

    public function test_navigation_hides_finance_module_and_supplier_item_without_permissions(): void
    {
        $commercialUser = $this->userWithRole('commercial');

        $this->actingAs($commercialUser);

        $modules = app(NavigationService::class)->modules();

        $this->assertNull(collect($modules)->firstWhere('label', 'Financeiro'));

        $gestao = collect($modules)->firstWhere('label', 'Gestão')
            ?? collect($modules)->firstWhere('label', 'GestÃ£o');

        $this->assertIsArray($gestao);

        $cadastros = collect($gestao['items'])->firstWhere('title', 'Cadastros');

        $this->assertIsArray($cadastros);
        $this->assertNotContains('Fornecedores', collect($cadastros['items'])->pluck('title')->all());
    }

    private function userWithRole(string $roleSlug): User
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
