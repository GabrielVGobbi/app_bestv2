<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('addition_amount')->default(0)->after('gross_amount');
            $table->unsignedBigInteger('deduction_amount')->default(0)->after('addition_amount');
            $table->unsignedBigInteger('composition_legacy_gross_amount')->nullable()->after('net_amount');
        });

        Schema::table('financial_amount_components', function (Blueprint $table): void {
            $table->string('nature', 24)->nullable()->after('code')->index();
            $table->boolean('affects_net')->nullable()->after('operation')->index();
            $table->string('origin', 32)->nullable()->after('amount')->index();
            $table->unsignedBigInteger('base_amount')->nullable()->after('origin');
            $table->unsignedInteger('rate_basis_points')->nullable()->after('base_amount');
            $table->string('calculation_method', 32)->nullable()->after('rate_basis_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_amount_components', function (Blueprint $table): void {
            $table->dropIndex(['nature']);
            $table->dropIndex(['affects_net']);
            $table->dropIndex(['origin']);
            $table->dropColumn([
                'nature', 'affects_net', 'origin', 'base_amount',
                'rate_basis_points', 'calculation_method',
            ]);
        });

        Schema::table('financial_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'addition_amount', 'deduction_amount', 'composition_legacy_gross_amount',
            ]);
        });
    }
};
