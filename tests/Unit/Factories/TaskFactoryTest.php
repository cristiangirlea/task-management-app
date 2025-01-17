<?php

namespace Tests\Unit\Factories;

use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use Tests\TestCase;

class TaskFactoryTest extends TestCase
{
    /**
     * Test that the factory creates a valid task.
     */
    public function test_task_factory_creates_valid_task()
    {
        $task = Task::factory()->create();

        $this->assertNotEmpty($task->title, 'Task title should not be empty.');
        $this->assertNotEmpty($task->description, 'Task description should not be empty.');
        $this->assertNotEmpty($task->status, 'Task status should not be empty.');
        $this->assertNotEmpty($task->priority, 'Task priority should not be empty.');
        $this->assertNotNull($task->user_id, 'Task should be associated with a user.');
        $this->assertNotNull($task->project_id, 'Task should be associated with a project.');
    }

    /**
     * Test that the factory generates a task with a specific user.
     */
    public function test_task_factory_creates_task_with_specific_user()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $task->user_id, 'Task should be associated with the specified user.');
    }

    /**
     * Test that the factory generates a task with a specific project.
     */
    public function test_task_factory_creates_task_with_specific_project()
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->assertEquals($project->id, $task->project_id, 'Task should be associated with the specified project.');
    }

    /**
     * Test that the factory generates a valid random status.
     */
    public function test_task_factory_generates_valid_status()
    {
        $task = Task::factory()->create();

        $validStatuses = ['pending', 'in_progress', 'completed'];

        $this->assertContains($task->status, $validStatuses, 'Task status should be valid.');
    }

    /**
     * Test that the factory generates tasks with future due dates.
     */
    public function test_task_factory_generates_future_due_date()
    {
        $task = Task::factory()->create();

        $this->assertTrue(
            $task->due_date >= now(),
            'Task due date should be in the present or future.'
        );
    }
}
