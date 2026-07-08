<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinancialOverdueRecalculationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_titles_overdue_after_the_day_turns(): void
    {
        Carbon::setTestNow('2026-07-06 10:00:00');

        try {
            $entry = $this->manualReceivableEntry('2026-07-06', 50000);

            $this->assertSame(FinancialEntryStatus::Open, $entry->status);
            $this->assertSame(FinancialEntryStatus::Open, $entry->installments->sole()->status);

            Carbon::setTestNow('2026-07-07 00:05:00');

            $this->artisan('finance:recalculate-overdue')
                ->assertSuccessful();

            $entry->refresh()->load('installments');

            $this->assertSame(FinancialEntryStatus::Overdue, $entry->status);
            $this->assertSame(FinancialEntryStatus::Overdue, $entry->installments->sole()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function manualReceivableEntry(string $dueDate, int $amount): FinancialEntry
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        return app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Recebível para vencimento diário',
            'due_date' => $dueDate,
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => $amount,
                ],
            ],
        ])->refresh()->load('installments');
    }
}
