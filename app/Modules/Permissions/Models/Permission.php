<?php

namespace App\Modules\Permissions\Models;

use App\Models\User;
use App\Supports\Traits\GenerateUniqueSlugTrait;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory, GenerateUniqueSlugTrait;

    protected $fillable = ['name', 'slug', 'group', 'description'];

    protected static function newFactory(): Factory
    {
        return PermissionFactory::new();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'roles_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_permissions');
    }
}
