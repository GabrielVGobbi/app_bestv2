<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Seeders;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CancelFinancialEntry;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Actions\Settlements\ReverseFinancialSettlement;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountNature;
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
use App\Modules\Finance\Models\FinancialSettlementComponent;
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

    private const string MARKETING_SUPPLIER_DOCUMENT = 'BP-SUP-FIN-DEMO-MKT';

    private User $actor;

    private Company $company;

    private Client $client;

    private Supplier $supplier;

    private Supplier $marketingSupplier;

    private FinancialAccount $bankAccount;

    private FinancialAccount $cashAccount;

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

            $this->actor = $this->user();
            $this->company = Company::query()->where('nickname', 'Best Play')->firstOrFail();
            $this->actor->companies()->syncWithoutDetaching([
                $this->company->id => ['access_source' => 'seeder'],
            ]);

            Auth::login($this->actor);

            try {
                $this->client = $this->makeClient();
                $this->supplier = $this->makeSupplier(
                    document: self::SUPPLIER_DOCUMENT,
                    name: 'Fornecedor Financeiro Demo',
                    email: 'fornecedor.finance.demo@bestplay.local',
                    phone: '11990000004',
                );
                $this->marketingSupplier = $this->makeSupplier(
                    document: self::MARKETING_SUPPLIER_DOCUMENT,
                    name: 'Agencia de Midia Demo',
                    email: 'midia.finance.demo@bestplay.local',
                    phone: '11990000005',
                );
                $this->bankAccount = $this->financialAccount(
                    name: self::PREFIX.'Conta operacional',
                    type: 'bank_account',
                    institutionName: 'Banco Demo',
                    accountNumber: '100200',
                    openingBalance: 5_000_000,
                );
                $this->cashAccount = $this->financialAccount(
                    name: self::PREFIX.'Caixa',
                    type: 'cash',
                    institutionName: null,
                    accountNumber: null,
                    openingBalance: 250_000,
                );

                $this->seedReceivables();
                $this->seedPayables();
            } finally {
                Auth::logout();
            }
        });

        $this->command?->info('Cenario financeiro demo criado: a pagar, a receber, parcelas, baixas, vencidos, cancelados e estornos.');
        $this->command?->line('Login demo: '.self::USER_EMAIL.' / password');
    }

    private function seedReceivables(): void
    {
        $income = $this->category('REC-SERV');
        $salesIncome = $this->category('REC-VEND');

        $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel aberto sem parcelamento',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->addDays(10)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 120_000),
            ],
        ]);

        $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel parcelado em aberto',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 240_000),
            ],
            'installments' => [
                $this->installment(1, today()->addDays(7), 80_000),
                $this->installment(2, today()->addDays(37), 80_000),
                $this->installment(3, today()->addDays(67), 80_000),
            ],
        ]);

        $partialInstallments = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel parcelado com primeira parcela paga',
            'issue_date' => today()->subDays(5)->toDateString(),
            'competence_date' => today()->subDays(5)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 300_000),
            ],
            'installments' => [
                $this->installment(1, today()->subDays(2), 100_000),
                $this->installment(2, today()->addDays(28), 100_000),
                $this->installment(3, today()->addDays(58), 100_000),
            ],
        ]);
        $this->settle($partialInstallments, 100_000, $this->paymentMethod('pix'), $this->bankAccount, today()->subDay(), installmentNumber: 1);

        $paidInstallments = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $salesIncome->id,
            'description' => self::PREFIX.'Recebivel parcelado liquidado',
            'issue_date' => today()->subDays(90)->toDateString(),
            'competence_date' => today()->subDays(90)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 150_000),
            ],
            'installments' => [
                $this->installment(1, today()->subDays(60), 50_000),
                $this->installment(2, today()->subDays(30), 50_000),
                $this->installment(3, today()->subDays(5), 50_000),
            ],
        ]);
        $this->settle($paidInstallments, 50_000, $this->paymentMethod('pix'), $this->bankAccount, today()->subDays(58), installmentNumber: 1);
        $this->settle($paidInstallments, 50_000, $this->paymentMethod('pix'), $this->bankAccount, today()->subDays(28), installmentNumber: 2);
        $this->settle($paidInstallments, 50_000, $this->paymentMethod('bank_transfer'), $this->bankAccount, today()->subDays(3), installmentNumber: 3);

        $pixPaid = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel liquidado no Pix',
            'issue_date' => today()->subDays(3)->toDateString(),
            'competence_date' => today()->subDays(3)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 85_000),
            ],
        ]);
        $this->settle($pixPaid, 85_000, $this->paymentMethod('pix'), $this->bankAccount, today()->subDay());

        $creditCardPaid = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Credito liquidado no cartao',
            'issue_date' => today()->subDays(2)->toDateString(),
            'competence_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->addDays(5)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 90_000),
            ],
        ]);
        $this->settle($creditCardPaid, 90_000, $this->paymentMethod('credit_card'), $this->bankAccount, today()->subDay());

        $boletoPaid = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel liquidado no boleto',
            'issue_date' => today()->subDays(12)->toDateString(),
            'competence_date' => today()->subDays(12)->toDateString(),
            'due_date' => today()->subDays(4)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 73_500),
            ],
        ]);
        $this->settle($boletoPaid, 73_500, $this->paymentMethod('boleto'), $this->bankAccount, today()->subDays(4));

        $cashPaid = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel liquidado em dinheiro',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 25_000),
            ],
        ]);
        $this->settle($cashPaid, 25_000, $this->paymentMethod('cash'), $this->cashAccount, today());

        $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel vencido nao pago',
            'issue_date' => today()->subDays(25)->toDateString(),
            'competence_date' => today()->subDays(25)->toDateString(),
            'due_date' => today()->subDays(5)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 175_000),
            ],
        ]);

        $overduePartial = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel vencido com baixa parcial',
            'issue_date' => today()->subDays(40)->toDateString(),
            'competence_date' => today()->subDays(40)->toDateString(),
            'due_date' => today()->subDays(10)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 210_000),
            ],
        ]);
        $this->settle($overduePartial, 80_000, $this->paymentMethod('pix'), $this->bankAccount, today()->subDays(8));

        $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel com desconto comercial',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->addDays(15)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 180_000),
                $this->component(FinancialAmountComponentCode::Discount, FinancialAmountOperation::Subtract, 18_000),
                [
                    'code' => FinancialAmountComponentCode::Tax,
                    'operation' => FinancialAmountOperation::None,
                    'nature' => FinancialAmountNature::Informational,
                    'affects_net' => false,
                    'amount' => 27_000,
                    'label' => 'ISS informado',
                ],
            ],
        ]);

        $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel com juros e multa',
            'issue_date' => today()->subDays(20)->toDateString(),
            'competence_date' => today()->subDays(20)->toDateString(),
            'due_date' => today()->addDays(3)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 200_000),
                $this->component(FinancialAmountComponentCode::Interest, FinancialAmountOperation::Add, 15_000),
                $this->component(FinancialAmountComponentCode::Penalty, FinancialAmountOperation::Add, 5_000),
            ],
        ]);

        $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel a vencer hoje',
            'issue_date' => today()->subDays(7)->toDateString(),
            'competence_date' => today()->subDays(7)->toDateString(),
            'due_date' => today()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 48_000),
            ],
        ]);

        $canceledReceivable = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel cancelado',
            'issue_date' => today()->subDays(6)->toDateString(),
            'competence_date' => today()->subDays(6)->toDateString(),
            'due_date' => today()->addDays(20)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 55_000),
            ],
        ]);
        app(CancelFinancialEntry::class)->handle($canceledReceivable, 'Cancelamento demo do seeder financeiro.');

        $reversedReceivable = $this->entry([
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $this->client->id,
            'category_id' => $income->id,
            'description' => self::PREFIX.'Recebivel liquidado e estornado',
            'issue_date' => today()->subDays(8)->toDateString(),
            'competence_date' => today()->subDays(8)->toDateString(),
            'due_date' => today()->subDays(1)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 62_000),
            ],
        ]);
        $reversedSettlement = $this->settle(
            $reversedReceivable,
            62_000,
            $this->paymentMethod('pix'),
            $this->bankAccount,
            today()->subDays(1),
        );
        app(ReverseFinancialSettlement::class)->handle($reversedSettlement, [
            'reason' => 'Estorno demo do seeder financeiro.',
        ], $this->actor);
    }

    private function seedPayables(): void
    {
        $operating = $this->category('DESP-OPER');
        $marketing = $this->category('DESP-MKT');
        $commission = $this->category('DESP-COM');
        $taxes = $this->category('DESP-TAX');

        $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel nao pago em aberto',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->addDays(12)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 150_000),
            ],
        ]);

        $paidPayable = $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel liquidado',
            'issue_date' => today()->subDays(4)->toDateString(),
            'competence_date' => today()->subDays(4)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 65_000),
            ],
        ]);
        $this->settle($paidPayable, 65_000, $this->paymentMethod('pix'), $this->bankAccount, today());

        $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel parcelado em aberto',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 240_000),
            ],
            'installments' => [
                $this->installment(1, today()->addDays(15), 40_000),
                $this->installment(2, today()->addDays(45), 40_000),
                $this->installment(3, today()->addDays(75), 40_000),
                $this->installment(4, today()->addDays(105), 40_000),
                $this->installment(5, today()->addDays(135), 40_000),
                $this->installment(6, today()->addDays(165), 40_000),
            ],
        ]);

        $partialPayable = $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel parcelado com parcelas pagas',
            'issue_date' => today()->subDays(70)->toDateString(),
            'competence_date' => today()->subDays(70)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 240_000),
            ],
            'installments' => [
                $this->installment(1, today()->subDays(50), 40_000),
                $this->installment(2, today()->subDays(20), 40_000),
                $this->installment(3, today()->addDays(10), 40_000),
                $this->installment(4, today()->addDays(40), 40_000),
                $this->installment(5, today()->addDays(70), 40_000),
                $this->installment(6, today()->addDays(100), 40_000),
            ],
        ]);
        $this->settle($partialPayable, 40_000, $this->paymentMethod('bank_transfer'), $this->bankAccount, today()->subDays(48), installmentNumber: 1);
        $this->settle($partialPayable, 40_000, $this->paymentMethod('pix'), $this->bankAccount, today()->subDays(18), installmentNumber: 2);

        $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel vencido nao pago',
            'issue_date' => today()->subDays(30)->toDateString(),
            'competence_date' => today()->subDays(30)->toDateString(),
            'due_date' => today()->subDays(8)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 97_500),
            ],
        ]);

        $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $taxes->id,
            'description' => self::PREFIX.'Pagavel com retencao de imposto',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->addDays(20)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 250_000),
                $this->component(FinancialAmountComponentCode::Withholding, FinancialAmountOperation::Subtract, 37_500),
            ],
        ]);

        $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->marketingSupplier->id,
            'category_id' => $marketing->id,
            'description' => self::PREFIX.'Pagavel de marketing',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->addDays(18)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 320_000),
            ],
        ]);

        $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->marketingSupplier->id,
            'category_id' => $commission->id,
            'description' => self::PREFIX.'Pagavel de comissao',
            'issue_date' => today()->toDateString(),
            'competence_date' => today()->toDateString(),
            'due_date' => today()->addDays(5)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 45_000),
            ],
        ]);

        $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $taxes->id,
            'description' => self::PREFIX.'Pagavel de impostos',
            'issue_date' => today()->subDays(3)->toDateString(),
            'competence_date' => today()->subDays(3)->toDateString(),
            'due_date' => today()->addDays(8)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 88_000),
            ],
        ]);

        $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel a vencer hoje',
            'issue_date' => today()->subDays(10)->toDateString(),
            'competence_date' => today()->subDays(10)->toDateString(),
            'due_date' => today()->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 33_000),
            ],
        ]);

        $canceledPayable = $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel cancelado',
            'issue_date' => today()->subDays(2)->toDateString(),
            'competence_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->addDays(25)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 41_000),
            ],
        ]);
        app(CancelFinancialEntry::class)->handle($canceledPayable, 'Cancelamento demo do seeder financeiro.');

        $interestPayable = $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel liquidado com juros e multa',
            'issue_date' => today()->subDays(18)->toDateString(),
            'competence_date' => today()->subDays(18)->toDateString(),
            'due_date' => today()->subDays(3)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 100_000),
            ],
        ]);
        $this->settle(
            $interestPayable,
            100_000,
            $this->paymentMethod('bank_transfer'),
            $this->bankAccount,
            today(),
            extras: [
                'interest_amount' => 8_000,
                'penalty_amount' => 2_000,
            ],
        );

        $partialSinglePayable = $this->entry([
            'direction' => FinancialDirection::Payable,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $this->supplier->id,
            'category_id' => $operating->id,
            'description' => self::PREFIX.'Pagavel com baixa parcial',
            'issue_date' => today()->subDays(6)->toDateString(),
            'competence_date' => today()->subDays(6)->toDateString(),
            'due_date' => today()->addDays(4)->toDateString(),
            'components' => [
                $this->component(FinancialAmountComponentCode::Principal, FinancialAmountOperation::Add, 200_000),
            ],
        ]);
        $this->settle($partialSinglePayable, 75_000, $this->paymentMethod('pix'), $this->bankAccount, today()->subDay());
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

            FinancialSettlementComponent::query()
                ->whereIn('settlement_id', $settlementIds)
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

    private function makeClient(): Client
    {
        return Client::query()->updateOrCreate(
            ['email' => self::CLIENT_EMAIL],
            [
                'company_id' => $this->company->id,
                'responsible_user_id' => $this->actor->id,
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

    private function makeSupplier(string $document, string $name, string $email, string $phone): Supplier
    {
        $supplier = Supplier::query()
            ->withTrashed()
            ->updateOrCreate(
                [
                    'company_id' => $this->company->id,
                    'document' => $document,
                ],
                [
                    'name' => $name,
                    'company_name' => $name.' LTDA',
                    'email' => $email,
                    'phone' => $phone,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function entry(array $payload): FinancialEntry
    {
        return app(CreateManualFinancialEntry::class)
            ->handle([
                'company_id' => $this->company->id,
                ...$payload,
                'currency' => 'BRL',
                'metadata' => [
                    'source' => 'financial_entry_demo_seeder',
                ],
            ], $this->actor)
            ->refresh()
            ->load(['installments', 'settlements', 'amountComponents']);
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    private function settle(
        FinancialEntry $entry,
        int $amount,
        PaymentMethod $paymentMethod,
        FinancialAccount $financialAccount,
        CarbonInterface $settledAt,
        int $installmentNumber = 1,
        array $extras = [],
    ): FinancialSettlement {
        $entry->load('installments');

        $installment = $entry->installments->firstWhere('installment_number', $installmentNumber);

        if (! $installment instanceof FinancialInstallment) {
            $installment = $entry->installments->sole();
        }

        return app(CreateFinancialSettlement::class)->handle($installment, [
            'settled_at' => $settledAt->setTime(10, 0)->toDateTimeString(),
            'financial_account_id' => $financialAccount->id,
            'payment_method_id' => $paymentMethod->id,
            'gross_amount' => $amount,
            'origin' => 'manual',
            'metadata' => [
                'source' => 'financial_entry_demo_seeder',
            ],
            ...$extras,
        ], $this->actor);
    }

    private function financialAccount(
        string $name,
        string $type,
        ?string $institutionName,
        ?string $accountNumber,
        int $openingBalance,
    ): FinancialAccount {
        return FinancialAccount::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'name' => $name,
            ],
            [
                'type' => $type,
                'institution_name' => $institutionName,
                'agency_number' => $accountNumber === null ? null : '0001',
                'account_number' => $accountNumber,
                'account_digit' => $accountNumber === null ? null : '0',
                'opening_balance_amount' => $openingBalance,
                'current_balance_amount' => $openingBalance,
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
