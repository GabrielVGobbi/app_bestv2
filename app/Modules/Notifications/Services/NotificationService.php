<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * @var array<int, string>
     */
    private const LIST_COLUMNS = [
        'id',
        'uuid',
        'recipient_id',
        'sender_id',
        'type',
        'category',
        'title',
        'body',
        'data',
        'actions',
        'action_status',
        'link',
        'read_at',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private readonly CreateNotificationAction $createAction,
        private readonly MentionService $mentionService,
    ) {}

    public function create(CreateNotificationDTO $dto): AppNotification
    {
        return $this->createAction->handle($dto);
    }

    /**
     * Create notifications for every @username mentioned in a text.
     *
     * @return array<int, AppNotification>
     */
    public function notifyMentions(
        string $text,
        User $author,
        string $bodyTemplate,
        ?string $link = null,
        ?string $notifiableType = null,
        ?int $notifiableId = null,
    ): array {
        $mentionedUsers = $this->mentionService->usersFromText($text, $author);
        $notifications = [];

        foreach ($mentionedUsers as $mentionedUser) {
            $notifications[] = $this->createAction->handle(
                CreateNotificationDTO::mention(
                    recipientId: $mentionedUser->id,
                    senderId: $author->id,
                    title: 'Você foi mencionado',
                    body: str_replace(':author', $author->name, $bodyTemplate),
                    link: $link,
                    notifiableType: $notifiableType,
                    notifiableId: $notifiableId,
                )
            );
        }

        return $notifications;
    }

    /**
     * @param  array{type?: string, category?: string, read?: string, per_page?: int}  $filters
     */
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = AppNotification::forRecipient($user)
            ->select(self::LIST_COLUMNS)
            ->with('sender:id,name,uuid')
            ->orderByDesc('created_at');

        if (! empty($filters['type'])) {
            $type = NotificationType::tryFrom($filters['type']);
            if ($type !== null) {
                $query->ofType($type);
            }
        }

        if (! empty($filters['category'])) {
            $category = NotificationCategory::tryFrom($filters['category']);
            if ($category !== null) {
                $query->ofCategory($category);
            }
        }

        if (isset($filters['read'])) {
            if ($filters['read'] === 'true') {
                $query->read();
            } elseif ($filters['read'] === 'false') {
                $query->unread();
            }
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 50);

        return $query->paginate($perPage)->withQueryString();
    }

    public function unreadCountForUser(User $user): int
    {
        return AppNotification::forRecipient($user)->unread()->count();
    }

    /**
     * @return array<int, AppNotification>
     */
    public function recentUnreadForUser(User $user, int $limit = 5): array
    {
        return AppNotification::forRecipient($user)
            ->select(self::LIST_COLUMNS)
            ->unread()
            ->with('sender:id,name,uuid')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
