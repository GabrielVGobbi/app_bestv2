<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Services;

use App\Modules\Timeline\Models\TimelineComment;
use App\Modules\Timeline\Support\TimelineCursor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class TimelineService
{
    public function __construct(private readonly TimelineCursor $cursors) {}

    /**
     * @param  array<int, string>  $types
     * @return array{items: Collection<int, Activity|TimelineComment>, next_cursor: ?string}
     */
    public function page(Model $subject, int $limit = 50, ?Carbon $before = null, array $types = [], ?string $cursor = null): array
    {
        $types = $this->cursors->types($types);
        $anchor = $cursor === null ? null : $this->cursors->decode($cursor, $subject, $types);
        $cutoff = $anchor === null ? $before?->format('Y-m-d H:i:s.u') : $anchor['before'];
        /** @var Collection<int, Activity|TimelineComment> $candidates */
        $candidates = collect();
        if (in_array('comment', $types, true)) {
            $candidates = $candidates->merge($this->bounded($this->commentQuery($subject), 0, $limit + 1, $cutoff, $anchor)->get());
        }
        if (in_array('activity', $types, true)) {
            $query = Activity::query()->with(['causer', 'subject'])
                ->where('subject_type', $subject::class)->where('subject_id', $subject->getKey());
            $candidates = $candidates->merge($this->bounded($query, 1, $limit + 1, $cutoff, $anchor)->get());
        }
        $ordered = $candidates->sort(function (Activity|TimelineComment $left, Activity|TimelineComment $right): int {
            $leftTimestamp = $this->cursors->timestamp($left);
            $rightTimestamp = $this->cursors->timestamp($right);

            return strcmp($rightTimestamp ?? '', $leftTimestamp ?? '')
                ?: $this->cursors->rank($right) <=> $this->cursors->rank($left)
                ?: (int) $right->getKey() <=> (int) $left->getKey();
        })->values();
        $items = $ordered->take($limit)->reverse()->values();
        $oldest = $items->first();

        return [
            'items' => $items,
            'next_cursor' => $ordered->count() > $limit && $oldest !== null
                ? $this->cursors->encode($oldest, $subject, $types, $cutoff)
                : null,
        ];
    }

    /**
     * @param  array<int, string>  $types
     * @return Collection<int, Activity|TimelineComment>
     */
    public function items(Model $subject, int $limit = 50, ?Carbon $before = null, array $types = []): Collection
    {
        return $this->page($subject, $limit, $before, $types)['items'];
    }

    /**
     * @return Collection<int, TimelineComment>
     */
    public function comments(Model $subject, int $limit = 50, ?Carbon $before = null): Collection
    {
        return $this->bounded($this->commentQuery($subject), 0, $limit, $before?->format('Y-m-d H:i:s.u'), null)->get()->reverse()->values();
    }

    /** @return Builder<TimelineComment> */
    private function commentQuery(Model $subject): Builder
    {
        return TimelineComment::query()
            ->with([
                'commentable',
                'author:id,name',
                'uploads.uploader:id,name',
                'uploads.parentable.commentable',
                'replies.commentable',
                'replies.author:id,name',
                'replies.uploads.uploader:id,name',
                'replies.uploads.parentable.commentable',
            ])
            ->where('commentable_type', $subject::class)
            ->where('commentable_id', $subject->getKey())
            ->whereNull('parent_id');
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @param  array{timestamp: ?string, rank: int, id: int, before: ?string}|null  $anchor
     * @return Builder<T>
     */
    private function bounded(Builder $query, int $rank, int $limit, ?string $before, ?array $anchor): Builder
    {
        if ($before !== null) {
            $query->whereNotNull('created_at')->where(function (Builder $query) use ($before): void {
                $this->compareTimestamp($query, '<', $before);
            });
        }
        if ($anchor !== null) {
            if ($anchor['timestamp'] === null) {
                $query->whereNull('created_at');
                if ($rank === $anchor['rank']) {
                    $query->where('id', '<', $anchor['id']);
                } elseif ($rank > $anchor['rank']) {
                    $query->whereRaw('1 = 0');
                }
            } else {
                $query->where(function (Builder $query) use ($anchor, $rank): void {
                    $query->whereNull('created_at')->orWhere(function (Builder $query) use ($anchor): void {
                        $this->compareTimestamp($query, '<', $anchor['timestamp']);
                    });
                    if ($rank <= $anchor['rank']) {
                        $query->orWhere(function (Builder $query) use ($anchor, $rank): void {
                            $this->compareTimestamp($query, '=', $anchor['timestamp']);
                            if ($rank === $anchor['rank']) {
                                $query->where('id', '<', $anchor['id']);
                            }
                        });
                    }
                });
            }
        }
        $query->orderByRaw('CASE WHEN created_at IS NULL THEN 0 ELSE 1 END DESC');
        if ($query->getQuery()->getGrammar() instanceof SQLiteGrammar) {
            $query->orderByRaw('substr(created_at, 1, 16) DESC')->orderByRaw('CAST(substr(created_at, 18) AS NUMERIC) DESC');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->orderByDesc('id')->limit($limit);
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     */
    private function compareTimestamp(Builder $query, string $operator, string $timestamp): void
    {
        if (! $query->getQuery()->getGrammar() instanceof SQLiteGrammar) {
            $query->where('created_at', $operator, $timestamp);

            return;
        }
        $minute = substr($timestamp, 0, 16);
        $second = substr($timestamp, 17);
        if ($operator === '=') {
            $query->whereRaw('substr(created_at, 1, 16) = ?', [$minute])->whereRaw('CAST(substr(created_at, 18) AS NUMERIC) = ?', [$second]);

            return;
        }
        $query->whereRaw('substr(created_at, 1, 16) < ?', [$minute])
            ->orWhere(fn (Builder $query) => $query->whereRaw('substr(created_at, 1, 16) = ?', [$minute])->whereRaw('CAST(substr(created_at, 18) AS NUMERIC) < ?', [$second]));
    }
}
