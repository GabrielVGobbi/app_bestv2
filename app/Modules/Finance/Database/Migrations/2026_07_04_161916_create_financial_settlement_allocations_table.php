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
        Schema::create('financial_settlement_allocations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->constrained('financial_settlements')->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained('financial_installments')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->timestamps();

            $table->unique(['settlement_id', 'installment_id'], 'financial_settlement_allocations_unique');
            $table->index(['company_id', 'installment_id'], 'financial_settlement_allocations_installment_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_settlement_allocations');
    }
};
