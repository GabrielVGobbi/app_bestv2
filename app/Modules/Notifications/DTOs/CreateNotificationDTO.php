<?php

declare(strict_types=1);

namespace App\Modules\Notifications\DTOs;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;

/**
 * @phpstan-type NotificationActionData array{
 *     label: string,
 *     type: string,
 *     variant: string,
 *     requires_confirmation?: bool,
 *     confirmation_message?: string
 * }
 */
final class CreateNotificationDTO
{
    /**
     * @param  array<int, NotificationActionData>|null  $actions
     * @param  array<string, mixed>|null  $data  Only display information and auxiliary IDs — never sensitive business rules.
     */
    public function __construct(
        public readonly int $recipientId,
        public readonly NotificationType $type,
        public readonly NotificationCategory $category,
        public readonly string $title,
        public readonly string $body,
        public readonly ?int $senderId = null,
        public readonly ?string $link = null,
        public readonly ?string $notifiableType = null,
        public readonly ?int $notifiableId = null,
        public readonly ?array $actions = null,
        public readonly ?array $data = null,
    ) {}

    public static function system(
        int $recipientId,
        string $title,
        string $body,
        NotificationCategory $category = NotificationCategory::SYSTEM,
        ?string $link = null,
        ?array $data = null,
    ): self {
        return new self(
            recipientId: $recipientId,
            type: NotificationType::SYSTEM,
            category: $category,
            title: $title,
            body: $body,
            link: $link,
            data: $data,
        );
    }

    public static function fromUser(
        int $recipientId,
        int $senderId,
        string $title,
        string $body,
        NotificationCategory $category = NotificationCategory::GENERAL,
        ?string $link = null,
        ?array $data = null,
    ): self {
        return new self(
            recipientId: $recipientId,
            type: NotificationType::USER,
            category: $category,
            title: $title,
            body: $body,
            senderId: $senderId,
            link: $link,
            data: $data,
        );
    }

    public static function mention(
        int $recipientId,
        int $senderId,
        string $title,
        string $body,
        ?string $link = null,
        ?string $notifiableType = null,
        ?int $notifiableId = null,
        ?array $data = null,
    ): self {
        return new self(
            recipientId: $recipientId,
            type: NotificationType::MENTION,
            category: NotificationCategory::MENTION,
            title: $title,
            body: $body,
            senderId: $senderId,
            link: $link,
            notifiableType: $notifiableType,
            notifiableId: $notifiableId,
            data: $data,
        );
    }

    /**
     * @param  array<int, NotificationActionData>  $actions
     */
    public static function withActions(
        int $recipientId,
        string $title,
        string $body,
        array $actions,
        NotificationCategory $category = NotificationCategory::GENERAL,
        ?int $senderId = null,
        ?string $link = null,
        ?string $notifiableType = null,
        ?int $notifiableId = null,
        ?array $data = null,
    ): self {
        return new self(
            recipientId: $recipientId,
            type: NotificationType::ACTION,
            category: $category,
            title: $title,
            body: $body,
            senderId: $senderId,
            link: $link,
            notifiableType: $notifiableType,
            notifiableId: $notifiableId,
            actions: $actions,
            data: $data,
        );
    }
}
