<?php

namespace App\Http\Requests\Task;

use App\Validation\TaskRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return TaskRules::update($this->user()->tenant_id);
    }

    public function messages(): array
    {
        return TaskRules::messages();
    }
}
