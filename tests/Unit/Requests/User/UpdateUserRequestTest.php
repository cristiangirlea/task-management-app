<?php

namespace Tests\Unit\Requests\User;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateUserRequestTest extends TestCase
{
    /**
     * Test optional fields are valid.
     */
    public function test_it_allows_optional_fields()
    {
        $data = [
            'name' => 'Updated Name',
        ];

        $validator = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test unique email validation.
     */
    public function test_it_validates_unique_email()
    {
        $existingEmail = 'existing@example.com';

        // Simulate an existing user
        \App\Models\User::factory()->create(['email' => $existingEmail]);

        $data = [
            'email' => $existingEmail,
        ];

        $validator = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }
}
