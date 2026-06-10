<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Timeline\Http\Requests\StoreTimelineCommentRequest;
use App\Modules\Timeline\Http\Requests\UpdateTimelineCommentRequest;
use App\Modules\Timeline\Http\Resources\TimelineCommentResource;
use App\Modules\Timeline\Models\TimelineComment;
use App\Modules\Timeline\Services\CommentService;
use App\Modules\Timeline\Support\TimelineSubjectResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TimelineCommentController extends Controller
{
    public function __construct(
        private readonly TimelineSubjectResolver $subjects,
        private readonly CommentService $comments,
    ) {}

    public function store(StoreTimelineCommentRequest $request, string $type, string $id): TimelineCommentResource
    {
        $subject = $this->subjects->resolve($type, $id);
        Gate::authorize('view', $subject);

        $comment = $this->comments->create($subject, $request->user(), $request->validated());

        return new TimelineCommentResource($comment->load(['author:id,name', 'uploads.uploader:id,name', 'replies']));
    }

    public function update(UpdateTimelineCommentRequest $request, TimelineComment $comment): TimelineCommentResource
    {
        Gate::authorize('update', $comment);

        $comment = $this->comments->update($comment, $request->validated());

        return new TimelineCommentResource($comment->load(['author:id,name', 'uploads.uploader:id,name', 'replies']));
    }

    public function destroy(TimelineComment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $this->comments->delete($comment);

        return response()->json(['message' => 'Comentario removido com sucesso.']);
    }

    public function reply(StoreTimelineCommentRequest $request, TimelineComment $comment): TimelineCommentResource
    {
        Gate::authorize('reply', $comment);

        $reply = $this->comments->reply($comment, $request->user(), $request->validated());

        return new TimelineCommentResource($reply->load(['author:id,name', 'uploads.uploader:id,name', 'replies']));
    }
}
