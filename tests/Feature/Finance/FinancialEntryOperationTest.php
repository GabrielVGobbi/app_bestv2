<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialEntryOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_entry_pages_render_for_authorized_user(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance');
        $entry = FinancialEntry::factory()->create();

        $this->actingAs($user)
            ->get(route('financial-entries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('app/financial-entries/index'));

        $this->actingAs($user)
            ->get(route('financial-entries.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-entries/create')
                ->has('directionOptions')
                ->has('componentCodeOptions')
                ->has('componentOperationOptions')
                ->has('counterpartyTypeOptions'));

        $this->actingAs($user)
            ->get(route('financial-entries.show', $entry))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-entries/show')
                ->has('entryData.data'));
    }

    public function test_financial_entry_can_be_created_from_web_without_sales_origin(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $category = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable->value,
                'category_id' => $category->id,
                'description' => 'Recebivel manual web',
                'due_date' => '2026-07-20',
                'components' => [
                    [
                        'code' => FinancialAmountComponentCode::Principal->value,
                        'operation' => FinancialAmountOperation::Add->value,
                        'amount' => 100000,
                    ],
                    [
                        'code' => FinancialAmountComponentCode::Discount->value,
                        'operation' => FinancialAmountOperation::Subtract->value,
                        'amount' => 10000,
                    ],
                ],
            ]);

        $entry = FinancialEntry::query()->firstOrFail();

        $response->assertRedirect(route('financial-entries.show', $entry));
        $this->assertNull($entry->originable_type);
        $this->assertNull($entry->originable_id);
        $this->assertSame(100000, $entry->gross_amount);
        $this->assertSame(90000, $entry->net_amount);
        $this->assertSame(90000, $entry->balance_amount);
        $this->assertSame(FinancialEntryStatus::Open, $entry->status);
        $this->assertCount(2, $entry->amountComponents);
        $this->assertCount(1, $entry->installments);
    }

    public function test_financial_entry_api_lists_and_returns_resource_shape(): void
    {
        $user = $this->userWithRole('finance');
        Sanctum::actingAs($user);

        $entry = FinancialEntry::factory()->create([
            'description' => 'Recebivel API',
        ]);

        $this->getJson(route('api.financial-entries.index'))
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $entry->uuid)
            ->assertJsonPath('data.0.status_presenter.label', 'Em aberto');

        $this->getJson(route('api.financial-entries.show', $entry))
            ->assertOk()
            ->assertJsonPath('data.uuid', $entry->uuid)
            ->assertJsonPath('data.origin_kind', 'manual');
    }

    public function test_user_without_entry_permission_cannot_access_financial_entries(): void
    {
        $user = $this->userWithRole('commercial');

        $this->actingAs($user)
            ->get(route('financial-entries.index'))
            ->assertForbidden();
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
