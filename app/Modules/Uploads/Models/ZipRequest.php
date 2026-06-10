<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZipRequest extends Model
{
    protected $table = 'zip_requests';

    protected $fillable = [
        'uuid',
        'user_id',
        'label',
        'file_ids',
        'file_count',
        'status',
        'zip_path',
        'error_message',
        'expires_at',
    ];

    protected $casts = [
        'file_ids' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
