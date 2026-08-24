<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Finance\Database\Seeders\FinancialCategorySeeder;
use App\Modules\Finance\Database\Seeders\PaymentMethodSeeder;
use App\Modules\Finance\Models\FinancialEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_foundation_uses_morph_origin_and_no_origin_type_table(): void
    {
        $this->assertFalse(Schema::hasTable('financial_origin_types'));
        $this->assertTrue(Schema::hasColumn('financial_entries', 'originable_type'));
        $this->assertTrue(Schema::hasColumn('financial_entries', 'counterparty_type'));
        $this->assertFalse(Schema::hasColumn('financial_entries', 'origin_code'));

        $entry = FinancialEntry::factory()->create();

        $this->assertTrue($entry->isManualOrigin());
        $this->assertSame('manual', $entry->originKind());
    }

    public function test_finance_v1_seeders_are_idempotent(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $this->seed(FinancialCategorySeeder::class);
        $this->seed(PaymentMethodSeeder::class);
        $this->seed(PaymentMethodSeeder::class);

        $this->assertDatabaseCount('financial_categories', 12);
        $this->assertDatabaseCount('payment_methods', 7);
    }
}
