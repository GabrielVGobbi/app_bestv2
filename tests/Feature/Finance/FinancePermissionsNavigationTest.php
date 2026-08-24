<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Services\NavigationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancePermissionsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_v3_seeds_entry_settlement_report_reconciliation_and_attachment_permissions(): void
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $this->assertTrue(Permission::query()->where('slug', 'finance.entries.view')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.entries.create')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.entries.update')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.entries.cancel')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.settlements.create')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.settlements.reverse')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.reports.view')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.reconciliation.manage')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.attachments.manage')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.accounts.view')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.accounts.create')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.accounts.update')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'finance.accounts.archive')->exists());
    }

    public function test_finance_user_sees_v3_entries_reports_reconciliation_and_foundation_navigation(): void
    {
        $financeUser = $this->userWithRole('finance');

        $this->actingAs($financeUser);

        $modules = app(NavigationService::class)->modules();
        $financeModule = collect($modules)->firstWhere('label', 'Financeiro');

        $this->assertIsArray($financeModule);
        $this->assertSame('/financial-entries', $financeModule['defaultHref']);

        $operations = collect($financeModule['items'])->firstWhere('title', 'Operações');
        $settings = collect($financeModule['items'])->firstWhere('title', 'Configurações');

        $this->assertIsArray($operations);
        $this->assertIsArray($settings);
        $this->assertSame(
            ['Lançamentos Financeiros', 'Relatórios', 'Conciliação'],
            collect($operations['items'])->pluck('title')->all(),
        );

        $entriesGroup = collect($operations['items'])->firstWhere('title', 'Lançamentos Financeiros');

        $this->assertIsArray($entriesGroup);
        $this->assertSame('/financial-entries', $entriesGroup['href']);
        $this->assertSame(
            ['Contas a Receber', 'Contas a Pagar'],
            collect($entriesGroup['items'] ?? [])->pluck('title')->all(),
        );
        $this->assertSame(
            ['inflow', 'outflow'],
            collect($entriesGroup['items'] ?? [])->pluck('tone')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Categorias financeiras', 'Contas financeiras', 'Métodos de pagamento'],
            collect($settings['items'])->pluck('title')->all(),
        );
    }

    public function test_navigation_hides_finance_module_and_supplier_item_without_permissions(): void
    {
        $commercialUser = $this->userWithRole('commercial');

        $this->actingAs($commercialUser);

        $modules = app(NavigationService::class)->modules();

        $this->assertNull(collect($modules)->firstWhere('label', 'Financeiro'));
        $this->assertNull(collect($modules)->firstWhere('label', 'Gestão'));

        $comercial = collect($modules)->firstWhere('label', 'Comercial');

        $this->assertIsArray($comercial);

        $visibleTitles = collect($comercial['items'])
            ->flatMap(fn (array $item): array => [
                $item['title'] ?? null,
                ...collect($item['items'] ?? [])->pluck('title')->all(),
            ])
            ->filter()
            ->all();

        $this->assertNotContains('Fornecedores', $visibleTitles);
    }

    public function test_access_control_preserves_super_role_direct_role_and_denied_permissions(): void
    {
        $financeUser = $this->userWithRole('finance');
        $commercialUser = $this->userWithRole('commercial');
        $developerUser = $this->userWithRole('developer');
        $directUser = User::factory()->create();
        $permission = Permission::query()->where('slug', 'finance.entries.view')->firstOrFail();

        $directUser->permissions()->attach($permission);

        $this->assertTrue($financeUser->hasAnyPermission('finance.entries.view'));
        $this->assertTrue($directUser->hasAnyPermission('finance.entries.view'));
        $this->assertTrue($developerUser->hasAnyPermission('ability.without.database.permission'));
        $this->assertFalse($commercialUser->hasAnyPermission('finance.entries.view'));
    }

    public function test_navigation_is_built_once_with_a_constant_access_control_query_count(): void
    {
        $financeUser = $this->userWithRole('finance');
        $this->actingAs($financeUser);

        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $navigation = app(NavigationService::class);
        $navigation->global();
        $navigation->modules();
        $navigation->footer();

        $this->assertLessThanOrEqual(
            3,
            count($queries),
            'A navegacao voltou a consultar ACL por item: '.implode(' | ', $queries),
        );
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
