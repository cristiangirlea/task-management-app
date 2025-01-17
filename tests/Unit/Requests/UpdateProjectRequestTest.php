<?php

namespace Tests\Unit\Requests;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateProjectRequestTest extends TestCase
{
    public function test_valid_update_project_request()
    {
        $data = [
            'name' => 'Updated Project Name',
            'description' => 'This is an updated project description.',
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_missing_name_in_update_project_request()
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

    public function test_invalid_description_in_update_project_request()
    {
        $data = [
            'name' => 'Valid Name',
            'description' => ['invalid', 'array'],
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }

    public function test_description_exceeds_max_length_in_update_project_request()
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
