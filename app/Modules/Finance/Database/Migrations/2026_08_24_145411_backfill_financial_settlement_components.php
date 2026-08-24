<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('financial_settlements')->orderBy('id')->chunkById(200, function ($settlements): void {
            foreach ($settlements as $settlement) {
                $components = [
                    $this->component($settlement, 'principal', 'principal', 'add', (int) $settlement->gross_amount, 0),
                ];

                foreach ([
                    ['discount', 'deduction', 'subtract', (int) $settlement->discount_amount],
                    ['interest', 'addition', 'add', (int) $settlement->interest_amount],
                    ['penalty', 'addition', 'add', (int) $settlement->penalty_amount],
                ] as $index => [$code, $nature, $operation, $amount]) {
                    if ($amount > 0) {
                        $components[] = $this->component($settlement, $code, $nature, $operation, $amount, $index + 1);
                    }
                }

                DB::table('financial_settlement_components')->insert($components);
                DB::table('financial_settlements')->where('id', $settlement->id)->update([
                    'addition_amount' => (int) $settlement->interest_amount + (int) $settlement->penalty_amount,
                    'deduction_amount' => (int) $settlement->discount_amount,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('financial_settlement_components')->where('origin', 'legacy_backfill')->delete();
    }

    /** @return array<string, mixed> */
    private function component(object $settlement, string $code, string $nature, string $operation, int $amount, int $position): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => $settlement->company_id,
            'settlement_id' => $settlement->id,
            'code' => $code,
            'nature' => $nature,
            'label' => null,
            'operation' => $operation,
            'affects_net' => true,
            'amount' => $amount,
            'origin' => 'legacy_backfill',
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
};
