<?php

namespace Tests\Unit\Models;

use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a task belongs to a project.
     */
    public function test_task_belongs_to_a_project()
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->assertInstanceOf(Project::class, $task->project);
        $this->assertEquals($project->id, $task->project->id);
    }

    /**
     * Test that a task belongs to a user.
     */
    public function test_task_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $task->user);
        $this->assertEquals($user->id, $task->user->id);
    }

    /**
     * Test fillable attributes for the task.
     */
    public function test_task_fillable_attributes()
    {
        $data = [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'pending',
            'priority' => 3,
            'due_date' => now()->addDays(1),
            'project_id' => 1,
            'user_id' => 1,
        ];

        $task = Task::create($data);

        $this->assertEquals($data['title'], $task->title);
        $this->assertEquals($data['description'], $task->description);
        $this->assertEquals($data['status'], $task->status);
        $this->assertEquals($data['priority'], $task->priority);
        $this->assertEquals($data['project_id'], $task->project_id);
        $this->assertEquals($data['user_id'], $task->user_id);
    }

    /**
     * Test the isOverdue method.
     */
    public function test_task_is_overdue_method()
    {
        $task = Task::factory()->create(['due_date' => now()->subDay()]); // Past date

        $this->assertTrue($task->isOverdue(), 'Task should be overdue.');

        $task = Task::factory()->create(['due_date' => now()->addDay()]); // Future date

        $this->assertFalse($task->isOverdue(), 'Task should not be overdue.');
    }

    /**
     * Test the scopeByStatus method.
     */
    public function test_task_scope_by_status()
    {
        Task::factory()->create(['status' => 'pending']);
        Task::factory()->create(['status' => 'completed']);

        $pendingTasks = Task::byStatus('pending')->get();

        $this->assertCount(1, $pendingTasks);
        $this->assertEquals('pending', $pendingTasks->first()->status);
    }

    /**
     * Test the scopeByPriority method.
     */
    public function test_task_scope_by_priority()
    {
        Task::factory()->create(['priority' => 1]);
        Task::factory()->create(['priority' => 3]);

        $highPriorityTasks = Task::byPriority(1)->get();

        $this->assertCount(1, $highPriorityTasks);
        $this->assertEquals(1, $highPriorityTasks->first()->priority);
    }
}
