<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Canal privado de notificações por usuário.
 * Usado pelo NotificationCreated event.
 * Frontend: Echo.private(`user.${userId}.notifications`)
 */
Broadcast::channel('user.{userId}.notifications', function (User $user, int $userId) {
    return $user->id === $userId;
});
