<?php

namespace App\Supports\Traits;

use App\Supports\ActivityActions\ActivityActionLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

trait HasActivityLog
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password', 'remember_token'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->resolveActivityLogName());
    }

    protected function resolveActivityLogName(): string
    {
        return property_exists($this, 'activityLogName')
            ? $this->activityLogName
            : 'default';
    }

    /**
     * Log a generic custom event on this model.
     */
    public function logEvent(string $event, array $properties = [], ?string $logName = null): Activity
    {
        return activity($logName ?? $this->resolveActivityLogName())
            ->performedOn($this)
            ->causedBy(auth()->user() ?? $this)
            ->withProperties($properties)
            ->event($event)
            ->log($event);
    }

    /**
     * Log an "approved" event, e.g. when an admin approves a resource.
     */
    public function logApproved(?Authenticatable $causer = null, array $properties = []): Activity
    {
        return activity('approved')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties($properties)
            ->event('approved')
            ->log('approved');
    }

    /**
     * Log a "rejected" event.
     */
    public function logRejected(?Authenticatable $causer = null, array $properties = []): Activity
    {
        return activity('rejected')
            ->performedOn($this)
            ->causedBy($causer ?? auth()->user())
            ->withProperties($properties)
            ->event('rejected')
            ->log('rejected');
    }

    /**
     * Log a "login" event caused by the model itself (for User model).
     */
    public function logLogin(array $properties = []): ?Activity
    {
        return app(ActivityActionLogger::class)->log(
            actionKey: 'auth.login',
            subject: $this,
            causer: $this,
            properties: $properties,
        );
    }

    /**
     * Log a "logout" event caused by the model itself.
     */
    public function logLogout(array $properties = []): ?Activity
    {
        return app(ActivityActionLogger::class)->log(
            actionKey: 'auth.logout',
            subject: $this,
            causer: $this,
            properties: $properties,
        );
    }
}
