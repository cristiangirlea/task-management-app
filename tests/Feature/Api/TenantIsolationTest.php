<?php

namespace Tests\Feature\Api;

use App\Mcp\Servers\TaskBoardServer;
use App\Mcp\Tools\DeleteTask;
use App\Mcp\Tools\ListMembers;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\MoveTask;
use App\Mcp\Tools\UpdateTask;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * A sweep over every endpoint that accepts an identifier, proving that a
 * member of one workspace cannot reach another workspace's data through any
 * of them. This is the property the whole product rests on, so it is asserted
 * exhaustively rather than spot-checked.
 */
class TenantIsolationTest extends TestCase
{
    use ActsAsTenantUser;

    private User $intruder;

    private Tenant $victimTenant;

    private User $victim;

    private Project $victimProject;

    private Task $victimTask;

    protected function setUp(): void
    {
        parent::setUp();

        // The victim workspace, fully populated.
        $this->victimTenant = Tenant::factory()->create(['name' => 'Victim Workspace']);
        $this->victim = User::factory()->create(['tenant_id' => $this->victimTenant->id, 'email' => 'victim@example.com']);
        $this->victimProject = Project::factory()->create(['tenant_id' => $this->victimTenant->id, 'name' => 'Secret Project']);
        $this->victimTask = Task::factory()->create([
            'project_id' => $this->victimProject->id,
            'user_id' => $this->victim->id,
            'title' => 'Secret Task',
            'status' => 'pending',
            'position' => 0,
        ]);

        // The intruder: an owner of a different workspace, so role cannot be blamed.
        $this->intruder = $this->actingAsTenantUser();
    }

    public function test_the_victims_projects_are_unreachable(): void
    {
        $id = $this->victimProject->id;

        $this->getJson('/api/projects')->assertOk()->assertJsonMissing(['name' => 'Secret Project']);
        $this->getJson("/api/projects/{$id}")->assertNotFound();
        $this->putJson("/api/projects/{$id}", ['name' => 'Owned'])->assertNotFound();
        $this->deleteJson("/api/projects/{$id}")->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $id, 'name' => 'Secret Project']);
    }

    public function test_the_victims_tasks_are_unreachable(): void
    {
        $id = $this->victimTask->id;

        $this->getJson('/api/tasks')->assertOk()->assertJsonMissing(['title' => 'Secret Task']);
        $this->getJson("/api/tasks/{$id}")->assertNotFound();
        $this->putJson("/api/tasks/{$id}", ['title' => 'Owned'])->assertNotFound();
        $this->deleteJson("/api/tasks/{$id}")->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $id, 'title' => 'Secret Task', 'deleted_at' => null]);
    }

    public function test_foreign_ids_cannot_be_smuggled_in_as_parameters(): void
    {
        // Filtering, creating and assigning all validate ownership.
        $this->getJson("/api/tasks?project_id={$this->victimProject->id}")
            ->assertUnprocessable()->assertJsonValidationErrors(['project_id']);

        $this->postJson('/api/tasks', ['title' => 'Planted', 'project_id' => $this->victimProject->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['project_id']);

        $mine = Project::factory()->create(['tenant_id' => $this->intruder->tenant_id]);
        $this->postJson('/api/tasks', ['title' => 'Assigned away', 'project_id' => $mine->id, 'user_id' => $this->victim->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['user_id']);

        // Moving one of my own tasks into their project.
        $myTask = Task::factory()->create(['project_id' => $mine->id, 'user_id' => null]);
        $this->putJson("/api/tasks/{$myTask->id}", ['project_id' => $this->victimProject->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['project_id']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Planted']);
    }

    public function test_reordering_cannot_touch_foreign_tasks(): void
    {
        $this->postJson('/api/tasks/reorder', ['status' => 'completed', 'task_ids' => [$this->victimTask->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['task_ids.0']);

        // Nor hidden among legitimate ids.
        $mine = Task::factory()->create([
            'project_id' => Project::factory()->create(['tenant_id' => $this->intruder->tenant_id])->id,
            'user_id' => null,
        ]);
        $this->postJson('/api/tasks/reorder', ['status' => 'completed', 'task_ids' => [$mine->id, $this->victimTask->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['task_ids.1']);

        $this->assertDatabaseHas('tasks', ['id' => $this->victimTask->id, 'status' => 'pending', 'position' => 0]);
    }

    public function test_the_victims_people_tokens_and_invitations_are_unreachable(): void
    {
        $invitation = Invitation::factory()->create(['tenant_id' => $this->victimTenant->id]);
        $this->victim->createToken('victim-device');
        $tokenId = $this->victim->tokens()->firstOrFail()->id;

        $this->getJson('/api/tenant/members')->assertOk()->assertJsonMissing(['email' => 'victim@example.com']);
        $this->deleteJson("/api/tenant/members/{$this->victim->id}")->assertNotFound();

        $this->getJson('/api/tenant/invitations')->assertOk()->assertJsonMissing(['id' => $invitation->id]);
        $this->deleteJson("/api/tenant/invitations/{$invitation->id}")->assertNotFound();

        $this->getJson('/api/tokens')->assertOk()->assertJsonMissing(['name' => 'victim-device']);
        $this->deleteJson("/api/tokens/{$tokenId}")->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $this->victim->id]);
        $this->assertDatabaseHas('invitations', ['id' => $invitation->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_the_workspace_itself_is_never_another_tenants(): void
    {
        $this->getJson('/api/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $this->intruder->tenant_id)
            ->assertJsonMissing(['name' => 'Victim Workspace']);

        $this->putJson('/api/tenant', ['name' => 'Renamed'])->assertOk();

        $this->assertDatabaseHas('tenants', ['id' => $this->victimTenant->id, 'name' => 'Victim Workspace']);
    }

    public function test_the_mcp_tools_obey_the_same_boundary(): void
    {
        $server = TaskBoardServer::actingAs($this->intruder);

        $server->tool(ListTasks::class, ['project_id' => $this->victimProject->id])
            ->assertHasErrors(['The specified project does not exist.']);
        $server->tool(UpdateTask::class, ['task_id' => $this->victimTask->id, 'title' => 'Owned'])
            ->assertHasErrors(['The specified task does not exist.']);
        $server->tool(MoveTask::class, ['task_id' => $this->victimTask->id, 'status' => 'completed'])
            ->assertHasErrors(['The specified task does not exist.']);
        $server->tool(DeleteTask::class, ['task_id' => $this->victimTask->id])
            ->assertHasErrors(['The specified task does not exist.']);

        $server->tool(ListTasks::class)->assertOk()->assertDontSee('Secret Task');
        $server->tool(ListMembers::class)->assertOk()->assertDontSee('victim@example.com');

        $this->assertDatabaseHas('tasks', [
            'id' => $this->victimTask->id,
            'title' => 'Secret Task',
            'status' => 'pending',
            'deleted_at' => null,
        ]);
    }

    public function test_a_members_token_is_confined_to_its_own_workspace(): void
    {
        // Exercised over real HTTP with a bearer token rather than actingAs,
        // so the guard, the scope and the policies are all in play.
        $token = $this->victim->createToken('victim-cli')->plainTextToken;
        $mine = Project::factory()->create(['tenant_id' => $this->intruder->tenant_id, 'name' => 'My Project']);

        app('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonMissing(['name' => 'My Project'])
            ->assertJsonPath('data.0.name', 'Secret Project');

        $this->withToken($token)->getJson("/api/projects/{$mine->id}")->assertNotFound();
    }
}
