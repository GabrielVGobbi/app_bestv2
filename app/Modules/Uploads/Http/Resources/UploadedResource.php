<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = auth()->user();
        $canUpdate = $user?->can('update', $this->resource) ?? false;
        $canDelete = $user?->can('delete', $this->resource) ?? false;
        $previewUrl = route('uploads.preview', ['upload' => $this->id]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_name' => $this->original_name,
            'file_name' => $this->file_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_human' => $this->size_human,
            'url' => $previewUrl,
            'download_url' => route('uploads.download', ['upload' => $this->id]),
            'preview_url' => $previewUrl,
            'icon' => $this->icon,
            'is_image' => $this->isImage(),
            'is_cover' => (bool) $this->is_cover,
            'is_favorite' => (bool) $this->is_favorite,
            'tag' => $this->tag,
            'collection' => $this->collection,
            'source' => $this->source,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'uploader' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader?->id,
                'name' => $this->uploader?->name,
            ]),
            'permissions' => [
                'can_update' => $canUpdate,
                'can_delete' => $canDelete,
            ],
        ];
    }
}
