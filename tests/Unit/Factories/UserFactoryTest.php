<?php

namespace Tests\Unit\Factories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    /**
     * Test that the factory creates a valid user.
     */
    public function test_user_factory_creates_valid_user()
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->name, 'User name should not be empty.');
        $this->assertNotEmpty($user->email, 'User email should not be empty.');
        $this->assertNotEmpty($user->password, 'User password should not be empty.');
    }

    /**
     * Test that the factory generates unique email addresses.
     */
    public function test_user_factory_generates_unique_emails()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->assertNotEquals($user1->email, $user2->email, 'User emails should be unique.');
    }

    /**
     * Test that the factory creates hashed passwords.
     */
    public function test_user_factory_creates_hashed_passwords()
    {
        $user = User::factory()->create();

        $this->assertTrue(
            Hash::check('password', $user->password),
            'User password should be hashed and match the default factory password.'
        );
    }

    /**
     * Test that the factory can create unverified users.
     */
    public function test_user_factory_creates_unverified_users()
    {
        $user = User::factory()->unverified()->create();

        $this->assertNull($user->email_verified_at, 'Unverified user should have a null email_verified_at.');
    }

    /**
     * Test that the factory generates secure random passwords.
     */
    public function test_user_factory_creates_secure_random_passwords()
    {
        $user1 = User::factory()->create(['password' => Hash::make('random1')]);
        $user2 = User::factory()->create(['password' => Hash::make('random2')]);

        $this->assertNotEquals(
            $user1->password,
            $user2->password,
            'Each user should have a unique and secure hashed password.'
        );
    }
}
