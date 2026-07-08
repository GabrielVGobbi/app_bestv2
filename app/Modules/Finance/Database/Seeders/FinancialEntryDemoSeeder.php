<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Seeders;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialAmountComponent;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Finance\Models\FinancialSettlementAllocation;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use App\Modules\Suppliers\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FinancialEntryDemoSeeder extends Seeder
{
    private const string PREFIX = 'FIN-DEMO - ';

    private const string USER_EMAIL = 'finance.demo.seed@bestplay.local';

    private const string CLIENT_EMAIL = 'cliente.finance.demo.seed@bestplay.local';

    private const string SUPPLIER_DOCUMENT = 'BP-SUP-FIN-DEMO';

    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
            CompanySeeder::class,
            FinancialCategorySeeder::class,
            PaymentMethodSeeder::class,
        ]);

        DB::transaction(function (): void {
            $this->clearPreviousScenario();

            $user = $this->user();

            Auth::login($user);

            try {
                $company = Company::query()->where('nickname', 'Best Play')->firstOrFail();
                $client = $this->client($company, $user);
                $supplier = $this->supplier($company);
                $incomeCategory = $this->category('REC-SERV');
                $expenseCategory = $this->category('DESP-OPER');
                $creditCard = $this->paymentMethod('credit_card');
                $pix = $this->paymentMethod('pix');
                $financialAccount = $this->financialAccount($company);

                $this->openSingleReceivable($company, $client, $incomeCategory);
                $this->openInstallmentReceivable($company, $client, $incomeCategory);
                $this->creditCardPaidReceivable($company, $client, $incomeCategory, $creditCard, $financialAccount);
                $this->paidPayable($company, $supplier, $expenseCategory, $pix, $financialAccount);
                $this->unpaidPayable($company, $supplier, $expenseCategory);
                $this->overdueReceivable($company, $client, $incomeCategory);
            } finally {
                Auth::logout();
            }
        });

        $this->command?->info('Cenario financeiro demo criado: lancamentos abertos, parcelados, liquidados e vencidos.');
        $this->command?->line('Login demo: '.self::USER_EMAIL.' / password');
    }

    private function clearPreviousScenario(): void
    {
        $entryIds = FinancialEntry::query()
            ->withTrashed()
            ->where('description', 'like', self::PREFIX.'%')
            ->pluck('id');

        if ($entryIds->isEmpty()) {
            return;
        }

        $settlementIds = FinancialSettlement::query()
            ->whereIn('financial_entry_id', $entryIds)
            ->pluck('id');

        if ($settlementIds->isNotEmpty()) {
            FinancialMovement::query()
                ->where('source_type', (new FinancialSettlement)->getMorphClass())
                ->whereIn('source_id', $settlementIds)
                ->delete();

            FinancialSettlementAllocation::query()
                ->whereIn('settlement_id', $settlementIds)
                ->delete();

            FinancialSettlement::query()
                ->whereIn('id', $settlementIds)
                ->delete();
        }

        FinancialAmountComponent::query()
            ->where('amountable_type', (new FinancialEntry)->getMorphClass())
            ->whereIn('amountable_id', $entryIds)
            ->delete();

        FinancialInstallment::query()
            ->withTrashed()
            ->whereIn('financial_entry_id', $entryIds)
            ->forceDelete();

        FinancialEntry::query()
            ->withTrashed()
            ->whereIn('id', $entryIds)
            ->forceDelete();
    }

    private function user(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'name' => 'Financeiro Demo Seeder',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'must_change_password' => false,
            ],
        );

        $financeRole = Role::query()->where('slug', 'finance')->first();

        if ($financeRole instanceof Role) {
            $user->roles()->syncWithoutDetaching([$financeRole->id]);
        }

        return $user;
    }

    private function client(Company $company, User $user): Client
    {
        return Client::query()->updateOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'company_id' => $company->id,
                'responsible_user_id' => $user->id,
                'name' => 'Cliente Financeiro Demo',
                'company_name' => 'Cliente Financeiro Demo LTDA',
                'phone' => '11990000003',
                'document' => '30000000000100',
                'musical_genre' => 'Pop',
                'is_agent' => false,
                'classification' => 'Classe A',
                'status' => 'active',
            ],
        );
    }

    private function supplier(Company $company): Supplier
    {
        $supplier = Supplier::query()
            ->withTrashed()
            ->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'document' => self::SUPPLIER_DOCUMENT,
                ],
                [
                    'name' => 'Fornecedor Financeiro Demo',
                    'company_name' => 'Fornecedor Financeiro Demo LTDA',
                    'email' => 'fornecedor.finance.demo@bestplay.local',
                    'phone' => '11990000004',
                    'notes' => 'Fornecedor criado para cenarios financeiros demo.',
                    'status' => 'active',
                    'metadata' => [
                        'source' => 'financial_entry_demo_seeder',
                    ],
                ],
            );

        if ($supplier->trashed()) {
            $supplier->restore();
        }

        return $supplier;
    }

    private function category(string $code): FinancialCategory
    {
        return FinancialCategory::query()
            ->where('company_id', null)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function paymentMethod(string $code): PaymentMethod
    {
        return PaymentMethod::query()
            ->where('company_id', null)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function openSingleReceivable(Company $company, Client $client, FinancialCategory $category): FinancialEntry
    {
        return $this->entry([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'category_id' => $category->id,
            'description' => self::PREFIX.'Recebivel aberto sem parcelamento',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->addDays(10)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 120000),
            ],
        ]);
    }

    private function openInstallmentReceivable(Company $company, Client $client, FinancialCategory $category): FinancialEntry
    {
        return $this->entry([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'category_id' => $category->id,
            'description' => self::PREFIX.'Recebivel parcelado em aberto',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 240000),
            ],
            'installments' => [
                $this->installment(1, today()->addDays(7), 80000),
                $this->installment(2, today()->addDays(37), 80000),
                $this->installment(3, today()->addDays(67), 80000),
            ],
        ]);
    }

    private function creditCardPaidReceivable(
        Company $company,
        Client $client,
        FinancialCategory $category,
        PaymentMethod $paymentMethod,
        FinancialAccount $financialAccount,
    ): FinancialEntry {
        $entry = $this->entry([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'category_id' => $category->id,
            'description' => self::PREFIX.'Credito liquidado no cartao',
            'issue_date' => today()->subDays(2)->toDateString(),
            'competence_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->addDays(5)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 90000),
            ],
        ]);

        $this->settle($entry, 90000, $paymentMethod, $financialAccount, today()->subDay());

        return $entry->refresh()->load(['installments', 'settlements.movements']);
    }

    private function paidPayable(
        Company $company,
        Supplier $supplier,
        FinancialCategory $category,
        PaymentMethod $paymentMethod,
        FinancialAccount $financialAccount,
    ): FinancialEntry {
        $entry = $this->entry([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'category_id' => $category->id,
            'description' => self::PREFIX.'Pagavel liquidado',
            'issue_date' => today()->subDays(4)->toDateString(),
            'competence_date' => today()->subDays(4)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 65000),
            ],
        ]);

        $this->settle($entry, 65000, $paymentMethod, $financialAccount, today());

        return $entry->refresh()->load(['installments', 'settlements.movements']);
    }

    private function unpaidPayable(Company $company, Supplier $supplier, FinancialCategory $category): FinancialEntry
    {
        return $this->entry([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $supplier->id,
            'category_id' => $category->id,
            'description' => self::PREFIX.'Pagavel nao pago em aberto',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->addDays(12)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 150000),
            ],
        ]);
    }

    private function overdueReceivable(Company $company, Client $client, FinancialCategory $category): FinancialEntry
    {
        return $this->entry([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'category_id' => $category->id,
            'description' => self::PREFIX.'Recebivel vencido nao pago',
            'issue_date' => today()->subDays(25)->toDateString(),
            'competence_date' => today()->subDays(25)->toDateString(),
            'due_date' => today()->subDays(5)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 175000),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function entry(array $payload): FinancialEntry
    {
        return app(CreateManualFinancialEntry::class)
            ->handle([
                ...$payload,
                'currency' => 'BRL',
                'metadata' => [
                    'source' => 'financial_entry_demo_seeder',
                ],
            ])
            ->refresh()
            ->load(['installments', 'settlements']);
    }

    private function settle(
        FinancialEntry $entry,
        int $amount,
        PaymentMethod $paymentMethod,
        FinancialAccount $financialAccount,
        CarbonInterface $settledAt,
    ): FinancialSettlement {
        $installment = $entry->installments->sole();

        return app(CreateFinancialSettlement::class)->handle($installment, [
            'settled_at' => $settledAt->setTime(10, 0)->toDateTimeString(),
            'financial_account_id' => $financialAccount->id,
            'payment_method_id' => $paymentMethod->id,
            'gross_amount' => $amount,
            'origin' => 'manual',
            'metadata' => [
                'source' => 'financial_entry_demo_seeder',
            ],
        ]);
    }

    private function financialAccount(Company $company): FinancialAccount
    {
        return FinancialAccount::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => self::PREFIX.'Conta operacional',
            ],
            [
                'type' => 'bank_account',
                'institution_name' => 'Banco Demo',
                'agency_number' => '0001',
                'account_number' => '100200',
                'account_digit' => '0',
                'opening_balance_amount' => 1000000,
                'current_balance_amount' => 1000000,
                'balance_date' => today()->toDateString(),
                'currency' => 'BRL',
                'active' => true,
                'allows_negative_balance' => false,
                'metadata' => [
                    'source' => 'financial_entry_demo_seeder',
                ],
            ],
        );
    }

    /**
     * @return array{code: FinancialAmountComponentCode, operation: FinancialAmountOperation, amount: int}
     */
    private function component(
        FinancialAmountComponentCode $code,
        FinancialAmountOperation $operation,
        int $amount,
    ): array {
        return [
            'code' => $code,
            'operation' => $operation,
            'amount' => $amount,
        ];
    }

    /**
     * @return array{installment_number: int, due_date: string, amount: int}
     */
    private function installment(int $number, CarbonInterface $dueDate, int $amount): array
    {
        return [
            'installment_number' => $number,
            'due_date' => $dueDate->toDateString(),
            'amount' => $amount,
        ];
    }
}
