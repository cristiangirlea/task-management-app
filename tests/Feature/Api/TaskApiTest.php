<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use ActsAsTenantUser;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsTenantUser();
        $this->project = Project::factory()->create(['tenant_id' => $this->user->tenant_id]);
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->create($attributes + ['project_id' => $this->project->id, 'user_id' => null]);
    }

    public function test_lists_project_tasks_ordered_by_position(): void
    {
        $third = $this->task(['status' => 'pending', 'position' => 2]);
        $first = $this->task(['status' => 'pending', 'position' => 0]);
        $second = $this->task(['status' => 'pending', 'position' => 1]);
        $this->task(['project_id' => Project::factory()->create(['tenant_id' => $this->user->tenant_id])->id]);

        $response = $this->getJson("/api/tasks?project_id={$this->project->id}")->assertOk();

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            collect($response->json('data'))->pluck('id')->all()
        );
    }

    public function test_lists_all_tenant_tasks_without_project_filter_and_filters_by_status(): void
    {
        $this->task(['status' => 'pending']);
        $done = $this->task(['status' => 'completed']);
        Task::factory()->create(); // other tenant

        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/tasks?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $done->id);
    }

    public function test_the_list_can_be_filtered_the_way_a_client_would_ask(): void
    {
        $colleague = User::factory()->member()->create(['tenant_id' => $this->user->tenant_id]);
        $late = $this->task(['title' => 'Late invoice', 'due_date' => now()->subWeek(), 'status' => 'pending']);
        $mine = $this->task(['title' => 'Mine', 'user_id' => $this->user->id]);
        $this->task(['title' => 'Theirs', 'user_id' => $colleague->id]);
        $this->task(['title' => 'Nothing special']);

        $this->getJson('/api/tasks?overdue=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $late->id);

        // A query string carries words, not booleans: "false" must mean off,
        // and nonsense must still be refused.
        $this->getJson('/api/tasks?overdue=false')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/tasks?overdue=true')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?overdue=maybe')->assertUnprocessable()->assertJsonValidationErrors(['overdue']);

        $this->getJson("/api/tasks?assigned_to={$this->user->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);

        $this->getJson('/api/tasks?search=invoice')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $late->id);

        $this->getJson('/api/tasks?limit=2')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/tasks?limit=0')->assertUnprocessable()->assertJsonValidationErrors(['limit']);
    }

    public function test_cannot_list_tasks_of_another_tenants_project(): void
    {
        $foreign = Project::factory()->create();

        $this->getJson("/api/tasks?project_id={$foreign->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_task_json_shape(): void
    {
        $task = $this->task(['due_date' => '2030-01-02 03:04:05']);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonStructure(['status', 'message', 'data' => [
                'id', 'title', 'description', 'status', 'priority', 'position', 'due_date',
                'project_id', 'user_id', 'created_at', 'updated_at',
            ]])
            ->assertJsonPath('data.due_date', '2030-01-02T03:04:05+00:00');
    }

    public function test_creates_a_task_at_the_bottom_of_its_column(): void
    {
        $this->task(['status' => 'pending', 'position' => 0]);
        $this->task(['status' => 'pending', 'position' => 1]);
        $this->task(['status' => 'completed', 'position' => 7]);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Write tests',
            'project_id' => $this->project->id,
            'due_date' => '2030-06-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Write tests')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 3)
            ->assertJsonPath('data.position', 2);

        $this->assertDatabaseHas('tasks', ['title' => 'Write tests', 'tenant_id' => $this->user->tenant_id]);
    }

    public function test_validates_task_input(): void
    {
        $this->postJson('/api/tasks', ['status' => 'done', 'priority' => 9])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'project_id', 'status', 'priority']);
    }

    public function test_cannot_create_a_task_in_another_tenants_project(): void
    {
        $foreign = Project::factory()->create();

        $this->postJson('/api/tasks', ['title' => 'Nope', 'project_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_cannot_assign_a_user_from_another_tenant(): void
    {
        $stranger = User::factory()->create();

        $this->postJson('/api/tasks', ['title' => 'Nope', 'project_id' => $this->project->id, 'user_id' => $stranger->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_updates_a_task(): void
    {
        $task = $this->task(['title' => 'Old', 'priority' => 3]);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'New', 'priority' => 1, 'user_id' => $this->user->id])
            ->assertOk()
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.priority', 1)
            ->assertJsonPath('data.user_id', $this->user->id);
    }

    public function test_changing_status_moves_the_task_to_the_bottom_of_the_new_column(): void
    {
        $this->task(['status' => 'in_progress', 'position' => 4]);
        $task = $this->task(['status' => 'pending', 'position' => 0]);

        $this->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.position', 5);
    }

    public function test_deletes_a_task(): void
    {
        $task = $this->task();

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->getJson("/api/tasks/{$task->id}")->assertNotFound();
    }

    public function test_other_tenants_tasks_are_invisible(): void
    {
        $foreign = Task::factory()->create();

        $this->getJson("/api/tasks/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/tasks/{$foreign->id}", ['title' => 'Hijack'])->assertNotFound();
        $this->deleteJson("/api/tasks/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $foreign->id, 'title' => $foreign->title, 'deleted_at' => null]);
    }

    public function test_reorder_moves_tasks_into_a_column_in_the_given_order(): void
    {
        $a = $this->task(['status' => 'pending', 'position' => 0]);
        $b = $this->task(['status' => 'pending', 'position' => 1]);
        $c = $this->task(['status' => 'in_progress', 'position' => 0]);

        $response = $this->postJson('/api/tasks/reorder', [
            'status' => 'in_progress',
            'task_ids' => [$b->id, $c->id],
        ]);

        $response->assertOk();
        $this->assertSame([$b->id, $c->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertDatabaseHas('tasks', ['id' => $b->id, 'status' => 'in_progress', 'position' => 0]);
        $this->assertDatabaseHas('tasks', ['id' => $c->id, 'status' => 'in_progress', 'position' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $a->id, 'status' => 'pending', 'position' => 0]);
    }

    public function test_reorder_accepts_an_empty_column(): void
    {
        $this->postJson('/api/tasks/reorder', ['status' => 'completed', 'task_ids' => []])
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_reorder_rejects_tasks_of_other_tenants(): void
    {
        $foreign = Task::factory()->create();

        $this->postJson('/api/tasks/reorder', ['status' => 'completed', 'task_ids' => [$foreign->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['task_ids.0']);

        $this->assertDatabaseHas('tasks', ['id' => $foreign->id, 'status' => $foreign->status]);
    }
}
