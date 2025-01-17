<?php

namespace Tests\Unit\Requests\Project\Requests\Task;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreTaskRequestTest extends TestCase
{
    /**
     * Test valid input.
     */
    public function test_valid_input()
    {
        $data = [
            'name' => 'Valid Task Name',
            'description' => 'This is a valid task description.',
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test missing name field.
     */
    public function test_missing_name_field()
    {
        $data = [
            'description' => 'This is a valid description.',
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /**
     * Test invalid description type.
     */
    public function test_invalid_description_type()
    {
        $data = [
            'name' => 'Valid Task Name',
            'description' => 12345, // Invalid type
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }

    /**
     * Test description exceeds max length.
     */
    public function test_description_exceeds_max_length()
    {
        $data = [
            'name' => 'Valid Task Name',
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
