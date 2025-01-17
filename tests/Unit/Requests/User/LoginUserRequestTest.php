<?php

namespace Tests\Unit\Requests\User;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LoginUserRequestTest extends TestCase
{
    /**
     * Test valid input.
     */
    public function test_valid_input()
    {
        $data = [
            'email' => 'valid@example.com',
            'password' => 'securepassword',
        ];

        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test missing email and password fields.
     */
    public function test_missing_email_and_password()
    {
        $data = [];

        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * Test invalid email format.
     */
    public function test_invalid_email_format()
    {
        $data = [
            'email' => 'invalid-email',
            'password' => 'securepassword',
        ];

        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * Test missing password field.
     */
    public function test_missing_password_field()
    {
        $data = [
            'email' => 'valid@example.com',
        ];

        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }
}
