<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Finance\Database\Seeders\FinancialEntryDemoSeeder;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\FinancialAmountComponent;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialEntryDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_entry_demo_seeder_creates_operational_scenarios_without_duplicates(): void
    {
        $this->artisan('db:seed', [
            '--class' => FinancialEntryDemoSeeder::class,
        ])->assertExitCode(0);

        $this->artisan('db:seed', [
            '--class' => FinancialEntryDemoSeeder::class,
        ])->assertExitCode(0);

        $entries = FinancialEntry::query()
            ->with(['installments', 'amountComponents', 'settlements.paymentMethod', 'settlements.movements'])
            ->where('description', 'like', 'FIN-DEMO - %')
            ->get()
            ->keyBy('description');

        $this->assertCount(28, $entries);
        $this->assertSame(15, $entries->where('direction', FinancialDirection::Receivable)->count());
        $this->assertSame(13, $entries->where('direction', FinancialDirection::Payable)->count());

        $openSingleReceivable = $entries->get('FIN-DEMO - Recebivel aberto sem parcelamento');
        $this->assertNotNull($openSingleReceivable);
        $this->assertSame(FinancialDirection::Receivable, $openSingleReceivable->direction);
        $this->assertSame(FinancialEntryStatus::Open, $openSingleReceivable->status);
        $this->assertSame(1, $openSingleReceivable->installments->count());
        $this->assertSame(120000, $openSingleReceivable->balance_amount);

        $openInstallmentReceivable = $entries->get('FIN-DEMO - Recebivel parcelado em aberto');
        $this->assertNotNull($openInstallmentReceivable);
        $this->assertSame(FinancialEntryStatus::Open, $openInstallmentReceivable->status);
        $this->assertSame(3, $openInstallmentReceivable->installments->count());
        $this->assertSame(240000, $openInstallmentReceivable->balance_amount);

        $partialInstallmentReceivable = $entries->get('FIN-DEMO - Recebivel parcelado com primeira parcela paga');
        $this->assertNotNull($partialInstallmentReceivable);
        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $partialInstallmentReceivable->status);
        $this->assertSame(100000, $partialInstallmentReceivable->settled_amount);
        $this->assertSame(200000, $partialInstallmentReceivable->balance_amount);

        $paidInstallmentReceivable = $entries->get('FIN-DEMO - Recebivel parcelado liquidado');
        $this->assertNotNull($paidInstallmentReceivable);
        $this->assertSame(FinancialEntryStatus::Paid, $paidInstallmentReceivable->status);
        $this->assertSame(3, $paidInstallmentReceivable->settlements->where('status', FinancialSettlementStatus::Posted)->count());
        $this->assertSame(0, $paidInstallmentReceivable->balance_amount);

        $creditCardSettlement = $entries->get('FIN-DEMO - Credito liquidado no cartao');
        $this->assertNotNull($creditCardSettlement);
        $this->assertSame(FinancialEntryStatus::Paid, $creditCardSettlement->status);
        $this->assertSame(90000, $creditCardSettlement->settled_amount);
        $this->assertSame('credit_card', $creditCardSettlement->settlements->sole()->paymentMethod?->code);
        $this->assertSame(FinancialMovementDirection::Inflow, $creditCardSettlement->settlements->sole()->movements->sole()->direction);

        $discountReceivable = $entries->get('FIN-DEMO - Recebivel com desconto comercial');
        $this->assertNotNull($discountReceivable);
        $this->assertSame(180000, $discountReceivable->gross_amount);
        $this->assertSame(18000, $discountReceivable->deduction_amount);
        $this->assertSame(162000, $discountReceivable->net_amount);
        $this->assertTrue($discountReceivable->amountComponents->contains(
            fn (FinancialAmountComponent $component): bool => $component->code === FinancialAmountComponentCode::Tax
                && $component->affects_net === false,
        ));

        $interestReceivable = $entries->get('FIN-DEMO - Recebivel com juros e multa');
        $this->assertNotNull($interestReceivable);
        $this->assertSame(200000, $interestReceivable->gross_amount);
        $this->assertSame(20000, $interestReceivable->addition_amount);
        $this->assertSame(220000, $interestReceivable->net_amount);

        $overdueReceivable = $entries->get('FIN-DEMO - Recebivel vencido nao pago');
        $this->assertNotNull($overdueReceivable);
        $this->assertSame(FinancialEntryStatus::Open, $overdueReceivable->status);
        $this->assertTrue($overdueReceivable->isOverdue());

        $overduePartial = $entries->get('FIN-DEMO - Recebivel vencido com baixa parcial');
        $this->assertNotNull($overduePartial);
        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $overduePartial->status);
        $this->assertTrue($overduePartial->isOverdue());
        $this->assertSame(80000, $overduePartial->settled_amount);

        $dueTodayReceivable = $entries->get('FIN-DEMO - Recebivel a vencer hoje');
        $this->assertNotNull($dueTodayReceivable);
        $this->assertSame(FinancialEntryStatus::Open, $dueTodayReceivable->status);
        $this->assertFalse($dueTodayReceivable->isOverdue());

        $canceledReceivable = $entries->get('FIN-DEMO - Recebivel cancelado');
        $this->assertNotNull($canceledReceivable);
        $this->assertSame(FinancialEntryStatus::Canceled, $canceledReceivable->status);
        $this->assertSame(0, $canceledReceivable->balance_amount);

        $reversedReceivable = $entries->get('FIN-DEMO - Recebivel liquidado e estornado');
        $this->assertNotNull($reversedReceivable);
        $this->assertSame(FinancialEntryStatus::Open, $reversedReceivable->status);
        $this->assertSame(62000, $reversedReceivable->balance_amount);
        $this->assertSame(FinancialSettlementStatus::Reversed, $reversedReceivable->settlements->sole()->status);
        $this->assertCount(2, $reversedReceivable->settlements->sole()->movements);

        $paidPayable = $entries->get('FIN-DEMO - Pagavel liquidado');
        $this->assertNotNull($paidPayable);
        $this->assertSame(FinancialDirection::Payable, $paidPayable->direction);
        $this->assertSame(FinancialEntryStatus::Paid, $paidPayable->status);
        $this->assertSame(FinancialMovementDirection::Outflow, $paidPayable->settlements->sole()->movements->sole()->direction);

        $openPayable = $entries->get('FIN-DEMO - Pagavel nao pago em aberto');
        $this->assertNotNull($openPayable);
        $this->assertSame(FinancialEntryStatus::Open, $openPayable->status);
        $this->assertSame(0, $openPayable->settlements->count());

        $openInstallmentPayable = $entries->get('FIN-DEMO - Pagavel parcelado em aberto');
        $this->assertNotNull($openInstallmentPayable);
        $this->assertSame(6, $openInstallmentPayable->installments->count());
        $this->assertSame(240000, $openInstallmentPayable->balance_amount);

        $partialInstallmentPayable = $entries->get('FIN-DEMO - Pagavel parcelado com parcelas pagas');
        $this->assertNotNull($partialInstallmentPayable);
        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $partialInstallmentPayable->status);
        $this->assertSame(80000, $partialInstallmentPayable->settled_amount);
        $this->assertSame(160000, $partialInstallmentPayable->balance_amount);

        $withholdingPayable = $entries->get('FIN-DEMO - Pagavel com retencao de imposto');
        $this->assertNotNull($withholdingPayable);
        $this->assertSame(250000, $withholdingPayable->gross_amount);
        $this->assertSame(37500, $withholdingPayable->deduction_amount);
        $this->assertSame(212500, $withholdingPayable->net_amount);

        $interestPayable = $entries->get('FIN-DEMO - Pagavel liquidado com juros e multa');
        $this->assertNotNull($interestPayable);
        $this->assertSame(FinancialEntryStatus::Paid, $interestPayable->status);
        $this->assertSame(8000, $interestPayable->settlements->sole()->interest_amount);
        $this->assertSame(2000, $interestPayable->settlements->sole()->penalty_amount);
        $this->assertSame(110000, $interestPayable->settlements->sole()->net_amount);

        $partialPayable = $entries->get('FIN-DEMO - Pagavel com baixa parcial');
        $this->assertNotNull($partialPayable);
        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $partialPayable->status);
        $this->assertSame(75000, $partialPayable->settled_amount);
        $this->assertSame(125000, $partialPayable->balance_amount);

        $canceledPayable = $entries->get('FIN-DEMO - Pagavel cancelado');
        $this->assertNotNull($canceledPayable);
        $this->assertSame(FinancialEntryStatus::Canceled, $canceledPayable->status);

        $overduePayable = $entries->get('FIN-DEMO - Pagavel vencido nao pago');
        $this->assertNotNull($overduePayable);
        $this->assertTrue($overduePayable->isOverdue());

        $demoSettlements = FinancialSettlement::query()
            ->whereIn('financial_entry_id', $entries->pluck('id'))
            ->get();

        $this->assertSame(15, $demoSettlements->count());
        $this->assertSame(14, $demoSettlements->where('status', FinancialSettlementStatus::Posted)->count());
        $this->assertSame(1, $demoSettlements->where('status', FinancialSettlementStatus::Reversed)->count());

        $this->assertSame(16, FinancialMovement::query()
            ->where('source_type', (new FinancialSettlement)->getMorphClass())
            ->whereIn('source_id', $demoSettlements->pluck('id'))
            ->count());
    }
}
