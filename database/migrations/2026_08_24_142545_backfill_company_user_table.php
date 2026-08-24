<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $companyIds = DB::table('companies')->orderBy('id')->pluck('id');

        if ($companyIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($companyIds): void {
                $now = now();
                $rows = [];

                foreach ($users as $user) {
                    foreach ($companyIds as $companyId) {
                        $rows[] = [
                            'company_id' => $companyId,
                            'user_id' => $user->id,
                            'access_source' => 'legacy_all_companies',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('company_user')->insertOrIgnore($rows);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('company_user')
            ->where('access_source', 'legacy_all_companies')
            ->delete();
    }
};
