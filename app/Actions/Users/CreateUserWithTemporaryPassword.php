<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUserWithTemporaryPassword
{
    /**
     * @param  array{name: string, email: string}  $attributes
     * @return array{user: User, temporary_password: string}
     */
    public function handle(array $attributes): array
    {
        return DB::transaction(function () use ($attributes): array {
            $temporaryPassword = Str::password(length: 18, symbols: false, spaces: false);

            $user = User::create([
                ...$attributes,
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ]);

            return [
                'user' => $user,
                'temporary_password' => $temporaryPassword,
            ];
        });
    }
}
