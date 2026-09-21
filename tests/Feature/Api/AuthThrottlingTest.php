<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthThrottlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login');
        Notification::fake();
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong'])
            ->assertStatus(429);

        // The correct password is refused too while the limit holds.
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password'])
            ->assertStatus(429);
    }

    public function test_throttling_one_account_does_not_lock_out_another(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);
        User::factory()->create(['email' => 'grace@example.com']);

        foreach (range(1, 6) as $attempt) {
            $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['email' => 'grace@example.com', 'password' => 'password'])->assertOk();
    }

    public function test_password_reset_requests_are_throttled(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertStatus(429);
    }

    public function test_registration_is_throttled(): void
    {
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/register', [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])->assertCreated();
        }

        $this->postJson('/api/register', [
            'name' => 'One too many',
            'email' => 'extra@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(429);
    }
}
