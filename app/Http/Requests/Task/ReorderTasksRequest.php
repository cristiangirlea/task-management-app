<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use App\Validation\TaskRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moves the listed tasks into a Kanban column ($status) in the given order.
 */
class ReorderTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'status' => ['required', Rule::in(Task::STATUSES)],
            'task_ids' => 'present|array',
            'task_ids.*' => ['integer', 'distinct', TaskRules::taskExists($tenantId)],
        ];
    }

    public function messages(): array
    {
        return [
            'task_ids.*.exists' => 'One or more tasks do not exist.',
        ];
    }
}
