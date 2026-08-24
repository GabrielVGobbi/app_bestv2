<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('financial_amount_components')->orderBy('id')->chunkById(500, function ($components): void {
            foreach ($components as $component) {
                $nature = match ($component->code) {
                    'principal' => 'principal',
                    'discount', 'withholding' => 'deduction',
                    'informational' => 'informational',
                    default => $component->operation === 'subtract' ? 'deduction' : 'addition',
                };

                DB::table('financial_amount_components')->where('id', $component->id)->update([
                    'nature' => $nature,
                    'affects_net' => $nature !== 'informational',
                    'origin' => 'legacy',
                ]);
            }
        });

        DB::table('financial_entries')->orderBy('id')->chunkById(200, function ($entries): void {
            foreach ($entries as $entry) {
                $components = $this->componentsFor((int) $entry->id);
                $principalCount = $components->where('nature', 'principal')->count();

                if ($principalCount > 1) {
                    throw new RuntimeException("Financial entry {$entry->id} has multiple principal components.");
                }

                if ($principalCount === 0) {
                    $this->insertComponent($entry, [
                        'code' => 'principal',
                        'nature' => 'principal',
                        'label' => null,
                        'operation' => 'add',
                        'amount' => (int) $entry->gross_amount,
                        'origin' => 'legacy_synthesized',
                        'position' => 0,
                    ]);
                    $components = $this->componentsFor((int) $entry->id);
                }

                $principal = (int) $components->where('nature', 'principal')->sum('amount');
                $additions = (int) $components->where('nature', 'addition')->where('affects_net', true)->sum('amount');
                $deductions = (int) $components->where('nature', 'deduction')->where('affects_net', true)->sum('amount');
                $calculatedNet = $principal + $additions - $deductions;

                if ($calculatedNet !== (int) $entry->net_amount) {
                    $difference = abs((int) $entry->net_amount - $calculatedNet);
                    $nature = (int) $entry->net_amount > $calculatedNet ? 'addition' : 'deduction';

                    $this->insertComponent($entry, [
                        'code' => 'adjustment',
                        'nature' => $nature,
                        'label' => 'Ajuste de reconciliação do legado',
                        'operation' => $nature === 'addition' ? 'add' : 'subtract',
                        'amount' => $difference,
                        'origin' => 'legacy_reconciliation',
                        'position' => 65535,
                    ]);

                    if ($nature === 'addition') {
                        $additions += $difference;
                    } else {
                        $deductions += $difference;
                    }
                }

                DB::table('financial_entries')->where('id', $entry->id)->update([
                    'composition_legacy_gross_amount' => $entry->gross_amount,
                    'gross_amount' => $principal,
                    'addition_amount' => $additions,
                    'deduction_amount' => $deductions,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('financial_entries')
            ->whereNotNull('composition_legacy_gross_amount')
            ->update(['gross_amount' => DB::raw('composition_legacy_gross_amount')]);

        DB::table('financial_amount_components')
            ->whereIn('origin', ['legacy_synthesized', 'legacy_reconciliation'])
            ->delete();
    }

    private function componentsFor(int $entryId): Collection
    {
        return DB::table('financial_amount_components')
            ->where('amountable_type', 'App\\Modules\\Finance\\Models\\FinancialEntry')
            ->where('amountable_id', $entryId)
            ->get();
    }

    /**
     * @param  array{code: string, nature: string, label: ?string, operation: string, amount: int, origin: string, position: int}  $component
     */
    private function insertComponent(object $entry, array $component): void
    {
        DB::table('financial_amount_components')->insert([
            'uuid' => (string) Str::uuid(),
            'company_id' => $entry->company_id,
            'amountable_type' => 'App\\Modules\\Finance\\Models\\FinancialEntry',
            'amountable_id' => $entry->id,
            'code' => $component['code'],
            'nature' => $component['nature'],
            'label' => $component['label'],
            'operation' => $component['operation'],
            'affects_net' => true,
            'amount' => $component['amount'],
            'origin' => $component['origin'],
            'position' => $component['position'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
