<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\TaskBoardServer;
use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\CreateTask;
use App\Mcp\Tools\DeleteTask;
use App\Mcp\Tools\ListMembers;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\MoveTask;
use App\Mcp\Tools\UpdateTask;
use App\Mcp\Tools\WorkspaceOverview;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Tests\TestCase;

class TaskBoardServerTest extends TestCase
{
    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->user->tenant_id, 'name' => 'Launch']);
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->create($attributes + ['project_id' => $this->project->id, 'user_id' => null]);
    }

    public function test_registers_the_board_tools(): void
    {
        TaskBoardServer::tools()->assertRegistered([
            WorkspaceOverview::class,
            ListProjects::class,
            CreateProject::class,
            ListMembers::class,
            ListTasks::class,
            CreateTask::class,
            UpdateTask::class,
            MoveTask::class,
            DeleteTask::class,
        ]);
    }

    public function test_list_projects_is_scoped_to_the_users_workspace(): void
    {
        Project::factory()->create(['name' => 'Somebody elses project']);

        TaskBoardServer::actingAs($this->user)
            ->tool(ListProjects::class)
            ->assertOk()
            ->assertSee('Launch')
            ->assertDontSee('Somebody elses project');
    }

    public function test_list_members_shows_only_the_workspace(): void
    {
        User::factory()->member()->create(['tenant_id' => $this->user->tenant_id, 'name' => 'Colleague']);
        User::factory()->create(['name' => 'Outsider']);

        TaskBoardServer::actingAs($this->user)
            ->tool(ListMembers::class)
            ->assertOk()
            ->assertSee('Colleague')
            ->assertDontSee('Outsider');
    }

    public function test_create_project(): void
    {
        TaskBoardServer::actingAs($this->user)
            ->tool(CreateProject::class, ['name' => 'Website', 'description' => 'Redesign'])
            ->assertOk()
            ->assertSee('Website');

        $this->assertDatabaseHas('projects', ['name' => 'Website', 'tenant_id' => $this->user->tenant_id]);
    }

    public function test_list_tasks_filters_by_project_and_status(): void
    {
        $this->task(['title' => 'Write docs', 'status' => 'pending']);
        $this->task(['title' => 'Ship it', 'status' => 'completed']);
        Task::factory()->create(['title' => 'Foreign task']);

        TaskBoardServer::actingAs($this->user)
            ->tool(ListTasks::class, ['project_id' => $this->project->id, 'status' => 'pending'])
            ->assertOk()
            ->assertSee('Write docs')
            ->assertDontSee('Ship it')
            ->assertDontSee('Foreign task');
    }

    public function test_list_tasks_rejects_another_workspaces_project(): void
    {
        $foreign = Project::factory()->create();

        TaskBoardServer::actingAs($this->user)
            ->tool(ListTasks::class, ['project_id' => $foreign->id])
            ->assertHasErrors(['The specified project does not exist.']);
    }

    public function test_create_task_lands_at_the_bottom_of_its_column(): void
    {
        $this->task(['status' => 'pending', 'position' => 0]);

        TaskBoardServer::actingAs($this->user)
            ->tool(CreateTask::class, [
                'title' => 'Write tests',
                'project_id' => $this->project->id,
                'priority' => 2,
                'due_date' => '2030-05-01',
            ])
            ->assertOk()
            ->assertSee('Write tests');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Write tests',
            'tenant_id' => $this->user->tenant_id,
            'status' => 'pending',
            'position' => 1,
            'priority' => 2,
        ]);
    }

    public function test_create_task_validates_input(): void
    {
        TaskBoardServer::actingAs($this->user)
            ->tool(CreateTask::class, ['project_id' => $this->project->id, 'priority' => 9])
            ->assertHasErrors(['Task title is required.', 'Priority cannot exceed 5.']);
    }

    public function test_update_task(): void
    {
        $task = $this->task(['title' => 'Old', 'priority' => 3]);

        TaskBoardServer::actingAs($this->user)
            ->tool(UpdateTask::class, ['task_id' => $task->id, 'title' => 'New', 'priority' => 1])
            ->assertOk()
            ->assertSee('New');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'New', 'priority' => 1]);
    }

    public function test_tools_cannot_touch_another_workspaces_task(): void
    {
        $foreign = Task::factory()->create(['title' => 'Untouchable']);

        $server = TaskBoardServer::actingAs($this->user);

        $server->tool(UpdateTask::class, ['task_id' => $foreign->id, 'title' => 'Hijack'])
            ->assertHasErrors(['The specified task does not exist.']);
        $server->tool(MoveTask::class, ['task_id' => $foreign->id, 'status' => 'completed'])
            ->assertHasErrors(['The specified task does not exist.']);
        $server->tool(DeleteTask::class, ['task_id' => $foreign->id])
            ->assertHasErrors(['The specified task does not exist.']);

        $this->assertDatabaseHas('tasks', ['id' => $foreign->id, 'title' => 'Untouchable', 'status' => $foreign->status, 'deleted_at' => null]);
    }

    public function test_move_task_to_a_position_in_another_column(): void
    {
        $a = $this->task(['title' => 'A', 'status' => 'pending', 'position' => 0]);
        $b = $this->task(['title' => 'B', 'status' => 'pending', 'position' => 1]);
        $c = $this->task(['title' => 'C', 'status' => 'in_progress', 'position' => 0]);
        $d = $this->task(['title' => 'D', 'status' => 'in_progress', 'position' => 1]);

        TaskBoardServer::actingAs($this->user)
            ->tool(MoveTask::class, ['task_id' => $a->id, 'status' => 'in_progress', 'position' => 1])
            ->assertOk()
            ->assertSee('"status":"in_progress"');

        // Destination column: C, A, D. Source column: B moves up to 0.
        $this->assertDatabaseHas('tasks', ['id' => $c->id, 'status' => 'in_progress', 'position' => 0]);
        $this->assertDatabaseHas('tasks', ['id' => $a->id, 'status' => 'in_progress', 'position' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $d->id, 'status' => 'in_progress', 'position' => 2]);
        $this->assertDatabaseHas('tasks', ['id' => $b->id, 'status' => 'pending', 'position' => 0]);
    }

    public function test_move_task_without_position_goes_to_the_bottom(): void
    {
        $a = $this->task(['title' => 'A', 'status' => 'pending', 'position' => 0]);
        $this->task(['title' => 'C', 'status' => 'completed', 'position' => 0]);

        TaskBoardServer::actingAs($this->user)
            ->tool(MoveTask::class, ['task_id' => $a->id, 'status' => 'completed'])
            ->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $a->id, 'status' => 'completed', 'position' => 1]);
    }

    public function test_delete_task_soft_deletes(): void
    {
        $task = $this->task(['title' => 'Gone']);

        TaskBoardServer::actingAs($this->user)
            ->tool(DeleteTask::class, ['task_id' => $task->id])
            ->assertOk()
            ->assertSee('"deleted":true');

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
