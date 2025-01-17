<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,in_progress,completed',
            'priority' => 'required|integer|min:1|max:5',
            'due_date' => 'nullable|date|after_or_equal:today',
            'project_id' => 'required|exists:projects,id',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Task title is required.',
            'title.string' => 'Task title must be a valid string.',
            'title.max' => 'Task title cannot exceed 255 characters.',
            'status.required' => 'Task status is required.',
            'status.in' => 'Task status must be either pending, in progress, or completed.',
            'priority.required' => 'Priority is required.',
            'priority.integer' => 'Priority must be an integer.',
            'priority.min' => 'Priority must be at least 1.',
            'priority.max' => 'Priority cannot exceed 5.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after_or_equal' => 'The due date must be today or a future date.',
            'project_id.required' => 'The project ID is required.',
            'project_id.exists' => 'The specified project does not exist.',
        ];
    }
}
