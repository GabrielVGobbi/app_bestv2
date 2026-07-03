<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_entries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 32)->index();
            $table->string('status', 32)->default('open')->index();
            $table->nullableMorphs('counterparty');
            $table->nullableMorphs('originable');
            $table->string('origin_reference')->nullable()->index();
            $table->foreignId('category_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->string('description');
            $table->date('issue_date')->nullable()->index();
            $table->date('competence_date')->nullable()->index();
            $table->date('due_date')->nullable()->index();
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->unsignedBigInteger('settled_amount')->default(0);
            $table->unsignedBigInteger('balance_amount')->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'direction', 'status']);
            $table->index(['company_id', 'counterparty_type']);
            $table->index(['company_id', 'originable_type']);
            $table->index(['company_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
    }
};
