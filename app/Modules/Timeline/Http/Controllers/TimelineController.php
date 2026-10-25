<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Timeline\Http\Requests\IndexTimelineRequest;
use App\Modules\Timeline\Http\Resources\TimelineItemResource;
use App\Modules\Timeline\Services\TimelineService;
use App\Modules\Timeline\Support\TimelineSubjectResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class TimelineController extends Controller
{
    public function __construct(
        private readonly TimelineSubjectResolver $subjects,
        private readonly TimelineService $timeline,
    ) {}

    public function show(IndexTimelineRequest $request, string $type, string $id): JsonResponse
    {
        $subject = $this->subjects->resolve($type, $id);

        Gate::authorize('view', $subject);

        $before = $request->validated('before')
            ? Carbon::parse($request->validated('before'))->setTimezone(config('app.timezone'))
            : null;
        $page = $this->timeline->page(
            subject: $subject,
            limit: $request->limit(),
            before: $before,
            types: $request->validated('types') ?? [],
            cursor: $request->validated('cursor'),
        );

        return response()->json([
            'data' => TimelineItemResource::collection($page['items'])->resolve($request),
            'subject' => [
                'type' => $type,
                'id' => $subject->getKey(),
                'uuid' => $subject->getAttribute('uuid'),
                'label' => $this->displayName($subject),
            ],
            'meta' => [
                'next_cursor' => $page['next_cursor'],
            ],
        ]);
    }

    private function displayName(Model $subject): string
    {
        foreach (['name', 'title', 'email', 'uuid'] as $attribute) {
            if (filled($subject->getAttribute($attribute))) {
                return (string) $subject->getAttribute($attribute);
            }
        }

        return '#'.$subject->getKey();
    }
}
