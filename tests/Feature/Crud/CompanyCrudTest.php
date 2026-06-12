<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Companies\Models\Company;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_view_company_pages(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'name' => 'Best Play',
            'nickname' => 'Best Play',
            'cnpj' => '36569969000125',
        ]);

        $this->actingAs($user)
            ->get(route('companies.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('companies.show', $company))
            ->assertForbidden();
    }

    public function test_admin_can_view_company_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $company = Company::factory()->create([
            'name' => 'Best Play',
            'nickname' => 'Best Play',
            'cnpj' => '36569969000125',
        ]);

        $this->actingAs($admin)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/companies/index')
                ->where('permissions.can_manage', true)
            );

        $this->actingAs($admin)
            ->get(route('companies.show', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/companies/show')
                ->where('companyData.data.uuid', $company->uuid)
                ->where('companyData.data.cnpj', '36569969000125')
                ->where('permissions.can_manage', true)
            );
    }

    public function test_admin_can_access_company_create_and_edit_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $company = Company::factory()->create([
            'name' => 'Best Play',
            'nickname' => 'Best Play',
            'cnpj' => '36569969000125',
        ]);

        $this->actingAs($admin)
            ->get(route('companies.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/companies/create')
            );

        $this->actingAs($admin)
            ->get(route('companies.edit', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/companies/edit')
                ->where('companyData.data.uuid', $company->uuid)
                ->where('permissions.can_manage', true)
            );
    }

    public function test_regular_user_cannot_create_edit_update_or_delete_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'name' => 'Best Play',
            'nickname' => 'Best Play',
            'cnpj' => '36569969000125',
        ]);

        $this->actingAs($user)
            ->get(route('companies.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('companies.store'), [
                'name' => 'Best Branch',
                'nickname' => 'Best Branch',
                'cnpj' => '43.358.044/0001-09',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('companies.edit', $company))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('companies.update', $company), [
                'name' => 'Best Play Updated',
                'nickname' => 'Best Play',
                'cnpj' => '36.569.969/0001-25',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('companies.destroy', $company))
            ->assertForbidden();

        $company->refresh();

        $this->assertSame('Best Play', $company->name);
        $this->assertNull($company->deleted_at);
        $this->assertDatabaseMissing('companies', ['cnpj' => '43358044000109']);
    }

    public function test_admin_can_delete_company(): void
    {
        $admin = $this->userWithRole('admin');
        $company = Company::factory()->create([
            'name' => 'Best Play',
            'nickname' => 'Best Play',
            'cnpj' => '36569969000125',
        ]);

        $this->actingAs($admin)
            ->delete(route('companies.destroy', $company))
            ->assertRedirect(route('companies.index'));

        $this->assertSoftDeleted($company);
    }

    public function test_api_returns_company_records(): void
    {
        $user = $this->userWithRole('admin');

        Company::factory()->create([
            'name' => 'Best Play',
            'nickname' => 'Best Play',
            'cnpj' => '36569969000125',
        ]);

        Company::factory()->create([
            'name' => 'Bean Music',
            'nickname' => 'Bean Music',
            'cnpj' => '48120660000132',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Bean Music')
            ->assertJsonPath('data.1.name', 'Best Play');
    }

    public function test_regular_user_cannot_use_company_api(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'name' => 'Best Play',
            'nickname' => 'Best Play',
            'cnpj' => '36569969000125',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/companies')
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson("/api/v1/companies/{$company->uuid}")
            ->assertForbidden();
    }

    public function test_company_api_route_uses_the_stateful_api_group(): void
    {
        $route = Route::getRoutes()->getByName('api.companies.index');

        $this->assertNotNull($route);
        $this->assertSame('api/v1/companies', $route->uri());
        $this->assertContains('api', $route->gatherMiddleware());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    public function test_authenticated_user_can_create_company_with_normalized_cnpj_and_fiscal_metadata(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->post(route('companies.store'), [
                'name' => 'Best Play',
                'nickname' => 'Best Play',
                'cnpj' => '36.569.969/0001-25',
                'fiscal_metadata' => json_encode([
                    'cpfCnpj' => '00000000000000',
                    'razaoSocial' => 'BEST PLAY MARKETING DIRETO LTDA',
                    'endereco' => [
                        'logradouro' => 'Alameda Rio Negro',
                        'numero' => '1084',
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $company = Company::query()->where('cnpj', '36569969000125')->firstOrFail();

        $this->assertSame('Best Play', $company->name);
        $this->assertSame('Best Play', $company->nickname);
        $this->assertSame('36569969000125', $company->cnpj);
        $this->assertSame('36569969000125', $company->fiscal_metadata['cpfCnpj']);
        $this->assertSame('BEST PLAY MARKETING DIRETO LTDA', $company->fiscal_metadata['razaoSocial']);
        $this->assertSame('1084', $company->fiscal_metadata['endereco']['numero']);
    }

    public function test_api_accepts_array_fiscal_metadata(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->postJson('/api/v1/companies', [
                'name' => 'Best Branch',
                'nickname' => 'Best Branch',
                'cnpj' => '43.358.044/0001-09',
                'fiscal_metadata' => [
                    'razaoSocial' => 'BEST BRANCH MARKETING DIRETO',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.cnpj', '43358044000109')
            ->assertJsonPath('data.fiscal_metadata.cpfCnpj', '43358044000109')
            ->assertJsonPath('data.fiscal_metadata.razaoSocial', 'BEST BRANCH MARKETING DIRETO');
    }

    public function test_company_rejects_duplicate_active_cnpj(): void
    {
        $user = $this->userWithRole('super-admin');

        Company::factory()->create([
            'cnpj' => '36569969000125',
        ]);

        $this->actingAs($user)
            ->post(route('companies.store'), [
                'name' => 'Duplicada',
                'nickname' => 'Duplicada',
                'cnpj' => '36.569.969/0001-25',
            ])
            ->assertSessionHasErrors('cnpj');

        $this->assertSame(1, Company::query()->withTrashed()->count());
    }

    public function test_company_allows_reusing_cnpj_from_soft_deleted_record(): void
    {
        $user = $this->userWithRole('admin');

        Company::factory()->create([
            'name' => 'Arquivada',
            'nickname' => 'Arquivada',
            'cnpj' => '36569969000125',
        ])->delete();

        $this->actingAs($user)
            ->post(route('companies.store'), [
                'name' => 'Best Play',
                'nickname' => 'Best Play',
                'cnpj' => '36.569.969/0001-25',
            ])
            ->assertRedirect();

        $this->assertSame(2, Company::query()->withTrashed()->count());
        $this->assertSame(1, Company::query()->where('cnpj', '36569969000125')->count());
    }

    public function test_company_update_ignores_its_own_cnpj(): void
    {
        $user = $this->userWithRole('admin');
        $company = Company::factory()->create([
            'name' => 'Best Play',
            'nickname' => 'Best Play',
            'cnpj' => '36569969000125',
        ]);

        $this->actingAs($user)
            ->put(route('companies.update', $company), [
                'name' => 'Best Play Marketing',
                'nickname' => 'Best Play',
                'cnpj' => '36.569.969/0001-25',
                'fiscal_metadata' => json_encode([
                    'razaoSocial' => 'BEST PLAY MARKETING DIRETO LTDA',
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $company->refresh();

        $this->assertSame('Best Play Marketing', $company->name);
        $this->assertSame('36569969000125', $company->cnpj);
        $this->assertSame('36569969000125', $company->fiscal_metadata['cpfCnpj']);
    }

    public function test_company_rejects_invalid_fiscal_metadata_json(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->post(route('companies.store'), [
                'name' => 'Best Play',
                'nickname' => 'Best Play',
                'cnpj' => '36.569.969/0001-25',
                'fiscal_metadata' => '{"cpfCnpj":',
            ])
            ->assertSessionHasErrors('fiscal_metadata');

        $this->assertDatabaseMissing('companies', ['cnpj' => '36569969000125']);
    }

    public function test_companies_navigation_lives_under_admin_cadastros(): void
    {
        $navigation = config('navigation.modules');

        $adminModule = collect($navigation)->firstWhere('slug', 'admin');
        $gestaoModule = collect($navigation)->firstWhere('slug', 'gestao');

        $this->assertContains('/companies', $adminModule['route_prefixes']);
        $this->assertNotContains('/companies', $gestaoModule['route_prefixes']);

        $cadastrosSection = collect($adminModule['items'])->firstWhere('slug', 'admin_cadastros_section');

        $this->assertNotNull($cadastrosSection);
        $this->assertSame('Cadastros', $cadastrosSection['label']);
        $this->assertSame(
            ['route' => 'companies.index'],
            collect($cadastrosSection['items'])->firstWhere('slug', 'admin_companies')['href'],
        );
    }

    public function test_company_seeder_creates_initial_group_companies_idempotently(): void
    {
        $this->seed(CompanySeeder::class);
        $this->seed(CompanySeeder::class);

        $this->assertSame(3, Company::query()->count());

        $companies = Company::query()
            ->orderBy('cnpj')
            ->get()
            ->keyBy('cnpj');

        $this->assertTrue($companies->has('36569969000125'));
        $this->assertTrue($companies->has('43358044000109'));
        $this->assertTrue($companies->has('48120660000132'));
        $this->assertSame('Best Play', $companies->get('36569969000125')->name);
        $this->assertSame('Best Branch', $companies->get('43358044000109')->nickname);
        $this->assertSame('BEAN MUSIC', $companies->get('48120660000132')->fiscal_metadata['nomeFantasia']);
    }

    private function userWithRole(string $roleSlug): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => str($roleSlug)->replace('-', ' ')->title()->toString()],
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);
        $user->load('roles');

        return $user;
    }
}
