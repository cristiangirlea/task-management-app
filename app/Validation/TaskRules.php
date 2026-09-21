<?php

namespace App\Validation;

use App\Models\Task;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation rules for tasks, shared by the HTTP form requests and the MCP
 * tools so both surfaces enforce the same tenant-aware constraints.
 */
class TaskRules
{
    /**
     * @return array<string, mixed>
     */
    public static function store(int $tenantId): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => ['sometimes', Rule::in(Task::STATUSES)],
            'priority' => 'sometimes|integer|min:1|max:5',
            'due_date' => 'nullable|date',
            'project_id' => ['required', 'integer', static::projectExists($tenantId)],
            'user_id' => ['nullable', 'integer', static::userExists($tenantId)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(int $tenantId): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => ['sometimes', Rule::in(Task::STATUSES)],
            'priority' => 'sometimes|integer|min:1|max:5',
            'position' => 'sometimes|integer|min:0',
            'due_date' => 'nullable|date',
            'project_id' => ['sometimes', 'integer', static::projectExists($tenantId)],
            'user_id' => ['nullable', 'integer', static::userExists($tenantId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'title.required' => 'Task title is required.',
            'title.max' => 'Task title cannot exceed 255 characters.',
            'status.in' => 'Task status must be one of: pending, in_progress, completed.',
            'priority.integer' => 'Priority must be an integer.',
            'priority.min' => 'Priority must be at least 1.',
            'priority.max' => 'Priority cannot exceed 5.',
            'due_date.date' => 'The due date must be a valid date.',
            'project_id.required' => 'The project ID is required.',
            'project_id.exists' => 'The specified project does not exist.',
            'user_id.exists' => 'The specified assignee does not exist.',
            'task_id.exists' => 'The specified task does not exist.',
        ];
    }

    public static function projectExists(?int $tenantId): Exists
    {
        return static::scoped(Rule::exists('projects', 'id'), $tenantId);
    }

    public static function userExists(?int $tenantId): Exists
    {
        return static::scoped(Rule::exists('users', 'id'), $tenantId);
    }

    public static function taskExists(?int $tenantId): Exists
    {
        return static::scoped(Rule::exists('tasks', 'id'), $tenantId)->whereNull('deleted_at');
    }

    /**
     * Confine an existence check to one workspace.
     *
     * A caller with no workspace matches nothing rather than everything: such
     * a user should not be able to reference any record, and should get a
     * validation failure rather than a crash.
     */
    private static function scoped(Exists $rule, ?int $tenantId): Exists
    {
        return $tenantId === null
            ? $rule->where(fn (Builder $query) => $query->whereRaw('1 = 0'))
            : $rule->where('tenant_id', $tenantId);
    }
}
