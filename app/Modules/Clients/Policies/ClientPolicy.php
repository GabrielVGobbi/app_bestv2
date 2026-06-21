<?php

declare(strict_types=1);

namespace App\Modules\Clients\Policies;

use App\Models\User;
use App\Modules\Clients\Models\Client;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        return $client->canBeEditedInternally();
    }

    public function delete(User $user, Client $client): bool
    {
        return true;
    }
}
