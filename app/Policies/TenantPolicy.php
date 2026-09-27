<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Any member may view their own workspace.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    /**
     * Owners manage the workspace: settings, members and invitations.
     */
    public function manage(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id && $user->isOwner();
    }
}
