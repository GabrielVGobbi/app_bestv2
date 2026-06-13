<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_genre_music_subgenre', function (Blueprint $table): void {
            $table->foreignId('music_genre_id')->constrained('music_genres')->cascadeOnDelete();
            $table->foreignId('music_subgenre_id')->constrained('music_subgenres')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['music_genre_id', 'music_subgenre_id'], 'genre_subgenre_primary');
        });

        if (Schema::hasColumn('music_subgenres', 'music_genre_id')) {
            DB::table('music_subgenres')
                ->select(['id', 'music_genre_id', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->get()
                ->each(function (object $subgenre): void {
                    DB::table('music_genre_music_subgenre')->insertOrIgnore([
                        'music_genre_id' => $subgenre->music_genre_id,
                        'music_subgenre_id' => $subgenre->id,
                        'created_at' => $subgenre->created_at,
                        'updated_at' => $subgenre->updated_at,
                    ]);
                });

            DB::table('music_subgenres')
                ->select('slug')
                ->groupBy('slug')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('slug')
                ->each(function (string $slug): void {
                    $subgenreIds = DB::table('music_subgenres')
                        ->where('slug', $slug)
                        ->orderBy('id')
                        ->pluck('id');
                    $primaryId = (int) $subgenreIds->first();
                    $duplicateIds = $subgenreIds->skip(1)->values();

                    $duplicateIds->each(function (int $duplicateId) use ($primaryId): void {
                        DB::table('music_genre_music_subgenre')
                            ->where('music_subgenre_id', $duplicateId)
                            ->get()
                            ->each(function (object $link) use ($primaryId): void {
                                DB::table('music_genre_music_subgenre')->insertOrIgnore([
                                    'music_genre_id' => $link->music_genre_id,
                                    'music_subgenre_id' => $primaryId,
                                    'created_at' => $link->created_at,
                                    'updated_at' => $link->updated_at,
                                ]);
                            });

                        DB::table('playlist_music_subgenre')
                            ->where('music_subgenre_id', $duplicateId)
                            ->get()
                            ->each(function (object $link) use ($primaryId): void {
                                DB::table('playlist_music_subgenre')->insertOrIgnore([
                                    'playlist_id' => $link->playlist_id,
                                    'music_subgenre_id' => $primaryId,
                                ]);
                            });

                        DB::table('playlist_music_subgenre')
                            ->where('music_subgenre_id', $duplicateId)
                            ->delete();
                        DB::table('music_genre_music_subgenre')
                            ->where('music_subgenre_id', $duplicateId)
                            ->delete();
                        DB::table('music_subgenres')
                            ->where('id', $duplicateId)
                            ->delete();
                    });
                });

            Schema::table('music_subgenres', function (Blueprint $table): void {
                $table->dropForeign(['music_genre_id']);
                $table->dropUnique(['music_genre_id', 'slug']);
                $table->dropColumn('music_genre_id');
                $table->unique('slug');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('music_subgenres', 'music_genre_id')) {
            Schema::table('music_subgenres', function (Blueprint $table): void {
                $table->foreignId('music_genre_id')
                    ->nullable()
                    ->after('uuid')
                    ->constrained('music_genres')
                    ->nullOnDelete();
            });

            DB::table('music_genre_music_subgenre')
                ->select(['music_genre_id', 'music_subgenre_id'])
                ->orderBy('music_genre_id')
                ->get()
                ->each(function (object $link): void {
                    DB::table('music_subgenres')
                        ->where('id', $link->music_subgenre_id)
                        ->whereNull('music_genre_id')
                        ->update(['music_genre_id' => $link->music_genre_id]);
                });
        }

        Schema::dropIfExists('music_genre_music_subgenre');
    }
};
