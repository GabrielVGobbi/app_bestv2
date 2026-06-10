<?php

namespace App\Modules\Permissions\Traits;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasPermissionsTrait
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'users_roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'users_permissions');
    }

    public function hasRole(string ...$roles): bool
    {
        $super = config('permissions.super_role', 'developer');

        if ($this->roles->contains('slug', $super)) {
            return true;
        }

        foreach ($roles as $role) {
            if ($this->roles->contains('slug', $role)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermissionTo(Permission $permission): bool
    {
        $super = config('permissions.super_role', 'developer');

        if ($this->hasRole($super)) {
            return true;
        }

        if ($this->permissions->contains('slug', $permission->slug)) {
            return true;
        }

        foreach ($permission->roles as $role) {
            if ($this->roles->contains($role)) {
                return true;
            }
        }

        return false;
    }
}
