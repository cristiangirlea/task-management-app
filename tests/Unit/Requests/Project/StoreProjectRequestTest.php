<?php

namespace Tests\Unit\Requests\Project;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreProjectRequestTest extends TestCase
{
    /**
     * Test valid input.
     */
    public function test_valid_input()
    {
        $data = [
            'name' => 'Valid Project Name',
            'description' => 'This is a valid project description.',
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
            'name' => 'Valid Project Name',
            'description' => ['not', 'a', 'string'],
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
            'name' => 'Valid Project Name',
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
