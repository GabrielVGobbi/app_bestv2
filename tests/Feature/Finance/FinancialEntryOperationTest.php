<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Clients\Models\Client;
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
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
                ->where('amountAdjustmentOptions', [
                    ['label' => 'Desconto', 'value' => 'discount', 'operation' => 'subtract', 'nature' => 'deduction', 'affects_net' => true, 'requires_description' => false],
                    ['label' => 'Imposto', 'value' => 'tax', 'operation' => 'add', 'nature' => 'addition', 'affects_net' => true, 'requires_description' => false],
                    ['label' => 'Juros', 'value' => 'interest', 'operation' => 'add', 'nature' => 'addition', 'affects_net' => true, 'requires_description' => false],
                    ['label' => 'Multa', 'value' => 'penalty', 'operation' => 'add', 'nature' => 'addition', 'affects_net' => true, 'requires_description' => false],
                    ['label' => 'Taxa', 'value' => 'fee', 'operation' => 'add', 'nature' => 'addition', 'affects_net' => true, 'requires_description' => false],
                    ['label' => 'Retenção', 'value' => 'withholding', 'operation' => 'subtract', 'nature' => 'deduction', 'affects_net' => true, 'requires_description' => false],
                    ['label' => 'Informativo', 'value' => 'informational', 'operation' => 'none', 'nature' => 'informational', 'affects_net' => false, 'requires_description' => false],
                    ['label' => 'Outro', 'value' => 'other', 'operation' => null, 'nature' => 'addition', 'affects_net' => true, 'requires_description' => true],
                ])
                ->has('counterpartyTypeOptions')
                ->where('counterpartyTypeByDirection.receivable', Client::class)
                ->where('counterpartyTypeByDirection.payable', Supplier::class));

        $this->actingAs($user)
            ->get(route('financial-entries.show', $entry))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-entries/show')
                ->has('entryData.data')
                ->has('entryData.data.category')
                ->missing('entryData.data.category.data'));
    }

    public function test_financial_entry_request_rejects_component_domain_invariants_on_specific_fields(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->income()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->from(route('financial-entries.create'))
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable->value,
                'counterparty_type' => Client::class,
                'counterparty_id' => $client->id,
                'category_id' => $category->id,
                'description' => 'Recebível com ajustes inválidos',
                'due_date' => now()->addWeek()->toDateString(),
                'components' => [
                    [
                        'code' => FinancialAmountComponentCode::Principal->value,
                        'operation' => FinancialAmountOperation::Add->value,
                        'amount' => 100000,
                    ],
                    [
                        'code' => FinancialAmountComponentCode::Interest->value,
                        'operation' => FinancialAmountOperation::Subtract->value,
                        'amount' => 1000,
                    ],
                    [
                        'code' => FinancialAmountComponentCode::Other->value,
                        'label' => '   ',
                        'operation' => FinancialAmountOperation::Add->value,
                        'amount' => 500,
                    ],
                ],
            ])
            ->assertRedirect(route('financial-entries.create'))
            ->assertSessionHasErrors([
                'components.1.operation',
                'components.2.label',
            ]);

        $this->assertSame(0, FinancialEntry::query()->count());
    }

    public function test_financial_entry_can_be_created_from_web_without_sales_origin(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable->value,
                'counterparty_type' => Client::class,
                'counterparty_id' => $client->id,
                'category_id' => $category->id,
                'description' => 'Recebivel manual web',
                'due_date' => now()->addWeek()->toDateString(),
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

        $entry = FinancialEntry::query()
            ->with(['amountComponents', 'installments'])
            ->firstOrFail();

        $response->assertRedirect(route('financial-entries.show', $entry));
        $this->assertNull($entry->originable_type);
        $this->assertNull($entry->originable_id);
        $this->assertSame($client->getMorphClass(), $entry->counterparty_type);
        $this->assertSame($client->id, $entry->counterparty_id);
        $this->assertSame(100000, $entry->gross_amount);
        $this->assertSame(90000, $entry->net_amount);
        $this->assertSame(90000, $entry->balance_amount);
        $this->assertSame(FinancialEntryStatus::Open, $entry->status);
        $this->assertCount(2, $entry->amountComponents);
        $this->assertCount(1, $entry->installments);
    }

    public function test_financial_entry_can_be_created_from_web_with_manual_installments(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable->value,
                'counterparty_type' => Client::class,
                'counterparty_id' => $client->id,
                'category_id' => $category->id,
                'description' => 'Recebivel web parcelado',
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
                'installments' => [
                    [
                        'installment_number' => 1,
                        'due_date' => '2026-07-20',
                        'amount' => 40000,
                    ],
                    [
                        'installment_number' => 2,
                        'due_date' => '2026-08-20',
                        'amount' => 50000,
                    ],
                ],
            ]);

        $entry = FinancialEntry::query()
            ->with('installments')
            ->firstOrFail();

        $response->assertRedirect(route('financial-entries.show', $entry));
        $this->assertSame(90000, $entry->net_amount);
        $this->assertSame('2026-07-20', $entry->due_date->toDateString());
        $this->assertCount(2, $entry->installments);
        $this->assertSame([40000, 50000], $entry->installments->pluck('original_amount')->all());
    }

    public function test_financial_entry_show_exposes_installments_with_settlement_permission(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
        ]);

        $this->actingAs($user)->post(route('financial-entries.store'), [
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable->value,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'category_id' => $category->id,
            'description' => 'Pagavel web parcelado para baixa',
            'due_date' => '2026-07-28',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal->value,
                    'operation' => FinancialAmountOperation::Add->value,
                    'amount' => 44444,
                ],
            ],
            'installments' => [
                [
                    'installment_number' => 1,
                    'due_date' => '2026-07-28',
                    'amount' => 14814,
                ],
                [
                    'installment_number' => 2,
                    'due_date' => '2026-08-28',
                    'amount' => 14814,
                ],
                [
                    'installment_number' => 3,
                    'due_date' => '2026-09-28',
                    'amount' => 14816,
                ],
            ],
        ])->assertRedirect();

        $entry = FinancialEntry::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('financial-entries.show', $entry))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-entries/show')
                ->has('entryData.data')
                ->where('entryData.data.permissions.can_settle', true));

        Sanctum::actingAs($user);

        $this->getJson(route('api.financial-entries.show', $entry))
            ->assertOk()
            ->assertJsonCount(3, 'data.installments')
            ->assertJsonPath('data.installments.0.balance_amount', 14814)
            ->assertJsonPath('data.installments.0.permissions.can_settle', true);
    }

    public function test_financial_entry_web_requires_counterparty_for_manual_entry(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $category = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
        ]);

        $this->actingAs($user)
            ->from(route('financial-entries.create'))
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable->value,
                'category_id' => $category->id,
                'description' => 'Recebivel sem cliente',
                'due_date' => now()->addWeek()->toDateString(),
                'components' => [
                    [
                        'code' => FinancialAmountComponentCode::Principal->value,
                        'operation' => FinancialAmountOperation::Add->value,
                        'amount' => 100000,
                    ],
                ],
            ])
            ->assertRedirect(route('financial-entries.create'))
            ->assertSessionHasErrors(['counterparty_type', 'counterparty_id']);

        $this->assertSame(0, FinancialEntry::query()->count());
    }

    public function test_financial_entry_web_rejects_receivable_with_supplier_counterparty(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
        ]);

        $this->actingAs($user)
            ->from(route('financial-entries.create'))
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable->value,
                'counterparty_type' => Supplier::class,
                'counterparty_id' => $supplier->id,
                'category_id' => $category->id,
                'description' => 'Recebivel com fornecedor',
                'due_date' => now()->addWeek()->toDateString(),
                'components' => [
                    [
                        'code' => FinancialAmountComponentCode::Principal->value,
                        'operation' => FinancialAmountOperation::Add->value,
                        'amount' => 100000,
                    ],
                ],
            ])
            ->assertRedirect(route('financial-entries.create'))
            ->assertSessionHasErrors(['counterparty_type']);

        $this->assertSame(0, FinancialEntry::query()->count());
    }

    public function test_financial_entry_web_rejects_payable_with_client_counterparty(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
        ]);

        $this->actingAs($user)
            ->from(route('financial-entries.create'))
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Payable->value,
                'counterparty_type' => Client::class,
                'counterparty_id' => $client->id,
                'category_id' => $category->id,
                'description' => 'Despesa com cliente',
                'due_date' => now()->addWeek()->toDateString(),
                'components' => [
                    [
                        'code' => FinancialAmountComponentCode::Principal->value,
                        'operation' => FinancialAmountOperation::Add->value,
                        'amount' => 100000,
                    ],
                ],
            ])
            ->assertRedirect(route('financial-entries.create'))
            ->assertSessionHasErrors(['counterparty_type']);

        $this->assertSame(0, FinancialEntry::query()->count());
    }

    public function test_financial_entry_web_can_create_payable_with_supplier_counterparty(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('financial-entries.store'), [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Payable->value,
                'counterparty_type' => Supplier::class,
                'counterparty_id' => $supplier->id,
                'category_id' => $category->id,
                'description' => 'Despesa com fornecedor',
                'due_date' => now()->addWeek()->toDateString(),
                'components' => [
                    [
                        'code' => FinancialAmountComponentCode::Principal->value,
                        'operation' => FinancialAmountOperation::Add->value,
                        'amount' => 100000,
                    ],
                ],
            ]);

        $entry = FinancialEntry::query()->firstOrFail();

        $response->assertRedirect(route('financial-entries.show', $entry));
        $this->assertSame($supplier->getMorphClass(), $entry->counterparty_type);
        $this->assertSame($supplier->id, $entry->counterparty_id);
        $this->assertSame(FinancialDirection::Payable, $entry->direction);
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
            ->assertJsonPath('data.0.status_presenter.label', 'Em aberto')
            ->assertJsonPath('data.0.status_presenter.color', 'amber');

        $this->getJson(route('api.financial-entries.show', $entry))
            ->assertOk()
            ->assertJsonPath('data.uuid', $entry->uuid)
            ->assertJsonPath('data.origin_kind', 'manual');
    }

    public function test_financial_entry_api_serializes_categories_with_constant_query_count(): void
    {
        $user = $this->userWithRole('finance');
        Sanctum::actingAs($user);

        $company = Company::factory()->create();
        $parent = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
            'name' => 'Receitas',
            'slug' => 'receitas-query-budget',
        ]);
        $category = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
            'parent_id' => $parent->id,
            'name' => 'Assinaturas',
            'slug' => 'assinaturas-query-budget',
        ]);

        FinancialEntry::factory()->count(10)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
        ]);

        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->getJson(route('api.financial-entries.index', ['per_page' => 30]))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.category.full_path', 'Receitas > Assinaturas');

        $this->assertLessThanOrEqual(
            10,
            count($queries),
            'A listagem financeira voltou a executar consultas por registro: '.implode(' | ', $queries),
        );
    }

    public function test_financial_entry_api_filters_by_category_and_counterparty(): void
    {
        $user = $this->userWithRole('finance');
        Sanctum::actingAs($user);

        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $otherClient = Client::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
        ]);
        $otherCategory = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
        ]);

        $matchingEntry = FinancialEntry::factory()->create([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => $client->getMorphClass(),
            'counterparty_id' => $client->id,
            'category_id' => $category->id,
            'description' => 'Recebivel filtrado',
        ]);

        FinancialEntry::factory()->create([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => $otherClient->getMorphClass(),
            'counterparty_id' => $otherClient->id,
            'category_id' => $category->id,
            'description' => 'Recebivel fora do filtro',
        ]);

        $this->getJson(route('api.financial-entries.index', [
            'filter' => [
                'category_id' => $category->id,
                'counterparty_id' => $client->id,
            ],
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $matchingEntry->uuid);
    }

    public function test_financial_entry_api_filters_by_morph_counterparty_option(): void
    {
        $user = $this->userWithRole('finance');
        Sanctum::actingAs($user);

        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $matchingEntry = FinancialEntry::factory()->create([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => $client->getMorphClass(),
            'counterparty_id' => $client->id,
            'description' => 'Recebivel por cliente',
        ]);

        FinancialEntry::factory()->create([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => $supplier->getMorphClass(),
            'counterparty_id' => $supplier->id,
            'description' => 'Pagavel por fornecedor',
        ]);

        $this->getJson(route('api.financial-entries.index', [
            'filter' => [
                'counterparty' => $client->getMorphClass().'|'.$client->id,
            ],
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $matchingEntry->uuid);
    }

    public function test_financial_counterparty_options_return_morph_values_by_direction(): void
    {
        $user = $this->userWithRole('finance');
        Sanctum::actingAs($user);

        $company = Company::factory()->create();
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Ana Recebimentos',
            'company_name' => 'Cliente Opcao',
        ]);
        $supplier = Supplier::factory()->create([
            'company_id' => $company->id,
            'name' => 'Beta Pagamentos',
            'company_name' => 'Fornecedor Opcao',
        ]);

        $this->getJson(route('api.financial-counterparties.options', [
            'company_id' => $company->id,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'value' => $client->getMorphClass().'|'.$client->id,
                'label' => 'Ana Recebimentos',
                'description' => 'Cliente - Cliente Opcao',
            ])
            ->assertJsonFragment([
                'value' => $supplier->getMorphClass().'|'.$supplier->id,
                'label' => 'Beta Pagamentos',
                'description' => 'Fornecedor - Fornecedor Opcao',
            ]);

        $this->getJson(route('api.financial-counterparties.options', [
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable->value,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', $client->getMorphClass().'|'.$client->id);

        $this->getJson(route('api.financial-counterparties.options', [
            'company_id' => $company->id,
            'values' => [$supplier->getMorphClass().'|'.$supplier->id],
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', $supplier->getMorphClass().'|'.$supplier->id)
            ->assertJsonPath('data.0.label', 'Beta Pagamentos');
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
