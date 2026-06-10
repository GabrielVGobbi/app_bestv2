<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExecuteNotificationActionRequest;
use App\Http\Resources\NotificationResource;
use App\Modules\Notifications\Actions\DeleteNotificationAction;
use App\Modules\Notifications\Actions\MarkAllAsReadAction;
use App\Modules\Notifications\Actions\MarkAsReadAction;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Services\NotificationActionResolver;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationApiController extends Controller
{
    public function __construct(
        private readonly NotificationService $service,
        private readonly NotificationActionResolver $resolver,
        private readonly MarkAsReadAction $markAsReadAction,
        private readonly MarkAllAsReadAction $markAllAsReadAction,
        private readonly DeleteNotificationAction $deleteAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $this->service->listForUser(
            user: $request->user(),
            filters: $request->only(['type', 'category', 'read', 'per_page']),
        );

        return NotificationResource::collection($notifications)->additional([
            'unread_count' => $this->service->unreadCountForUser($request->user()),
        ]);
    }

    public function unread(Request $request): AnonymousResourceCollection
    {
        $notifications = $this->service->recentUnreadForUser($request->user(), limit: 10);

        return NotificationResource::collection($notifications)->additional([
            'unread_count' => $this->service->unreadCountForUser($request->user()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->service->unreadCountForUser($request->user()),
        ]);
    }

    public function markAsRead(Request $request, AppNotification $notification): NotificationResource
    {
        abort_unless($notification->recipient_id === $request->user()->id, 403);

        $this->markAsReadAction->handle($notification);

        return new NotificationResource($notification->refresh());
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->markAllAsReadAction->handle($request->user());

        return response()->json(['marked' => $count]);
    }

    public function destroy(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->recipient_id === $request->user()->id, 403);

        $this->deleteAction->handle($notification);

        return response()->json(null, 204);
    }

    public function executeAction(ExecuteNotificationActionRequest $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->recipient_id === $request->user()->id, 403);
        abort_unless($notification->hasActions(), 422, 'Esta notificação não possui ações.');
        abort_unless($notification->isActionPending(), 409, 'A ação desta notificação já foi executada ou está em estado final.');

        $actionType = $request->validated('action_type');

        abort_unless($notification->hasActionOfType($actionType), 422, "Ação [{$actionType}] não encontrada nesta notificação.");
        abort_unless($this->resolver->supports($actionType), 422, "Tipo de ação [{$actionType}] não é executável pelo sistema.");

        $executable = $this->resolver->resolve($actionType);
        $result = $executable->execute($notification, $request->user(), $request->validated('payload', []));

        $notification->update([
            'action_status' => $actionType === 'reject_request' ? 'rejected' : 'executed',
        ]);

        return response()->json([
            'action_type' => $actionType,
            'result' => $result,
        ]);
    }
}
