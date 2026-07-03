<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Finance\Database\Seeders\FinancialCategorySeeder;
use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Modules\Finance\Models\FinancialCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_default_category_tree_idempotently(): void
    {
        $this->seed(FinancialCategorySeeder::class);
        $this->seed(FinancialCategorySeeder::class);

        $incomeRoot = FinancialCategory::query()
            ->where('code', 'REC')
            ->with('children')
            ->firstOrFail();

        $this->assertSame(FinancialCategoryType::Income, $incomeRoot->type);
        $this->assertTrue($incomeRoot->hasChildren());
        $this->assertSame('Receitas', $incomeRoot->fullPath());
        $this->assertSame(0, $incomeRoot->depth());
        $this->assertSame(10, FinancialCategory::query()->count());
    }

    public function test_blocks_parent_with_different_type(): void
    {
        $income = FinancialCategory::factory()->income()->create(['name' => 'Receitas', 'slug' => 'receitas']);

        $this->expectException(ValidationException::class);

        FinancialCategory::factory()->expense()->create([
            'parent_id' => $income->id,
            'name' => 'Despesa invalida',
            'slug' => 'despesa-invalida',
        ]);
    }

    public function test_blocks_cycles_in_category_tree(): void
    {
        $parent = FinancialCategory::factory()->expense()->create(['name' => 'Despesas', 'slug' => 'despesas']);
        $child = FinancialCategory::factory()->expense()->create([
            'parent_id' => $parent->id,
            'name' => 'Marketing',
            'slug' => 'marketing',
        ]);

        $this->expectException(ValidationException::class);

        $parent->update(['parent_id' => $child->id]);
    }

    public function test_reports_deletion_blockers_for_children(): void
    {
        $parent = FinancialCategory::factory()->expense()->create(['name' => 'Despesas', 'slug' => 'despesas']);
        FinancialCategory::factory()->expense()->create([
            'parent_id' => $parent->id,
            'name' => 'Marketing',
            'slug' => 'marketing',
        ]);

        $this->assertSame(['children'], $parent->deletionBlockers());
        $this->assertFalse($parent->canBeDeleted());
    }
}
