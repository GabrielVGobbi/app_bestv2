<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Finance\Database\Seeders\FinancialEntryDemoSeeder;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialMovementDirection;
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
            ->with(['installments', 'settlements.paymentMethod', 'settlements.movements'])
            ->where('description', 'like', 'FIN-DEMO - %')
            ->get()
            ->keyBy('description');

        $this->assertCount(6, $entries);

        $openSingleReceivable = $entries->get('FIN-DEMO - Recebivel aberto sem parcelamento');
        $this->assertNotNull($openSingleReceivable);
        $this->assertSame(FinancialDirection::Receivable, $openSingleReceivable->direction);
        $this->assertSame(FinancialEntryStatus::Open, $openSingleReceivable->status);
        $this->assertSame(1, $openSingleReceivable->installments->count());
        $this->assertSame(120000, $openSingleReceivable->balance_amount);

        $openInstallmentReceivable = $entries->get('FIN-DEMO - Recebivel parcelado em aberto');
        $this->assertNotNull($openInstallmentReceivable);
        $this->assertSame(FinancialDirection::Receivable, $openInstallmentReceivable->direction);
        $this->assertSame(FinancialEntryStatus::Open, $openInstallmentReceivable->status);
        $this->assertSame(3, $openInstallmentReceivable->installments->count());
        $this->assertSame(240000, $openInstallmentReceivable->balance_amount);

        $creditCardSettlement = $entries->get('FIN-DEMO - Credito liquidado no cartao');
        $this->assertNotNull($creditCardSettlement);
        $this->assertSame(FinancialDirection::Receivable, $creditCardSettlement->direction);
        $this->assertSame(FinancialEntryStatus::Paid, $creditCardSettlement->status);
        $this->assertSame(90000, $creditCardSettlement->settled_amount);
        $this->assertSame(0, $creditCardSettlement->balance_amount);
        $this->assertSame('credit_card', $creditCardSettlement->settlements->sole()->paymentMethod?->code);
        $this->assertSame(FinancialMovementDirection::Inflow, $creditCardSettlement->settlements->sole()->movements->sole()->direction);

        $paidPayable = $entries->get('FIN-DEMO - Pagavel liquidado');
        $this->assertNotNull($paidPayable);
        $this->assertSame(FinancialDirection::Payable, $paidPayable->direction);
        $this->assertSame(FinancialEntryStatus::Paid, $paidPayable->status);
        $this->assertSame(FinancialMovementDirection::Outflow, $paidPayable->settlements->sole()->movements->sole()->direction);

        $unpaidPayable = $entries->get('FIN-DEMO - Pagavel nao pago em aberto');
        $this->assertNotNull($unpaidPayable);
        $this->assertSame(FinancialDirection::Payable, $unpaidPayable->direction);
        $this->assertSame(FinancialEntryStatus::Open, $unpaidPayable->status);
        $this->assertSame(0, $unpaidPayable->settled_amount);
        $this->assertSame(0, $unpaidPayable->settlements->count());

        $overdueReceivable = $entries->get('FIN-DEMO - Recebivel vencido nao pago');
        $this->assertNotNull($overdueReceivable);
        $this->assertSame(FinancialDirection::Receivable, $overdueReceivable->direction);
        $this->assertSame(FinancialEntryStatus::Open, $overdueReceivable->status);
        $this->assertTrue($overdueReceivable->isOverdue());
        $this->assertTrue($overdueReceivable->installments->sole()->due_date->isPast());

        $this->assertSame(2, FinancialSettlement::query()->count());
        $this->assertSame(2, FinancialMovement::query()->count());
    }
}
