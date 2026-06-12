<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('addresses')) {
            return;
        }

        DB::transaction(function (): void {
            $duplicateGroups = DB::table('addresses')
                ->select('addressable_type', 'addressable_id')
                ->where('is_default', true)
                ->whereNull('deleted_at')
                ->groupBy('addressable_type', 'addressable_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicateGroups as $group) {
                $defaultIds = DB::table('addresses')
                    ->where('addressable_type', $group->addressable_type)
                    ->where('addressable_id', $group->addressable_id)
                    ->where('is_default', true)
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->pluck('id');

                $keepId = $defaultIds->first();

                if ($keepId === null) {
                    continue;
                }

                DB::table('addresses')
                    ->whereIn('id', $defaultIds->skip(1)->all())
                    ->update(['is_default' => false]);
            }
        });
    }

    public function down(): void
    {
        //
    }
};
