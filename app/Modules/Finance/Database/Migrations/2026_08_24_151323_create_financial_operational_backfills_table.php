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
        Schema::create('financial_operational_backfills', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('record_type', 32);
            $table->unsignedBigInteger('record_id');
            $table->unsignedBigInteger('original_category_id')->nullable();
            $table->unsignedBigInteger('assigned_category_id');
            $table->date('original_due_date')->nullable();
            $table->date('assigned_due_date');
            $table->string('reason', 64);
            $table->timestamps();

            $table->unique(['record_type', 'record_id'], 'financial_operational_backfills_record_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_operational_backfills');
    }
};
