<?php

namespace App\Modules\ExternalApi\Core\Support;

class ArrayData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = data_get($data, $key);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = data_get($data, $key);

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = data_get($data, $key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = data_get($data, $key);

        return is_bool($value) || is_numeric($value) || is_string($value) ? (bool) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int|string, mixed>
     */
    public static function array(array $data, string $key): array
    {
        $value = data_get($data, $key);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public static function listOfArrays(array $data, string $key): array
    {
        return array_values(array_filter(
            self::array($data, $key),
            fn (mixed $item): bool => is_array($item)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function externalUrl(array $data, string $provider = 'spotify'): ?string
    {
        return self::nullableString($data, "external_urls.{$provider}");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function firstImageUrl(array $data): ?string
    {
        $image = self::listOfArrays($data, 'images')[0] ?? [];

        return self::nullableString($image, 'url');
    }
}
