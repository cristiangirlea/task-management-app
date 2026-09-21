<?php

namespace App\Http\Requests\Task;

use App\Validation\TaskRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return TaskRules::store($this->user()->tenant_id);
    }

    public function messages(): array
    {
        return TaskRules::messages();
    }
}
