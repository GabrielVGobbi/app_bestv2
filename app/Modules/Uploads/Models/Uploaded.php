<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Uploaded extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'uploadeds';

    protected $fillable = [
        'user_id',
        'uploader_id',
        'parentable_type',
        'parentable_id',
        'name',
        'file_name',
        'original_name',
        'mime_type',
        'path',
        'extension',
        'disk',
        'file_hash',
        'size',
        'tag',
        'collection',
        'is_cover',
        'is_favorite',
        'source',
        'uploaded_images',
    ];

    protected $casts = [
        'uploaded_images' => 'array',
        'size' => 'integer',
        'is_cover' => 'boolean',
        'is_favorite' => 'boolean',
    ];

    protected $appends = [
        'url',
        'icon',
        'size_human',
    ];

    public function parentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        if ($this->disk === 'public') {
            return asset('storage/'.ltrim($this->path, '/'));
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function getIconAttribute(): string
    {
        return match (true) {
            str_contains($this->mime_type, 'image') => 'image',
            str_contains($this->mime_type, 'video') => 'video',
            str_contains($this->mime_type, 'audio') => 'music',
            str_contains($this->mime_type, 'spreadsheet') || str_contains($this->mime_type, 'excel') => 'file-spreadsheet',
            str_contains($this->mime_type, 'pdf'),
            str_contains($this->mime_type, 'word'),
            str_contains($this->mime_type, 'text') => 'file-text',
            default => 'file',
        };
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = (int) ($this->size ?? 0);

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    public function isImage(): bool
    {
        return str_contains($this->mime_type, 'image');
    }
}
