<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid()->unique();
            $table->foreignId('bank_statement_import_id')->constrained('bank_statement_imports')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->date('posted_at')->index();
            $table->string('description');
            $table->bigInteger('amount');
            $table->string('reference')->nullable()->index();
            $table->string('line_hash', 64)->unique();
            $table->json('raw_payload')->nullable();
            $table->timestamp('reconciled_at')->nullable()->index();
            $table->timestamps();

            $table->index(['company_id', 'posted_at']);
            $table->index(['company_id', 'amount', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
