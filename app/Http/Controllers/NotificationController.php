<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('notifications/index', [
            'types' => NotificationType::cases(),
            'categories' => NotificationCategory::cases(),
        ]);
    }
}
