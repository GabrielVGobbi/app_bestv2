<?php

namespace App\Modules\Permissions\Models;

use App\Models\User;
use App\Supports\Traits\GenerateUniqueSlugTrait;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory, GenerateUniqueSlugTrait;

    protected $fillable = ['name', 'slug', 'description'];

    protected static function newFactory(): Factory
    {
        return RoleFactory::new();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'roles_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_roles');
    }
}
