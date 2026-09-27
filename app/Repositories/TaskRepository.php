<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * All queries go through the Task model, so the tenant global scope applies.
 *
 * @phpstan-type Filters array{
 *     project_id?: int|null,
 *     status?: string|null,
 *     assigned_to?: int|null,
 *     due_before?: string|null,
 *     search?: string|null,
 *     overdue?: bool|null,
 *     limit?: int|null,
 * }
 */
class TaskRepository
{
    /**
     * Tasks matching $filters, ordered the way a Kanban column displays them.
     *
     * With no `limit` every match is returned, which is what the board wants
     * for a single project. Callers that cannot bound the result themselves
     * (an agent asking across the whole workspace) should pass one.
     *
     * @param  Filters  $filters
     */
    public function list(array $filters = []): Collection
    {
        return $this->filtered($filters)
            ->when($filters['limit'] ?? null, fn (Builder $q, int $limit) => $q->limit($limit))
            ->get();
    }

    /**
     * How many tasks match, ignoring any limit, so a caller can tell whether
     * what it received is the whole picture.
     *
     * @param  Filters  $filters
     */
    public function countMatching(array $filters = []): int
    {
        return $this->filtered($filters)->count();
    }

    /**
     * @param  Filters  $filters
     */
    private function filtered(array $filters): Builder
    {
        return $this->query()
            ->when($filters['project_id'] ?? null, fn (Builder $q, int $id) => $q->where('project_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['assigned_to'] ?? null, fn (Builder $q, int $userId) => $q->assignedTo($userId))
            ->when($filters['due_before'] ?? null, fn (Builder $q, string $date) => $q->dueBefore($date))
            ->when($filters['search'] ?? null, fn (Builder $q, string $term) => $q->matching($term))
            ->when($filters['overdue'] ?? false, fn (Builder $q) => $q->overdue())
            ->orderBy('position')
            ->orderBy('id');
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
