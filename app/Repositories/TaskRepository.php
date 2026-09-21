<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * All queries go through the Task model, so the tenant global scope applies.
 */
class TaskRepository
{
    /**
     * Tasks for the board, optionally filtered by project and/or status,
     * ordered the way a Kanban column displays them.
     *
     * @param  array{project_id?: int|null, status?: string|null}  $filters
     */
    public function list(array $filters = []): Collection
    {
        return $this->query()
            ->when($filters['project_id'] ?? null, fn (Builder $q, int $projectId) => $q->where('project_id', $projectId))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function getTasksByProject(int $projectId): Collection
    {
        return $this->list(['project_id' => $projectId]);
    }

    /**
     * Next free position at the bottom of a Kanban column.
     */
    public function nextPosition(int $projectId, string $status): int
    {
        $max = $this->query()
            ->where('project_id', $projectId)
            ->where('status', $status)
            ->max('position');

        return $max === null ? 0 : $max + 1;
    }

    public function create(array $data): Task
    {
        return Task::create($data);
    }

    public function find(int $taskId): Task
    {
        return Task::findOrFail($taskId);
    }

    public function findMany(array $ids): Collection
    {
        return $this->query()->whereIn('id', $ids)->orderBy('position')->get();
    }

    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    /**
     * Put the given tasks into $status, positioned by their index in $taskIds.
     */
    public function reorder(string $status, array $taskIds): void
    {
        foreach (array_values($taskIds) as $position => $taskId) {
            $this->query()->whereKey($taskId)->update([
                'status' => $status,
                'position' => $position,
            ]);
        }
    }

    private function query(): Builder
    {
        return Task::query();
    }
}
