<?php

namespace App\Policies;

use App\Models\User;
use App\Services\UserAccountService;

class UserPolicy
{
    public function __construct(private UserAccountService $accounts) {}

    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id
            || $actor->hasRole(User::ROLE_SUPER_ADMIN)
            || $this->accounts->canManageRole($actor, $target->role);
    }

    public function manage(User $actor, User $target): bool
    {
        return $this->accounts->canManage($actor, $target);
    }
}
