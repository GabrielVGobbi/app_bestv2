<?php

namespace App\Http\Resources;

use App\Supports\ActivityActions\ActivityActionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $properties = $this->properties?->toArray() ?? [];
        $actionKey = $properties['action_key'] ?? null;
        $definition = is_string($actionKey)
            ? app(ActivityActionRegistry::class)->find($actionKey)
            : app(ActivityActionRegistry::class)->findByLogAndEvent($this->log_name, $this->event, $this->subject_type);

        return [
            'id' => $this->id,
            'action_key' => $actionKey,
            'action' => $definition?->toArray(),
            'log_name' => $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? [
                'id' => $this->subject->getKey(),
                'name' => $this->subject->name ?? "#{$this->subject->getKey()}",
                'email' => $this->subject->email ?? null,
            ] : null),
            'causer_type' => $this->causer_type ? class_basename($this->causer_type) : null,
            'causer_id' => $this->causer_id,
            'causer' => $this->whenLoaded('causer', fn () => $this->causer ? [
                'id' => $this->causer->getKey(),
                'uuid' => $this->causer->uuid ?? null,
                'name' => $this->causer->name ?? 'Sistema',
                'email' => $this->causer->email ?? null,
            ] : null),
            'properties' => $properties,
            'batch_uuid' => $this->batch_uuid,
            'created_at' => $this->created_at,
        ];
    }
}
