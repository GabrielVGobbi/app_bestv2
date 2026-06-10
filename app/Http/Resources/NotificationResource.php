<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'title' => $this->title,
            'body' => $this->body,
            'link' => $this->link,
            'actions' => $this->actions,
            'action_status' => $this->action_status,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'sender' => $this->whenLoaded('sender', fn () => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'uuid' => $this->sender->uuid,
            ] : null),
            // data is only returned with explicitly allowed keys — never raw
            'data' => $this->when(
                $this->data !== null,
                fn () => $this->resolvePublicData()
            ),
        ];
    }

    /**
     * Returns only display-safe keys from the data field.
     * Add keys here as your domain grows — never expose sensitive fields.
     *
     * @return array<string, mixed>
     */
    protected function resolvePublicData(): array
    {
        $allowed = [
            'export_uuid',
            'file_name',
            'format',
            'total_records',
            'preview_url',
            'entity_name',
            'entity_id',
        ];

        return array_intersect_key($this->data ?? [], array_flip($allowed));
    }
}
