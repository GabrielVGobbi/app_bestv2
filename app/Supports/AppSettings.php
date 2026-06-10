<?php

declare(strict_types=1);

namespace App\Supports;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Key-value application settings with cache layer.
 *
 * Usage:
 *   AppSettings::get('email', 'enabled', true)
 *   AppSettings::set('email', 'export_enabled', false)
 *   AppSettings::all('email')
 */
class AppSettings
{
    private const CACHE_TTL = 60; // seconds

    /**
     * Get a setting value.
     *
     * @template T
     *
     * @param  T  $default
     * @return T|mixed
     */
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        $cacheKey = self::cacheKey($group, $key);

        $raw = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $key) {
            $row = DB::table('app_settings')
                ->where('group', $group)
                ->where('key', $key)
                ->value('value');

            return $row; // null if not found
        });

        if ($raw === null) {
            return $default;
        }

        return json_decode($raw, true);
    }

    /**
     * Set a setting value (upsert).
     */
    public static function set(string $group, string $key, mixed $value): void
    {
        DB::table('app_settings')->upsert(
            [
                'group' => $group,
                'key' => $key,
                'value' => json_encode($value),
                'updated_at' => now(),
                'created_at' => now(),
            ],
            ['group', 'key'],
            ['value', 'updated_at'],
        );

        Cache::forget(self::cacheKey($group, $key));
    }

    /**
     * Get all settings for a group as an associative array.
     *
     * @return array<string, mixed>
     */
    public static function all(string $group): array
    {
        $rows = DB::table('app_settings')
            ->where('group', $group)
            ->get(['key', 'value']);

        return $rows->mapWithKeys(
            fn ($row) => [$row->key => json_decode($row->value, true)]
        )->all();
    }

    /**
     * Forget a setting and its cache entry.
     */
    public static function forget(string $group, string $key): void
    {
        DB::table('app_settings')
            ->where('group', $group)
            ->where('key', $key)
            ->delete();

        Cache::forget(self::cacheKey($group, $key));
    }

    private static function cacheKey(string $group, string $key): string
    {
        return "app_settings.{$group}.{$key}";
    }
}
