<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $richable_type
 * @property int $richable_id
 * @property string $field
 * @property string $format html|tiptap_json
 * @property string $body
 * @property string|null $body_html
 */
class RichText extends Model
{
    protected $fillable = [
        'richable_type',
        'richable_id',
        'field',
        'format',
        'body',
        'body_html',
    ];

    public function richable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Retorna o HTML renderizável independente do formato armazenado.
     * Se o formato for tiptap_json, usa body_html como cache.
     */
    public function toHtml(): string
    {
        if ($this->format === 'html') {
            return $this->body;
        }

        return $this->body_html ?? '';
    }
}
