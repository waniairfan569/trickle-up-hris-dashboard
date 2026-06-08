<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TimeOffRequest;

class TimeOffRequestPolicy
{
    public function view(User $auth, TimeOffRequest $req): bool
    {
        if ($auth->isAdmin()) return true;
        if ($auth->id === $req->user_id) return true;
        
        $requester = $req->user;
        if ($requester && $auth->canManage($requester)) return true;

        return false;
    }

    public function create(User $auth): bool
    {
        return true; // Any authenticated user can create
    }

    public function approve(User $auth, TimeOffRequest $req): bool
    {
        if ($auth->isAdmin()) return true;

        $requester = $req->user;
        if ($requester && $auth->canManage($requester)) return true;

        return false;
    }

    public function cancel(User $auth, TimeOffRequest $req): bool
    {
        if ($auth->isAdmin()) return true;
        if ($auth->id === $req->user_id && $req->status === 'pending') return true;

        return false;
    }
}
