<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_histories', function (Blueprint $table): void {
            $table->string('disk')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('backup_histories')->whereNull('disk')->exists()) {
            throw new RuntimeException('Cannot require a backup disk while history records without a disk exist.');
        }

        Schema::table('backup_histories', function (Blueprint $table): void {
            $table->string('disk')->nullable(false)->change();
        });
    }
};
