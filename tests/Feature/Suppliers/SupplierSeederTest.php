<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Modules\Companies\Models\Company;
use App\Modules\Suppliers\Database\Seeders\SupplierSeeder;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_seeder_creates_finance_ready_suppliers_for_each_company(): void
    {
        $this->seed(SupplierSeeder::class);

        $companyCount = Company::query()->count();

        $this->assertSame(3, $companyCount);
        $this->assertSame($companyCount * 6, Supplier::query()->count());

        $supplier = Supplier::query()
            ->where('document', 'BP-SUP-META-ADS')
            ->where('company_id', Company::query()->where('cnpj', '36569969000125')->value('id'))
            ->firstOrFail();

        $this->assertSame('Meta Ads', $supplier->name);
        $this->assertSame('active', $supplier->status);
        $this->assertSame('paid_media', $supplier->metadata['segment']);
    }

    public function test_supplier_seeder_is_idempotent(): void
    {
        $this->seed(SupplierSeeder::class);
        $this->seed(SupplierSeeder::class);

        $this->assertSame(18, Supplier::query()->count());
    }
}
