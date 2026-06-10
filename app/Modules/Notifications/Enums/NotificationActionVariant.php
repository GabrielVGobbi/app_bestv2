<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

enum NotificationActionVariant: string
{
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
    case DESTRUCTIVE = 'destructive';
    case GHOST = 'ghost';
    case OUTLINE = 'outline';
    case SUCCESS = 'success';
    case WARNING = 'warning';
}
