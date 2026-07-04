<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('logo_disk')->nullable()->after('fiscal_metadata');
            $table->string('logo_path')->nullable()->after('logo_disk');
            $table->timestamp('logo_updated_at')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['logo_disk', 'logo_path', 'logo_updated_at']);
        });
    }
};
