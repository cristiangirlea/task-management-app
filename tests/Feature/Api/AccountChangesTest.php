<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * A token alone, stolen or an API token, must not be enough to take an
 * account over or delete it: those changes ask for the password.
 */
class AccountChangesTest extends TestCase
{
    use ActsAsTenantUser;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsTenantUser();
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $new = ['password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password'];

        $this->putJson('/api/user', $new)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
        $this->putJson('/api/user', [...$new, 'current_password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));

        $this->putJson('/api/user', [...$new, 'current_password' => 'password'])->assertOk();
        $this->assertTrue(Hash::check('a-brand-new-password', $this->user->fresh()->password));
    }

    public function test_changing_the_email_needs_the_password_and_verifies_the_new_address(): void
    {
        Notification::fake();

        $this->putJson('/api/user', ['email' => 'new@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
        $this->assertNotSame('new@example.com', $this->user->fresh()->email);

        $this->putJson('/api/user', ['email' => 'new@example.com', 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.email_verified_at', null);

        Notification::assertSentTo($this->user, VerifyEmailNotification::class);
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_sending_the_same_email_back_needs_no_password(): void
    {
        Notification::fake();

        $this->putJson('/api/user', ['name' => 'Renamed', 'email' => $this->user->email])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        Notification::assertNothingSent();
        $this->assertNotNull($this->user->fresh()->email_verified_at);
    }

    public function test_the_email_change_stands_when_the_verification_mail_fails(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('The mail provider is down.'));

        $this->putJson('/api/user', ['email' => 'new@example.com', 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertSame('new@example.com', $this->user->fresh()->email);
    }

    public function test_deleting_the_account_needs_the_password(): void
    {
        $this->deleteJson('/api/user')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
        $this->deleteJson('/api/user', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
        $this->assertModelExists($this->user);

        $this->deleteJson('/api/user', ['password' => 'password'])->assertNoContent();
        $this->assertModelMissing($this->user);
    }

    public function test_guessing_the_password_through_these_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->deleteJson('/api/user', ['password' => "guess-{$i}"])->assertUnprocessable();
        }

        $this->deleteJson('/api/user', ['password' => 'password'])->assertTooManyRequests();
        $this->putJson('/api/user', ['name' => 'Renamed'])->assertTooManyRequests();
        $this->assertModelExists($this->user);
    }
}
