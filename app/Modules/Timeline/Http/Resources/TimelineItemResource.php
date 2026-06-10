<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Http\Resources;

use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

class TimelineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof TimelineComment) {
            return (new TimelineCommentResource($this->resource))->toArray($request);
        }

        if ($this->resource instanceof Activity) {
            return (new TimelineActivityResource($this->resource))->toArray($request);
        }

        return [
            'type' => 'system',
            'id' => 'system:unknown',
            'created_at' => null,
        ];
    }
}
