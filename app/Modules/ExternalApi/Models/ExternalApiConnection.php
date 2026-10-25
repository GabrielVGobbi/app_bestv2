<?php

namespace App\Modules\ExternalApi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'provider',
    'provider_user_id',
    'access_token',
    'refresh_token',
    'token_type',
    'scopes',
    'expires_at',
    'metadata',
])]
#[Hidden(['access_token', 'refresh_token'])]
class ExternalApiConnection extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'encrypted:array',
            'metadata' => 'encrypted:array',
            'expires_at' => 'datetime',
        ];
    }
}
