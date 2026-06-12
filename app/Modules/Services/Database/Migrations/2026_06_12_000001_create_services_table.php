<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_price')->default(0);
            $table->char('currency', 3)->default('BRL');
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
