<?php

namespace App\Modules\ExternalApi\Core\Support;

use App\Models\User;
use App\Modules\ExternalApi\Core\Exceptions\ExternalApiException;
use Illuminate\Support\Str;

class OAuthStateManager
{
    public function create(string $provider, User $user): string
    {
        $state = Str::random(40);

        session([$this->key($provider, $user) => [
            'state' => $state,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]]);

        return $state;
    }

    public function validate(string $provider, User $user, string $state): void
    {
        $key = $this->key($provider, $user);
        $payload = session($key);

        session()->forget($key);

        if (! is_array($payload) || ($payload['state'] ?? null) !== $state) {
            throw new ExternalApiException('Invalid OAuth state.');
        }

        if ((int) ($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw new ExternalApiException('Expired OAuth state.');
        }
    }

    private function key(string $provider, User $user): string
    {
        return "external-api.oauth-state.{$provider}.{$user->id}";
    }
}
