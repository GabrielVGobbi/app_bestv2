<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Http\Resources;

use App\Modules\Timeline\Models\TimelineComment;
use App\Modules\Timeline\Services\TimelineHtmlSanitizer;
use App\Modules\Uploads\Http\Resources\UploadedResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin TimelineComment */
class TimelineCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'comment',
            'id' => $this->id,
            'uuid' => $this->uuid,
            'body' => $this->body_format === 'html'
                ? app(TimelineHtmlSanitizer::class)->clean($this->body)
                : $this->body,
            'body_format' => $this->body_format,
            'author' => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ],
            'attachments' => UploadedResource::collection($this->whenLoaded('uploads')),
            'replies' => self::collection($this->whenLoaded('replies')),
            'permissions' => [
                'can_update' => Gate::allows('update', $this->resource),
                'can_delete' => Gate::allows('delete', $this->resource),
                'can_reply' => Gate::allows('reply', $this->resource),
            ],
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
