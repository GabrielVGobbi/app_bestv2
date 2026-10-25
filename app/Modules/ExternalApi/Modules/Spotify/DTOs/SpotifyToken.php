<?php

namespace App\Modules\ExternalApi\Modules\Spotify\DTOs;

use App\Modules\ExternalApi\Core\Support\ArrayData;
use Carbon\CarbonImmutable;

class SpotifyToken
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly string $tokenType,
        public readonly int $expiresIn,
        public readonly array $scopes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: ArrayData::string($data, 'access_token'),
            refreshToken: ArrayData::nullableString($data, 'refresh_token'),
            tokenType: ArrayData::string($data, 'token_type', 'Bearer'),
            expiresIn: ArrayData::int($data, 'expires_in', 3600),
            scopes: array_values(array_filter(explode(' ', ArrayData::string($data, 'scope')))),
        );
    }

    public function expiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addSeconds($this->expiresIn);
    }
}
