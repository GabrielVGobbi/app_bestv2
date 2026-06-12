<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('uuid')->index();
            $table->foreignId('responsible_user_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            $table->string('company_name')->nullable()->after('name');
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('document')->nullable()->unique()->after('phone');
            $table->string('musical_genre')->nullable()->after('document');
            $table->boolean('is_agent')->default(false)->after('musical_genre');
            $table->text('notes')->nullable()->after('is_agent');
            $table->string('classification')->nullable()->after('notes');
            $table->string('source_id')->nullable()->after('classification');
            $table->string('status')->default('active')->after('source_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropForeign(['responsible_user_id']);
            $table->dropIndex(['company_id']);
            $table->dropIndex(['status']);
            $table->dropUnique(['phone']);
            $table->dropUnique(['document']);
            $table->dropColumn([
                'company_id',
                'responsible_user_id',
                'company_name',
                'phone',
                'document',
                'musical_genre',
                'is_agent',
                'notes',
                'classification',
                'source_id',
                'status',
            ]);
        });
    }
};
