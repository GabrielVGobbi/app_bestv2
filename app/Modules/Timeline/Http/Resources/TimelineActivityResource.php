<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Http\Resources;

use App\Modules\Timeline\Services\ActivityFormatterService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(ActivityFormatterService::class)->format($this->resource);
    }
}
