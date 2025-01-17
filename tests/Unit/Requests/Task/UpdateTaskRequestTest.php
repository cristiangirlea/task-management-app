<?php

namespace Tests\Unit\Requests\Project\Requests\Task;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateTaskRequestTest extends TestCase
{
    /**
     * Test valid input for UpdateTaskRequest.
     */
    public function test_valid_update_task_request()
    {
        $data = [
            'title' => 'Updated Task Title',
            'description' => 'This is an updated task description.',
            'status' => 'completed',
            'priority' => 2,
            'due_date' => now()->addDays(3)->toDateString(),
        ];

        $validator = Validator::make($data, [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'priority' => 'sometimes|integer|min:1|max:5',
            'due_date' => 'nullable|date|after_or_equal:today',
            'project_id' => 'sometimes|exists:projects,id',
        ]);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test missing optional fields for UpdateTaskRequest.
     */
    public function test_update_task_request_missing_optional_fields()
    {
        $data = []; // No fields provided

        $validator = Validator::make($data, [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'priority' => 'sometimes|integer|min:1|max:5',
            'due_date' => 'nullable|date|after_or_equal:today',
            'project_id' => 'sometimes|exists:projects,id',
        ]);

        $this->assertTrue($validator->passes()); // Missing fields are allowed for partial updates
    }

    /**
     * Test invalid values for UpdateTaskRequest.
     */
    public function test_update_task_request_invalid_values()
    {
        $data = [
            'status' => 'invalid_status',
            'priority' => 10, // Out of range
        ];

        $validator = Validator::make($data, [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'priority' => 'sometimes|integer|min:1|max:5',
            'due_date' => 'nullable|date|after_or_equal:today',
            'project_id' => 'sometimes|exists:projects,id',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
        $this->assertArrayHasKey('priority', $validator->errors()->toArray());
    }
}
