<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use App\Validation\TaskRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'project_id' => ['sometimes', 'integer', TaskRules::projectExists($tenantId)],
            'status' => ['sometimes', Rule::in(Task::STATUSES)],
        ];
    }
}
