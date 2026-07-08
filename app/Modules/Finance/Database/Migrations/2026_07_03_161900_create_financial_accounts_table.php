<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 32)->index();
            $table->string('institution_name')->nullable();
            $table->string('agency_number', 32)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->string('account_digit', 16)->nullable();
            $table->string('document_number', 32)->nullable();
            $table->bigInteger('opening_balance_amount')->default(0);
            $table->bigInteger('current_balance_amount')->default(0);
            $table->date('balance_date')->nullable();
            $table->string('currency', 3)->default('BRL');
            $table->boolean('active')->default(true)->index();
            $table->boolean('allows_negative_balance')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type', 'active']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
