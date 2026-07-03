<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Seeders;

use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Modules\Finance\Models\FinancialCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinancialCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'REC', 'name' => 'Receitas', 'type' => FinancialCategoryType::Income, 'color' => '#16a34a', 'children' => [
                ['code' => 'REC-SERV', 'name' => 'Receita de servicos'],
                ['code' => 'REC-VEND', 'name' => 'Receita de vendas'],
            ]],
            ['code' => 'DESP', 'name' => 'Despesas', 'type' => FinancialCategoryType::Expense, 'color' => '#dc2626', 'children' => [
                ['code' => 'DESP-OPER', 'name' => 'Despesas operacionais'],
                ['code' => 'DESP-MKT', 'name' => 'Marketing e midia'],
                ['code' => 'DESP-COM', 'name' => 'Comissoes'],
                ['code' => 'DESP-TAX', 'name' => 'Impostos e taxas'],
            ]],
            ['code' => 'TRANSF', 'name' => 'Transferencias', 'type' => FinancialCategoryType::Transfer, 'color' => '#0ea5e9', 'children' => [
                ['code' => 'TRANSF-INT', 'name' => 'Transferencias internas'],
            ]],
        ];

        foreach ($categories as $index => $category) {
            $parent = $this->upsertCategory(
                code: $category['code'],
                name: $category['name'],
                type: $category['type'],
                color: $category['color'],
                sortOrder: $index,
            );

            foreach ($category['children'] as $childIndex => $child) {
                $this->upsertCategory(
                    code: $child['code'],
                    name: $child['name'],
                    type: $category['type'],
                    color: $category['color'],
                    sortOrder: $childIndex,
                    parent: $parent,
                );
            }
        }
    }

    private function upsertCategory(
        string $code,
        string $name,
        FinancialCategoryType $type,
        string $color,
        int $sortOrder,
        ?FinancialCategory $parent = null,
    ): FinancialCategory {
        return FinancialCategory::query()->updateOrCreate(
            [
                'company_id' => null,
                'code' => $code,
                'type' => $type->value,
            ],
            [
                'parent_id' => $parent?->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => null,
                'color' => $color,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );
    }
}
