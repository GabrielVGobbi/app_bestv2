<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Actions\Executable\ApproveRequestNotificationAction;
use App\Modules\Notifications\Actions\Executable\Contracts\NotificationExecutableAction;
use App\Modules\Notifications\Actions\Executable\DownloadExportNotificationAction;
use App\Modules\Notifications\Actions\Executable\RejectRequestNotificationAction;
use InvalidArgumentException;

class NotificationActionResolver
{
    /**
     * Map of action_type → executable action class.
     *
     * Add new entries here when introducing new executable action types.
     *
     * @var array<string, class-string<NotificationExecutableAction>>
     */
    protected array $map = [
        'approve_request' => ApproveRequestNotificationAction::class,
        'reject_request' => RejectRequestNotificationAction::class,
        'download_export' => DownloadExportNotificationAction::class,
    ];

    public function resolve(string $actionType): NotificationExecutableAction
    {
        if (! isset($this->map[$actionType])) {
            throw new InvalidArgumentException("Ação desconhecida: [{$actionType}]");
        }

        return app($this->map[$actionType]);
    }

    public function supports(string $actionType): bool
    {
        return isset($this->map[$actionType]);
    }

    /**
     * Register a custom action type at runtime.
     *
     * @param  class-string<NotificationExecutableAction>  $class
     */
    public function register(string $actionType, string $class): void
    {
        $this->map[$actionType] = $class;
    }
}
