<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository
{
    /**
     * Get tasks by project ID.
     *
     * @param int $projectId
     * @return Collection
     */
    public function getTasksByProject(int $projectId): Collection
    {
        return Task::where('project_id', $projectId)->orderBy('priority')->get();
    }

    /**
     * Get the maximum priority for a project.
     *
     * @param int $projectId
     * @return int
     */
    public function getMaxPriorityByProject(int $projectId): int
    {
        return Task::where('project_id', $projectId)->max('priority') ?? 0;
    }

    /**
     * Create a new task.
     *
     * @param array $data
     * @return Task
     */
    public function create(array $data): Task
    {
        return Task::create($data);
    }

    /**
     * Find a task by ID.
     *
     * @param int $taskId
     * @return Task
     */
    public function find(int $taskId): Task
    {
        return Task::findOrFail($taskId);
    }

    /**
     * Update a task.
     *
     * @param Task $task
     * @param array $data
     * @return Task
     */
    public function update(Task $task, array $data): Task
    {
        $task->update($data);
        return $task;
    }

    /**
     * Delete a task.
     *
     * @param Task $task
     * @return void
     */
    public function delete(Task $task): void
    {
        $task->delete();
    }

    /**
     * Reorder tasks based on an array of task IDs.
     *
     * @param array $priorities
     * @return void
     */
    public function reorderTasks(array $priorities): void
    {
        foreach ($priorities as $index => $taskId) {
            Task::where('id', $taskId)->update(['priority' => $index + 1]);
        }
    }
}
