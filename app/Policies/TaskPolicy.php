<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->sameTenant($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->sameTenant($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->sameTenant($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->sameTenant($user, $task);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $this->sameTenant($user, $task);
    }

    private function sameTenant(User $user, Task $task): bool
    {
        return $user->tenant_id !== null && $user->tenant_id === $task->tenant_id;
    }
}
