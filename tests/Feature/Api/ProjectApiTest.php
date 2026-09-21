<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use ActsAsTenantUser;

    public function test_lists_only_projects_of_the_users_tenant(): void
    {
        $user = $this->actingAsTenantUser();
        $mine = Project::factory()->count(2)->create(['tenant_id' => $user->tenant_id]);
        Project::factory()->create(); // another tenant

        $response = $this->getJson('/api/projects')->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing($mine->pluck('id')->all(), collect($response->json('data'))->pluck('id')->all());
    }

    public function test_list_returns_empty_data_array_when_there_are_no_projects(): void
    {
        $this->actingAsTenantUser();

        $this->getJson('/api/projects')->assertOk()->assertExactJson([
            'status' => 'success',
            'message' => 'Projects retrieved successfully',
            'data' => [],
        ]);
    }

    public function test_creates_a_project_in_the_users_tenant(): void
    {
        $user = $this->actingAsTenantUser();

        $response = $this->postJson('/api/projects', ['name' => 'Launch', 'description' => 'Ship it']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Launch')
            ->assertJsonPath('data.tasks_count', 0);

        $this->assertDatabaseHas('projects', ['name' => 'Launch', 'tenant_id' => $user->tenant_id]);
    }

    public function test_tenant_id_from_the_request_body_is_ignored(): void
    {
        $user = $this->actingAsTenantUser();
        $other = Tenant::factory()->create();

        $this->postJson('/api/projects', ['name' => 'Sneaky', 'tenant_id' => $other->id])->assertCreated();

        $this->assertDatabaseHas('projects', ['name' => 'Sneaky', 'tenant_id' => $user->tenant_id]);
    }

    public function test_validates_project_name(): void
    {
        $this->actingAsTenantUser();

        $this->postJson('/api/projects', ['description' => 'no name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_shows_a_project_with_its_task_count(): void
    {
        $user = $this->actingAsTenantUser();
        $project = Project::factory()->create(['tenant_id' => $user->tenant_id]);
        Task::factory()->count(3)->create(['project_id' => $project->id]);

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.tasks_count', 3);
    }

    public function test_updates_a_project(): void
    {
        $user = $this->actingAsTenantUser();
        $project = Project::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->putJson("/api/projects/{$project->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Renamed']);
    }

    public function test_deletes_a_project(): void
    {
        $user = $this->actingAsTenantUser();
        $project = Project::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_other_tenants_projects_are_invisible(): void
    {
        $this->actingAsTenantUser();
        $foreign = Project::factory()->create();

        $this->getJson("/api/projects/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/projects/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->deleteJson("/api/projects/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $foreign->id, 'name' => $foreign->name]);
    }
}
