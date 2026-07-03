<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_amount_components', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->morphs('amountable');
            $table->string('code', 64)->index();
            $table->string('label')->nullable();
            $table->string('operation', 16)->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_amount_components');
    }
};
