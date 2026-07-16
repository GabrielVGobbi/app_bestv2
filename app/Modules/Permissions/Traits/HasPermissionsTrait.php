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
        $this->loadMissing('roles:id,name,slug');

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
        return $this->hasAnyPermission($permission->slug);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        $this->loadAccessControl();

        if ($this->hasRole(config('permissions.super_role', 'developer'))) {
            return true;
        }

        if ($this->permissions->contains(fn (Permission $permission): bool => in_array($permission->slug, $permissions, true))) {
            return true;
        }

        return $this->roles->contains(
            fn (Role $role): bool => $role->permissions->contains(
                fn (Permission $permission): bool => in_array($permission->slug, $permissions, true),
            ),
        );
    }

    public function loadAccessControl(): static
    {
        $this->loadMissing([
            'roles:id,name,slug',
            'roles.permissions:id,slug',
            'permissions:id,slug',
        ]);

        return $this;
    }
}
