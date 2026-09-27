<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_requesting_a_link_emails_a_reset_url_pointing_at_the_frontend(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])
            ->assertOk()
            ->assertJsonPath('message', __('passwords.sent'));

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'http://localhost:3000/reset-password?')
                && str_contains($url, 'token='.$notification->token)
                && str_contains($url, urlencode('ada@example.com'));
        });
    }

    public function test_an_unknown_address_gets_the_same_response_so_accounts_cannot_be_enumerated(): void
    {
        $known = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);
        User::factory()->create(['email' => 'real@example.com']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'real@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());

        Notification::assertCount(1);
    }

    public function test_the_email_must_look_like_an_address(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_resetting_sets_the_new_password_and_revokes_every_existing_token(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $oldToken = $user->createToken('phone')->plainTextToken;
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ada@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk()->assertJsonPath('message', __('passwords.reset'));

        $this->assertDatabaseCount('personal_access_tokens', 0);

        app('auth')->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/user')->assertUnauthorized();

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'brand-new-password'])->assertOk();
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/reset-password', [
            'token' => 'not-the-real-token',
            'email' => 'ada@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertOk();
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $token = Password::createToken($user);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ada@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_the_new_password_must_be_confirmed_and_long_enough(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ada@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
}
