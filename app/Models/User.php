<?php

namespace App\Models;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Modules\Companies\Models\Company;
use App\Modules\Permissions\Models\Role;
use App\Modules\Permissions\Traits\HasPermissionsTrait;
use App\Supports\Traits\HasActivityLog;
use App\Supports\Traits\HasNotifications;
use App\Supports\Traits\HasUuidIdentifier;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['uuid', 'name', 'email', 'password', 'must_change_password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Exportable,
        HasActivityLog,
        HasApiTokens,
        HasFactory,
        HasNotifications,
        HasPermissionsTrait,
        HasUuidIdentifier,
        Notifiable,
        Searchable,
        TwoFactorAuthenticatable {
            replaceRecoveryCode as private replaceRecoveryCodeWithoutLock;
        }

    /** @var array<string, string> */
    protected array $exportable = [
        'name' => 'Nome',
        'email' => 'Email',
        'created_at' => 'Criado em',
    ];

    /** @var array<int, string> */
    protected array $searchable = ['name', 'email'];

    /** @var array<int, string> */
    protected array $filterable = ['uuid', 'name', 'email', 'created_at', 'updated_at', 'email_verified_at'];

    /** @var array<int, string> */
    protected array $sortable = ['id', 'uuid', 'name', 'email', 'created_at', 'updated_at'];

    protected string $defaultSortColumn = 'id';

    protected string $defaultSortDirection = 'desc';

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $role = Role::where('slug', 'user')->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        });
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithRole(Builder $query, string $role): Builder
    {
        return $query->whereHas(
            'roles',
            fn (Builder $roles): Builder => $roles->where('slug', $role),
        );
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('access_source')
            ->withTimestamps();
    }

    /**
     * Consume a recovery code under a row lock so two concurrent challenges cannot reuse it.
     */
    public function replaceRecoveryCode(string $code): void
    {
        $this->getConnection()->transaction(function () use ($code): void {
            $fresh = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();
            if (! $fresh instanceof self || ! $this->containsRecoveryCode($fresh->recoveryCodes(), $code)) {
                throw ValidationException::withMessages([
                    'recovery_code' => __('The provided two factor recovery code was invalid.'),
                ]);
            }
            $fresh->replaceRecoveryCodeWithoutLock($code);
            $this->setAttribute('two_factor_recovery_codes', $fresh->getAttribute('two_factor_recovery_codes'));
            $this->syncOriginalAttribute('two_factor_recovery_codes');
        });
    }

    private function containsRecoveryCode(mixed $codes, string $submitted): bool
    {
        if (! is_array($codes)) {
            return false;
        }
        foreach ($codes as $stored) {
            if (is_string($stored) && hash_equals($stored, $submitted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
