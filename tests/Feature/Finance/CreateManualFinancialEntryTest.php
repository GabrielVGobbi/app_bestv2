<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateManualFinancialEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Os vencimentos fixos destes cenarios sao de 2026; o relogio congelado mantem as parcelas em aberto.
        Carbon::setTestNow('2026-07-01 10:00:00');
    }

    public function test_creates_manual_receivable_with_amount_components_and_net_amount(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $dueDate = now()->addDays(30)->toDateString();

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Venda manual com desconto',
            'due_date' => $dueDate,
            'currency' => 'BRL',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'label' => 'Venda',
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 100000,
                ],
                [
                    'code' => FinancialAmountComponentCode::Discount,
                    'label' => 'Desconto comercial',
                    'operation' => FinancialAmountOperation::Subtract,
                    'amount' => 10000,
                ],
            ],
        ]);

        $entry->refresh()->load(['amountComponents', 'installments']);

        $this->assertSame($company->id, $entry->company_id);
        $this->assertSame(FinancialDirection::Receivable, $entry->direction);
        $this->assertNull($entry->originable_type);
        $this->assertNull($entry->originable_id);
        $this->assertTrue($entry->isManualOrigin());
        $this->assertSame('manual', $entry->originKind());
        $this->assertSame('Manual', $entry->originLabel());
        $this->assertSame(100000, $entry->gross_amount);
        $this->assertSame(90000, $entry->net_amount);
        $this->assertSame(90000, $entry->balance_amount);
        $this->assertSame(FinancialEntryStatus::Open, $entry->status);

        $components = $entry->amountComponents->keyBy(fn ($component): string => $component->code->value);

        $this->assertCount(2, $components);
        $this->assertSame(100000, $components->get('principal')->amount);
        $this->assertSame(FinancialAmountOperation::Add, $components->get('principal')->operation);
        $this->assertSame(10000, $components->get('discount')->amount);
        $this->assertSame(FinancialAmountOperation::Subtract, $components->get('discount')->operation);

        $installment = $entry->installments->sole();

        $this->assertSame(1, $installment->installment_number);
        $this->assertSame(90000, $installment->original_amount);
        $this->assertSame(0, $installment->settled_amount);
        $this->assertSame(90000, $installment->balance_amount);
        $this->assertSame(FinancialEntryStatus::Open, $installment->status);
        $this->assertSame($dueDate, $installment->due_date->toDateString());
    }

    public function test_creates_manual_entry_with_multiple_installments_matching_net_amount(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $firstDueDate = now()->addDays(30)->toDateString();
        $secondDueDate = now()->addDays(60)->toDateString();

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Recebivel manual parcelado',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 100000,
                ],
                [
                    'code' => FinancialAmountComponentCode::Discount,
                    'operation' => FinancialAmountOperation::Subtract,
                    'amount' => 10000,
                ],
            ],
            'installments' => [
                [
                    'installment_number' => 1,
                    'due_date' => $firstDueDate,
                    'amount' => 40000,
                ],
                [
                    'installment_number' => 2,
                    'due_date' => $secondDueDate,
                    'amount' => 50000,
                ],
            ],
        ]);

        $entry->refresh()->load('installments');

        $this->assertSame($firstDueDate, $entry->due_date->toDateString());
        $this->assertSame(FinancialEntryStatus::Open, $entry->status);
        $this->assertCount(2, $entry->installments);
        $this->assertSame([1, 2], $entry->installments->pluck('installment_number')->all());
        $this->assertSame([40000, 50000], $entry->installments->pluck('original_amount')->all());
        $this->assertSame([40000, 50000], $entry->installments->pluck('balance_amount')->all());
    }

    public function test_rejects_installments_when_sum_does_not_match_net_amount(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $firstDueDate = now()->addDays(30)->toDateString();
        $secondDueDate = now()->addDays(60)->toDateString();

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Recebivel com soma incorreta',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 100000,
                ],
            ],
            'installments' => [
                [
                    'installment_number' => 1,
                    'due_date' => $firstDueDate,
                    'amount' => 40000,
                ],
                [
                    'installment_number' => 2,
                    'due_date' => $secondDueDate,
                    'amount' => 50000,
                ],
            ],
        ]);
    }

    public function test_keeps_operational_status_open_and_derives_overdue_state(): void
    {
        Carbon::setTestNow('2026-07-04 10:00:00');

        try {
            $company = Company::factory()->create();
            $client = Client::factory()->create(['company_id' => $company->id]);

            $entry = app(CreateManualFinancialEntry::class)->handle([
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable,
                'counterparty_type' => Client::class,
                'counterparty_id' => $client->id,
                'description' => 'Recebivel vencido',
                'components' => [
                    [
                        'code' => FinancialAmountComponentCode::Principal,
                        'operation' => FinancialAmountOperation::Add,
                        'amount' => 100000,
                    ],
                ],
                'installments' => [
                    [
                        'installment_number' => 1,
                        'due_date' => '2026-07-03',
                        'amount' => 100000,
                    ],
                ],
            ]);

            $entry->refresh()->load('installments');

            $this->assertSame(FinancialEntryStatus::Open, $entry->status);
            $this->assertSame(FinancialEntryStatus::Open, $entry->installments->sole()->status);
            $this->assertTrue($entry->isOverdue());
            $this->assertTrue($entry->installments->sole()->isOverdue());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_creates_manual_receivable_with_client_counterparty(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Recebivel manual com cliente',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 50000,
                ],
            ],
        ]);

        $entry->refresh()->load('counterparty');

        $this->assertTrue($entry->counterparty->is($client));
        $this->assertSame($client->getMorphClass(), $entry->counterparty_type);
        $this->assertSame($client->id, $entry->counterparty_id);
    }

    public function test_calculates_net_amount_with_tax_adding_to_total(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Recebivel com imposto somando',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 100000,
                    'position' => 0,
                ],
                [
                    'code' => FinancialAmountComponentCode::Tax,
                    'label' => 'Imposto repassado',
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 12000,
                    'position' => 1,
                ],
            ],
        ]);

        $this->assertSame(100000, $entry->gross_amount);
        $this->assertSame(12000, $entry->addition_amount);
        $this->assertSame(0, $entry->deduction_amount);
        $this->assertSame(112000, $entry->net_amount);
        $this->assertSame(112000, $entry->balance_amount);
        $this->assertSame(112000, $entry->installments->sole()->original_amount);
    }

    public function test_accepts_fixed_operations_and_flexible_manual_adjustments(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Lançamento com todas as operações permitidas',
            'components' => [
                ['code' => FinancialAmountComponentCode::Principal, 'operation' => FinancialAmountOperation::Add, 'amount' => 100000],
                ['code' => FinancialAmountComponentCode::Tax, 'operation' => FinancialAmountOperation::Add, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Interest, 'operation' => FinancialAmountOperation::Add, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Penalty, 'operation' => FinancialAmountOperation::Add, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Fee, 'operation' => FinancialAmountOperation::Add, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Discount, 'operation' => FinancialAmountOperation::Subtract, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Withholding, 'operation' => FinancialAmountOperation::Subtract, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Adjustment, 'operation' => FinancialAmountOperation::Add, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Adjustment, 'operation' => FinancialAmountOperation::Subtract, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Other, 'label' => 'Crédito adicional', 'operation' => FinancialAmountOperation::Add, 'amount' => 100],
                ['code' => FinancialAmountComponentCode::Other, 'label' => 'Abatimento negociado', 'operation' => FinancialAmountOperation::Subtract, 'amount' => 100],
            ],
        ]);

        $this->assertCount(11, $entry->amountComponents);
        $this->assertSame(100000, $entry->gross_amount);
        $this->assertSame(600, $entry->addition_amount);
        $this->assertSame(400, $entry->deduction_amount);
        $this->assertSame(100200, $entry->net_amount);
    }

    public function test_rejects_incompatible_fixed_operations_in_direct_action_calls(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $incompatibleOperations = [
            FinancialAmountComponentCode::Principal->value => FinancialAmountOperation::Subtract,
            FinancialAmountComponentCode::Tax->value => FinancialAmountOperation::Subtract,
            FinancialAmountComponentCode::Interest->value => FinancialAmountOperation::Subtract,
            FinancialAmountComponentCode::Penalty->value => FinancialAmountOperation::Subtract,
            FinancialAmountComponentCode::Fee->value => FinancialAmountOperation::Subtract,
            FinancialAmountComponentCode::Discount->value => FinancialAmountOperation::Add,
            FinancialAmountComponentCode::Withholding->value => FinancialAmountOperation::Add,
        ];

        foreach ($incompatibleOperations as $code => $operation) {
            try {
                app(CreateManualFinancialEntry::class)->handle([
                    'company_id' => $company->id,
                    'direction' => FinancialDirection::Receivable,
                    'counterparty_type' => Client::class,
                    'counterparty_id' => $client->id,
                    'description' => 'Lançamento com operação incompatível',
                    'components' => [
                        ['code' => FinancialAmountComponentCode::Principal, 'operation' => FinancialAmountOperation::Add, 'amount' => 100000],
                        ['code' => $code, 'operation' => $operation, 'amount' => 100],
                    ],
                ]);

                $this->fail("A operação incompatível para {$code} deveria ser rejeitada.");
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_requires_description_for_other_component_in_direct_action_calls(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        try {
            app(CreateManualFinancialEntry::class)->handle([
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable,
                'counterparty_type' => Client::class,
                'counterparty_id' => $client->id,
                'description' => 'Lançamento com outro ajuste sem descrição',
                'components' => [
                    ['code' => FinancialAmountComponentCode::Principal, 'operation' => FinancialAmountOperation::Add, 'amount' => 100000],
                    ['code' => FinancialAmountComponentCode::Other, 'label' => '   ', 'operation' => FinancialAmountOperation::Add, 'amount' => 100],
                ],
            ]);

            $this->fail('O componente Outro sem descrição deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('components.1.label', $exception->errors());
        }
    }

    public function test_rejects_zero_or_negative_net_amount(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Recebivel zerado por desconto',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 10000,
                ],
                [
                    'code' => FinancialAmountComponentCode::Discount,
                    'operation' => FinancialAmountOperation::Subtract,
                    'amount' => 10000,
                ],
            ],
        ]);
    }

    public function test_rejects_negative_component_amount(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'description' => 'Despesa com componente negativo',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => -10000,
                ],
            ],
        ]);
    }

    public function test_preserves_component_order_by_position(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'description' => 'Despesa com componentes ordenados',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Fee,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 1000,
                    'position' => 2,
                ],
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 10000,
                    'position' => 0,
                ],
                [
                    'code' => FinancialAmountComponentCode::Withholding,
                    'operation' => FinancialAmountOperation::Subtract,
                    'amount' => 500,
                    'position' => 1,
                ],
            ],
        ]);

        $codes = $entry
            ->refresh()
            ->amountComponents()
            ->get()
            ->pluck('code.value')
            ->all();

        $this->assertSame(['principal', 'withholding', 'fee'], $codes);
        $this->assertSame(10000, $entry->gross_amount);
        $this->assertSame(1000, $entry->addition_amount);
        $this->assertSame(500, $entry->deduction_amount);
        $this->assertSame(10500, $entry->net_amount);
    }

    public function test_rejects_unapproved_counterparty_model_type(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => User::class,
            'counterparty_id' => $user->id,
            'description' => 'Recebivel com contraparte invalida',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 50000,
                ],
            ],
        ]);
    }

    public function test_creates_manual_payable_with_supplier_counterparty(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'description' => 'Despesa manual com fornecedor',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 50000,
                ],
            ],
        ]);

        $entry->refresh()->load('counterparty');

        $this->assertTrue($entry->counterparty->is($supplier));
        $this->assertSame($supplier->getMorphClass(), $entry->counterparty_type);
        $this->assertSame($supplier->id, $entry->counterparty_id);
    }

    public function test_creates_manual_entry_with_active_leaf_category(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
        ]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'category_id' => $category->id,
            'description' => 'Despesa categorizada',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 50000,
                ],
            ],
        ]);

        $this->assertSame($category->id, $entry->category_id);
    }

    public function test_rejects_parent_category_for_manual_entry(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $category = FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
        ]);
        FinancialCategory::factory()->expense()->create([
            'company_id' => $company->id,
            'parent_id' => $category->id,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'category_id' => $category->id,
            'description' => 'Despesa com categoria pai',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 50000,
                ],
            ],
        ]);
    }

    public function test_rejects_category_type_incompatible_with_direction(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $incomeCategory = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'category_id' => $incomeCategory->id,
            'description' => 'Despesa com categoria de receita',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 50000,
                ],
            ],
        ]);
    }

    public function test_rejects_client_counterparty_from_another_company(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Recebivel com cliente de outra empresa',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 50000,
                ],
            ],
        ]);
    }

    public function test_rejects_incomplete_external_origin_pair(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $this->expectException(ValidationException::class);

        app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Origem externa incompleta',
            'originable_id' => 123,
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => 100000,
                ],
            ],
        ]);
    }
}
