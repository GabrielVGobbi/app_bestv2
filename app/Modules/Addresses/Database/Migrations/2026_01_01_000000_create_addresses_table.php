<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('addressable');
            $table->string('name')->nullable();
            $table->string('street')->nullable();
            $table->string('number', 50)->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('city_code', 30)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->string('complement')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('state_registration', 80)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['zipcode', 'street', 'number', 'city', 'state'], 'addresses_lookup_index');
            $table->index(['addressable_type', 'addressable_id', 'is_default'], 'addresses_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
