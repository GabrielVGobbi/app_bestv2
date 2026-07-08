<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_account_pages_render_for_authorized_user(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance');
        $account = FinancialAccount::factory()->create([
            'name' => 'Banco Inter PJ',
            'type' => FinancialAccountType::BankAccount,
        ]);

        $this->actingAs($user)
            ->get(route('financial-accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('app/financial-accounts/index'));

        $this->actingAs($user)
            ->get(route('financial-accounts.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-accounts/create')
                ->has('typeOptions'));

        $this->actingAs($user)
            ->get(route('financial-accounts.show', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-accounts/show')
                ->has('financialAccountData.data'));

        $this->actingAs($user)
            ->get(route('financial-accounts.edit', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-accounts/edit')
                ->has('financialAccountData.data')
                ->has('typeOptions'));
    }

    public function test_financial_account_can_be_created_updated_and_deactivated_from_web(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post(route('financial-accounts.store'), [
                'company_id' => $company->id,
                'name' => 'Banco Inter PJ',
                'type' => FinancialAccountType::BankAccount->value,
                'institution_name' => 'Banco Inter',
                'agency_number' => '0001',
                'account_number' => '123456',
                'account_digit' => '7',
                'document_number' => '12345678000190',
                'opening_balance_amount' => 250000,
                'current_balance_amount' => 250000,
                'balance_date' => '2026-07-01',
                'currency' => 'BRL',
                'allows_negative_balance' => false,
                'active' => true,
            ])
            ->assertRedirect();

        $account = FinancialAccount::query()->where('name', 'Banco Inter PJ')->firstOrFail();

        $this->assertSame(FinancialAccountType::BankAccount, $account->type);
        $this->assertSame(250000, $account->current_balance_amount);
        $this->assertFalse($account->allows_negative_balance);

        $this->actingAs($user)
            ->put(route('financial-accounts.update', $account), [
                'company_id' => $company->id,
                'name' => 'Banco Inter Principal',
                'type' => FinancialAccountType::BankAccount->value,
                'institution_name' => 'Banco Inter',
                'agency_number' => '0001',
                'account_number' => '654321',
                'account_digit' => '9',
                'document_number' => '12345678000190',
                'opening_balance_amount' => 250000,
                'current_balance_amount' => 250000,
                'balance_date' => '2026-07-01',
                'currency' => 'BRL',
                'allows_negative_balance' => true,
                'active' => true,
            ])
            ->assertRedirect(route('financial-accounts.show', $account));

        $account->refresh();

        $this->assertSame('Banco Inter Principal', $account->name);
        $this->assertSame('654321', $account->account_number);
        $this->assertTrue($account->allows_negative_balance);

        $this->actingAs($user)
            ->patch(route('financial-accounts.status', $account), [
                'active' => false,
            ])
            ->assertRedirect();

        $this->assertFalse($account->refresh()->active);
    }

    public function test_financial_account_api_returns_resource_and_active_options(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $active = FinancialAccount::factory()->create([
            'company_id' => $company->id,
            'name' => 'Caixa financeiro',
            'type' => FinancialAccountType::Cash,
            'active' => true,
        ]);
        FinancialAccount::factory()->create([
            'company_id' => $company->id,
            'name' => 'Conta inativa',
            'active' => false,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.financial-accounts.index', [
                'filter' => [
                    'type' => FinancialAccountType::Cash->value,
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.type_label', 'Caixa');

        $this->actingAs($user)
            ->getJson(route('api.financial-accounts.options', [
                'company_id' => $company->id,
                'active' => '1',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Caixa financeiro');
    }

    public function test_financial_account_with_movements_cannot_be_deleted(): void
    {
        $user = $this->userWithRole('finance');
        $account = FinancialAccount::factory()->create();
        FinancialMovement::factory()->create([
            'company_id' => $account->company_id,
            'financial_account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->delete(route('financial-accounts.destroy', $account))
            ->assertSessionHasErrors('financial_account');

        $this->assertModelExists($account);
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
