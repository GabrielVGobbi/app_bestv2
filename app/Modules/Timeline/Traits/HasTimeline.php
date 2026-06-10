<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Traits;

use Illuminate\Support\Str;

trait HasTimeline
{
    public static function timelineType(): string
    {
        return Str::of(class_basename(static::class))->kebab()->toString();
    }

    /**
     * @return array<string, mixed>
     */
    public function timelineActivityConfig(): array
    {
        return property_exists($this, 'timelineActivity') && is_array($this->timelineActivity)
            ? $this->timelineActivity
            : [];
    }
}
