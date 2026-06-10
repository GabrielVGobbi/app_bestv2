<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

enum NotificationActionType: string
{
    case VIEW = 'view';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case DOWNLOAD = 'download';
    case REPLY = 'reply';
    case OPEN = 'open';
    case CONFIRM = 'confirm';
    case CUSTOM = 'custom';
}
