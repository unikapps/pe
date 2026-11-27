<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Each user hears only about their own generations (see GenerationUpdated).
Broadcast::channel('users.{id}', function (User $user, int $id) {
    return $user->id === $id;
});
