<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SuppliersModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_pages_render_for_authorized_user(): void
    {
        $user = $this->userWithRole('developer');
        $supplier = Supplier::factory()->create();

        $this->withoutVite();

        $this->actingAs($user)->get(route('suppliers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('app/suppliers/index'));

        $this->actingAs($user)->get(route('suppliers.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('app/suppliers/create'));

        $this->actingAs($user)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('app/suppliers/show')
                ->has('supplierData.data')
                ->has('timelineSubject'));

        $this->actingAs($user)->get(route('suppliers.edit', $supplier))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('app/suppliers/edit')
                ->has('supplierData.data'));
    }

    public function test_supplier_can_be_created_updated_and_archived_from_web(): void
    {
        $user = $this->userWithRole('finance', seedPermissions: true);
        $company = Company::factory()->create();

        $response = $this->actingAs($user)->post(route('suppliers.store'), [
            'company_id' => $company->id,
            'name' => 'Fornecedor de mídia',
            'company_name' => 'Fornecedor de mídia LTDA',
            'document' => '12345678000199',
            'email' => 'financeiro@fornecedor.test',
            'phone' => '11999990000',
            'status' => 'active',
        ]);

        $supplier = Supplier::query()->firstOrFail();

        $response->assertRedirect(route('suppliers.show', $supplier));
        $this->assertSame($company->id, $supplier->company_id);
        $this->assertSame('Fornecedor de mídia', $supplier->name);

        $this->actingAs($user)->put(route('suppliers.update', $supplier), [
            'company_id' => $company->id,
            'name' => 'Fornecedor de mídia atualizado',
            'document' => '12345678000199',
            'status' => 'inactive',
        ])->assertRedirect(route('suppliers.show', $supplier));

        $supplier->refresh();

        $this->assertSame('Fornecedor de mídia atualizado', $supplier->name);
        $this->assertSame('inactive', $supplier->status);

        $this->actingAs($user)->patch(route('suppliers.status', $supplier), [
            'status' => 'archived',
        ])->assertRedirect();

        $this->assertSame('archived', $supplier->refresh()->status);
    }

    public function test_supplier_api_lists_and_returns_resource_shape(): void
    {
        $user = $this->userWithRole('developer');
        $supplier = Supplier::factory()->create([
            'name' => 'Fornecedor API',
            'document' => '99887766000155',
        ]);

        $this->actingAs($user)->getJson(route('api.suppliers.index'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $supplier->uuid,
                'name' => 'Fornecedor API',
            ]);

        $this->actingAs($user)->getJson(route('api.suppliers.show', $supplier))
            ->assertOk()
            ->assertJsonPath('data.uuid', $supplier->uuid)
            ->assertJsonPath('data.document', '99887766000155');
    }

    public function test_supplier_document_is_unique_within_company(): void
    {
        $user = $this->userWithRole('developer');
        $company = Company::factory()->create();
        Supplier::factory()->create([
            'company_id' => $company->id,
            'document' => '11222333000144',
        ]);

        $this->actingAs($user)->postJson(route('api.suppliers.store'), [
            'company_id' => $company->id,
            'name' => 'Fornecedor duplicado',
            'document' => '11222333000144',
            'status' => 'active',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);
    }

    public function test_supplier_with_financial_entry_cannot_be_deleted(): void
    {
        $user = $this->userWithRole('developer');
        $supplier = Supplier::factory()->create();
        $supplier->financialEntries()->create([
            'company_id' => $supplier->company_id,
            'direction' => FinancialDirection::Payable,
            'status' => 'open',
            'description' => 'Despesa vinculada ao fornecedor',
            'gross_amount' => 10000,
            'net_amount' => 10000,
            'settled_amount' => 0,
            'balance_amount' => 10000,
            'currency' => 'BRL',
        ]);

        $this->assertSame(['financial_entries'], $supplier->deletionBlockers());

        $this->actingAs($user)->delete(route('suppliers.destroy', $supplier))
            ->assertForbidden();

        $this->assertModelExists($supplier);
    }

    public function test_archived_supplier_cannot_be_used_in_new_financial_entry(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create([
            'company_id' => $company->id,
            'status' => 'archived',
        ]);

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'description' => 'Despesa com fornecedor arquivado',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 50000,
                ],
            ],
        ]);
    }

    private function userWithRole(string $roleSlug, bool $seedPermissions = false): User
    {
        $this->seed(RolesSeeder::class);

        if ($seedPermissions) {
            $this->seed(PermissionsSeeder::class);
        }

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
