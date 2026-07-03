<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_installments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_entry_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('open')->index();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date')->nullable()->index();
            $table->unsignedBigInteger('original_amount')->default(0);
            $table->unsignedBigInteger('settled_amount')->default(0);
            $table->unsignedBigInteger('balance_amount')->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['financial_entry_id', 'installment_number'], 'financial_installments_entry_number_unique');
            $table->index(['company_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_installments');
    }
};
