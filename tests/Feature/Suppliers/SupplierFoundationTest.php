<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use App\Modules\Suppliers\Database\Seeders\SupplierSeeder;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_uses_module_contract_and_seed_is_idempotent(): void
    {
        $this->seed(SupplierSeeder::class);
        $this->seed(SupplierSeeder::class);

        $this->assertDatabaseCount('suppliers', 18);
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Meta Ads',
            'document' => 'BP-SUP-META-ADS',
            'status' => 'active',
        ]);

        $supplier = Supplier::factory()->create([
            'name' => 'Fornecedor Manual',
        ]);

        $this->assertSame('Fornecedor Manual', $supplier->name);
        $this->assertSame('supplier', Supplier::timelineType());
        $this->assertTrue($supplier->isActive());
        $this->assertDatabaseCount('suppliers', 19);
    }

    public function test_finance_role_receives_supplier_permissions(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $finance = Role::query()->where('slug', 'finance')->firstOrFail();

        $this->assertTrue($finance->permissions()->where('slug', 'suppliers.view')->exists());
        $this->assertTrue($finance->permissions()->where('slug', 'suppliers.create')->exists());
        $this->assertTrue($finance->permissions()->where('slug', 'finance.categories.view')->exists());
        $this->assertFalse($finance->permissions()->where('slug', 'finance.origins.manage')->exists());
    }
}
