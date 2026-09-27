<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->sameTenant($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->sameTenant($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->sameTenant($user, $project);
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->sameTenant($user, $project);
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $this->sameTenant($user, $project);
    }

    private function sameTenant(User $user, Project $project): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $project->tenant_id;
    }
}
