<?php

namespace App\Services;

use App\Models\Task;
use App\Repositories\TaskRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(protected TaskRepository $taskRepository) {}

    /**
     * @param  array{project_id?: int|null, status?: string|null}  $filters
     */
    public function listTasks(array $filters = []): Collection
    {
        return $this->taskRepository->list($filters);
    }

    public function getTasksByProject(int $projectId): Collection
    {
        return $this->taskRepository->getTasksByProject($projectId);
    }

    /**
     * Create a task at the bottom of its Kanban column.
     */
    public function createTask(array $data): Task
    {
        $data['status'] = $data['status'] ?? Task::STATUS_PENDING;
        $data['position'] = $this->taskRepository->nextPosition((int) $data['project_id'], $data['status']);

        return $this->taskRepository->create($data);
    }

    /**
     * Update a task. When it changes column (status) or project without an
     * explicit position, it goes to the bottom of the destination column.
     */
    public function updateTask(Task $task, array $data): Task
    {
        $status = $data['status'] ?? $task->status;
        $projectId = (int) ($data['project_id'] ?? $task->project_id);
        $movedColumn = $status !== $task->status || $projectId !== $task->project_id;

        if ($movedColumn && ! array_key_exists('position', $data)) {
            $data['position'] = $this->taskRepository->nextPosition($projectId, $status);
        }

        return $this->taskRepository->update($task, $data);
    }

    public function deleteTask(Task $task): void
    {
        $this->taskRepository->delete($task);
    }

    /**
     * Move one task to a column at a given index (bottom when null),
     * renumbering both the destination and the source column.
     */
    public function moveTask(Task $task, string $status, ?int $position = null): Task
    {
        DB::transaction(function () use ($task, $status, $position) {
            $destination = $this->taskRepository
                ->list(['project_id' => $task->project_id, 'status' => $status])
                ->reject(fn (Task $other) => $other->is($task))
                ->pluck('id')
                ->values()
                ->all();

            $index = $position === null ? count($destination) : max(0, min($position, count($destination)));
            array_splice($destination, $index, 0, [$task->id]);
            $this->taskRepository->reorder($status, $destination);

            if ($status !== $task->status) {
                $source = $this->taskRepository
                    ->list(['project_id' => $task->project_id, 'status' => $task->status])
                    ->reject(fn (Task $other) => $other->is($task))
                    ->pluck('id')
                    ->all();
                $this->taskRepository->reorder($task->status, $source);
            }
        });

        return $task->refresh();
    }

    /**
     * Move the listed tasks into $status in the given order and return them.
     */
    public function reorderTasks(string $status, array $taskIds): Collection
    {
        DB::transaction(function () use ($status, $taskIds) {
            $this->taskRepository->reorder($status, $taskIds);
        });

        return $this->taskRepository->findMany($taskIds);
    }
}
