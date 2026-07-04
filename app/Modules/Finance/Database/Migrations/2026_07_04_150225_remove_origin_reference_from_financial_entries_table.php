<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('financial_entries', 'origin_reference')) {
            return;
        }

        Schema::table('financial_entries', function (Blueprint $table): void {
            $table->dropColumn('origin_reference');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('financial_entries', 'origin_reference')) {
            return;
        }

        Schema::table('financial_entries', function (Blueprint $table): void {
            $table->string('origin_reference')->nullable()->index()->after('originable_id');
        });
    }
};
