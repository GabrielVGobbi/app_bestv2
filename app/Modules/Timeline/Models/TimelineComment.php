<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Models;

use App\Models\User;
use App\Modules\Timeline\Database\Factories\TimelineCommentFactory;
use App\Modules\Uploads\Contracts\HasUploadTags;
use App\Modules\Uploads\Traits\HasUploads;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'commentable_type',
    'commentable_id',
    'parent_id',
    'user_id',
    'body',
    'body_format',
    'edited_at',
])]
class TimelineComment extends Model implements HasUploadTags
{
    use HasFactory;
    use HasUploads;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (TimelineComment $comment): void {
            if (! $comment->uuid) {
                $comment->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public static function uploadTagSuggestions(): array
    {
        return [
            'attachment' => 'Anexo',
            'evidence' => 'Evidencia',
            'document' => 'Documento',
        ];
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    protected static function newFactory(): Factory
    {
        return TimelineCommentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
