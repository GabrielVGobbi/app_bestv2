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
        Schema::table('financial_settlements', function (Blueprint $table): void {
            $table->unsignedBigInteger('addition_amount')->default(0)->after('gross_amount');
            $table->unsignedBigInteger('deduction_amount')->default(0)->after('addition_amount');
        });

        Schema::create('financial_settlement_components', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->constrained('financial_settlements')->cascadeOnDelete();
            $table->string('code', 64)->index();
            $table->string('nature', 24)->index();
            $table->string('label')->nullable();
            $table->string('operation', 16)->index();
            $table->boolean('affects_net')->index();
            $table->unsignedBigInteger('amount');
            $table->string('origin', 32)->index();
            $table->unsignedBigInteger('base_amount')->nullable();
            $table->unsignedInteger('rate_basis_points')->nullable();
            $table->string('calculation_method', 32)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'settlement_id', 'nature'], 'settlement_components_company_nature_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_settlement_components');

        Schema::table('financial_settlements', function (Blueprint $table): void {
            $table->dropColumn(['addition_amount', 'deduction_amount']);
        });
    }
};
