<?php

namespace Tests\Unit\Requests;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateTaskRequestTest extends TestCase
{
    public function test_valid_update_task_request()
    {
        $data = [
            'name' => 'Updated Task Name',
            'description' => 'This is an updated task description.',
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_missing_name_in_update_task_request()
    {
        $data = [
            'description' => 'Valid description but no name.',
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_invalid_description_in_update_task_request()
    {
        $data = [
            'name' => 'Valid Name',
            'description' => 12345, // Invalid type
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }

    public function test_description_exceeds_max_length_in_update_task_request()
    {
        $data = [
            'name' => 'Valid Name',
            'description' => str_repeat('a', 1001), // 1001 characters
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }
}
