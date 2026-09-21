<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data: one workspace, one user (demo@example.com / password),
 * two projects with a few tasks in every Kanban column.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Workspace',
            'slug' => 'demo',
            'domain' => null,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        Project::factory()
            ->count(2)
            ->create(['tenant_id' => $tenant->id])
            ->each(function (Project $project) use ($user) {
                foreach (Task::STATUSES as $status) {
                    foreach (range(0, 2) as $position) {
                        Task::factory()->create([
                            'project_id' => $project->id,
                            'user_id' => $user->id,
                            'status' => $status,
                            'position' => $position,
                        ]);
                    }
                }
            });
    }
}
