<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultCategories = [
            'receivable' => $this->defaultCategory('SYS-UNCAT-REC', 'Receitas não classificadas', 'income'),
            'payable' => $this->defaultCategory('SYS-UNCAT-PAY', 'Despesas não classificadas', 'expense'),
        ];

        DB::table('financial_entries')->orderBy('id')->chunkById(200, function ($entries) use ($defaultCategories): void {
            foreach ($entries as $entry) {
                $categoryId = $this->leafCategoryId((int) ($entry->category_id ?? 0))
                    ?? $this->firstInstallmentLeafCategoryId((int) $entry->id)
                    ?? $defaultCategories[$entry->direction];
                $dueDate = $entry->due_date
                    ?? DB::table('financial_installments')->where('financial_entry_id', $entry->id)->whereNotNull('due_date')->min('due_date')
                    ?? $entry->issue_date
                    ?? $entry->competence_date
                    ?? Carbon::parse($entry->created_at)->toDateString();

                if ((int) ($entry->category_id ?? 0) !== $categoryId || $entry->due_date === null) {
                    $this->record('entry', $entry, $categoryId, (string) $dueDate);
                    DB::table('financial_entries')->where('id', $entry->id)->update([
                        'category_id' => $categoryId,
                        'due_date' => $dueDate,
                    ]);
                }

                DB::table('financial_installments')
                    ->where('financial_entry_id', $entry->id)
                    ->orderBy('id')
                    ->eachById(function (object $installment) use ($categoryId, $dueDate): void {
                        $installmentCategoryId = $this->leafCategoryId((int) ($installment->category_id ?? 0)) ?? $categoryId;
                        $installmentDueDate = $installment->due_date ?? $dueDate;

                        if ((int) ($installment->category_id ?? 0) === $installmentCategoryId && $installment->due_date !== null) {
                            return;
                        }

                        $this->record('installment', $installment, $installmentCategoryId, (string) $installmentDueDate);
                        DB::table('financial_installments')->where('id', $installment->id)->update([
                            'category_id' => $installmentCategoryId,
                            'due_date' => $installmentDueDate,
                        ]);
                    });
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('financial_operational_backfills')->orderByDesc('id')->each(function (object $backfill): void {
            $table = $backfill->record_type === 'entry' ? 'financial_entries' : 'financial_installments';

            DB::table($table)->where('id', $backfill->record_id)->update([
                'category_id' => $backfill->original_category_id,
                'due_date' => $backfill->original_due_date,
            ]);
        });

        DB::table('financial_operational_backfills')->delete();

        DB::table('financial_categories')
            ->whereIn('code', ['SYS-UNCAT-REC', 'SYS-UNCAT-PAY'])
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('financial_entries')
                ->whereColumn('financial_entries.category_id', 'financial_categories.id'))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('financial_installments')
                ->whereColumn('financial_installments.category_id', 'financial_categories.id'))
            ->delete();
    }

    private function defaultCategory(string $code, string $name, string $type): int
    {
        $existing = DB::table('financial_categories')->whereNull('company_id')->where('code', $code)->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        return (int) DB::table('financial_categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_id' => null,
            'parent_id' => null,
            'code' => $code,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'Categoria sistêmica para normalização segura de dados legados.',
            'type' => $type,
            'color' => null,
            'sort_order' => 65535,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function leafCategoryId(int $categoryId): ?int
    {
        if ($categoryId <= 0 || ! DB::table('financial_categories')->where('id', $categoryId)->exists()) {
            return null;
        }

        return DB::table('financial_categories')->where('parent_id', $categoryId)->exists() ? null : $categoryId;
    }

    private function firstInstallmentLeafCategoryId(int $entryId): ?int
    {
        $categoryIds = DB::table('financial_installments')
            ->where('financial_entry_id', $entryId)
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->pluck('category_id');

        foreach ($categoryIds as $categoryId) {
            $leafCategoryId = $this->leafCategoryId((int) $categoryId);

            if ($leafCategoryId !== null) {
                return $leafCategoryId;
            }
        }

        return null;
    }

    private function record(string $type, object $record, int $categoryId, string $dueDate): void
    {
        DB::table('financial_operational_backfills')->insertOrIgnore([
            'record_type' => $type,
            'record_id' => $record->id,
            'original_category_id' => $record->category_id,
            'assigned_category_id' => $categoryId,
            'original_due_date' => $record->due_date,
            'assigned_due_date' => $dueDate,
            'reason' => 'operational_requirement',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
