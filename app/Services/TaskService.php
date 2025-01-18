<?php

namespace App\Services;

use App\Repositories\TaskRepository;
use Illuminate\Support\Facades\DB;

class TaskService
{
    protected TaskRepository $taskRepository;

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    /**
     * Get tasks by project ID.
     *
     * @param int $projectId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTasksByProject(int $projectId)
    {
        return $this->taskRepository->getTasksByProject($projectId);
    }

    /**
     * Create a new task.
     *
     * @param array $data
     * @return \App\Models\Task
     */
    public function createTask(array $data)
    {
        $maxPriority = $this->taskRepository->getMaxPriorityByProject($data['project_id']);
        $data['priority'] = $maxPriority + 1;

        return $this->taskRepository->create($data);
    }

    /**
     * Update an existing task.
     *
     * @param int $taskId
     * @param array $data
     * @return \App\Models\Task
     */
    public function updateTask(int $taskId, array $data)
    {
        $task = $this->taskRepository->find($taskId);
        return $this->taskRepository->update($task, $data);
    }

    /**
     * Delete a task.
     *
     * @param int $taskId
     * @return void
     */
    public function deleteTask(int $taskId): void
    {
        $task = $this->taskRepository->find($taskId);
        $this->taskRepository->delete($task);
    }

    /**
     * Reorder tasks based on an array of task IDs.
     *
     * @param array $priorities
     * @return void
     */
    public function reorderTasks(array $priorities): void
    {
        DB::transaction(function () use ($priorities) {
            $this->taskRepository->reorderTasks($priorities);
        });
    }
}
