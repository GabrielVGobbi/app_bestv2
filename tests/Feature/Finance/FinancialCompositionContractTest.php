<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialCompositionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_informational_component_preserves_net_and_records_calculation_context(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => 'receivable',
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Título com tributo informativo',
            'components' => [
                ['code' => 'principal', 'nature' => 'principal', 'amount' => 10000],
                [
                    'code' => 'tax', 'nature' => 'informational', 'operation' => 'none',
                    'affects_net' => false, 'amount' => 1800, 'base_amount' => 10000,
                    'rate_basis_points' => 1800, 'calculation_method' => 'percentage',
                ],
            ],
        ]);

        $informational = $entry->amountComponents->last();

        $this->assertSame(10000, $entry->gross_amount);
        $this->assertSame(0, $entry->addition_amount);
        $this->assertSame(0, $entry->deduction_amount);
        $this->assertSame(10000, $entry->net_amount);
        $this->assertFalse($informational->affects_net);
        $this->assertSame(10000, $informational->base_amount);
        $this->assertSame(1800, $informational->rate_basis_points);
    }

    public function test_rejects_incompatible_code_nature_and_multiple_principals(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $base = [
            'company_id' => $company->id,
            'direction' => 'receivable',
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'description' => 'Composição inválida',
        ];

        foreach ([
            [['code' => 'principal', 'amount' => 10000], ['code' => 'discount', 'nature' => 'addition', 'amount' => 100]],
            [['code' => 'principal', 'amount' => 5000], ['code' => 'principal', 'amount' => 5000]],
        ] as $components) {
            try {
                app(CreateManualFinancialEntry::class)->handle([...$base, 'components' => $components]);
                $this->fail('Invalid composition should fail.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertDatabaseCount('financial_entries', 0);
    }

    public function test_legacy_backfill_preserves_net_and_installment_sum_while_reclassifying_gross(): void
    {
        $company = Company::factory()->create();
        $entry = FinancialEntry::factory()->create([
            'company_id' => $company->id,
            'gross_amount' => 11200,
            'addition_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 11200,
            'balance_amount' => 11200,
            'composition_legacy_gross_amount' => null,
        ]);
        FinancialInstallment::factory()->create([
            'company_id' => $company->id,
            'financial_entry_id' => $entry->id,
            'original_amount' => 11200,
            'balance_amount' => 11200,
        ]);

        foreach ([['principal', 10000, 0], ['tax', 1200, 1]] as [$code, $amount, $position]) {
            DB::table('financial_amount_components')->insert([
                'uuid' => (string) Str::uuid(), 'company_id' => $company->id,
                'amountable_type' => FinancialEntry::class, 'amountable_id' => $entry->id,
                'code' => $code, 'nature' => null, 'label' => null, 'operation' => 'add',
                'affects_net' => null, 'amount' => $amount, 'origin' => null, 'position' => $position,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $migration = require app_path('Modules/Finance/Database/Migrations/2026_08_24_145408_backfill_financial_entry_composition_contract.php');
        $migration->up();
        $entry->refresh();

        $this->assertSame(10000, $entry->gross_amount);
        $this->assertSame(1200, $entry->addition_amount);
        $this->assertSame(11200, $entry->net_amount);
        $this->assertSame(11200, (int) $entry->installments()->sum('original_amount'));
        $this->assertSame(11200, $entry->composition_legacy_gross_amount);
    }
}
