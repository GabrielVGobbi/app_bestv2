<?php

declare(strict_types=1);

namespace Tests\Fixtures\Email;

final class SvixMessageFixture
{
    public const KEY = 'SEC009_SYNTHETIC_SIGNING_KEY_ONLY';

    public static function secret(): string
    {
        return 'whsec_'.base64_encode(self::KEY);
    }

    /** @return array{'svix-id': string, 'svix-timestamp': string, 'svix-signature': string} */
    public static function headers(string $raw, string $id, string $timestamp, string $key = self::KEY): array
    {
        return [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => self::signature($raw, $id, $timestamp, $key),
        ];
    }

    public static function signature(string $raw, string $id, string $timestamp, string $key = self::KEY): string
    {
        return 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$raw, $key, true));
    }

    /** @param array<string, mixed> $payload */
    public static function raw(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
