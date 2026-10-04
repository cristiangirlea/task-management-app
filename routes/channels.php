<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Boards listen here for BoardChanged. The binding is scoped to the user's
// workspace, so another workspace's project is not found.
Broadcast::channel('projects.{project}', fn (User $user, Project $project) => $user->can('view', $project));
