<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Services;

use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class TimelineService
{
    /**
     * @param  array<int, string>  $types
     * @return Collection<int, Activity|TimelineComment>
     */
    public function items(Model $subject, int $limit = 50, ?Carbon $before = null, array $types = []): Collection
    {
        $types = $types === [] ? ['activity', 'comment'] : $types;

        return collect()
            ->when(in_array('comment', $types, true), fn (Collection $items): Collection => $items->merge($this->comments($subject, $limit, $before)))
            ->when(in_array('activity', $types, true), fn (Collection $items): Collection => $items->merge($this->activities($subject, $limit, $before)))
            ->sortBy(fn (Activity|TimelineComment $item): string => $item->created_at?->toISOString() ?? '')
            ->values()
            ->take(-$limit)
            ->values();
    }

    /**
     * @return Collection<int, TimelineComment>
     */
    public function comments(Model $subject, int $limit = 50, ?Carbon $before = null): Collection
    {
        return TimelineComment::query()
            ->with([
                'author:id,name',
                'uploads.uploader:id,name',
                'replies.author:id,name',
                'replies.uploads.uploader:id,name',
            ])
            ->where('commentable_type', $subject::class)
            ->where('commentable_id', $subject->getKey())
            ->whereNull('parent_id')
            ->when($before, fn ($query) => $query->where('created_at', '<', $before))
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @return Collection<int, Activity>
     */
    private function activities(Model $subject, int $limit, ?Carbon $before = null): Collection
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->when($before, fn ($query) => $query->where('created_at', '<', $before))
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
