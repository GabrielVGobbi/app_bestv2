<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_entries', function (Blueprint $table): void {
            $table->unique(
                ['company_id', 'direction', 'originable_type', 'originable_id'],
                'financial_entries_origin_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('financial_entries', function (Blueprint $table): void {
            $table->dropUnique('financial_entries_origin_unique');
        });
    }
};
