<?php

namespace Tests\Feature\Api;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use ActsAsTenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function verificationUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_registering_sends_a_verification_email_and_leaves_the_account_unverified(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated()->assertJsonPath('data.user.email_verified_at', null);

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_following_the_link_verifies_the_address_and_returns_to_the_app(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))
            ->assertRedirect('http://localhost:3000/verify-email?status=success');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_a_link_for_an_already_verified_account_says_so(): void
    {
        $user = User::factory()->create();

        $this->get($this->verificationUrl($user))
            ->assertRedirect('http://localhost:3000/verify-email?status=already-verified');
    }

    public function test_a_hash_that_does_not_match_the_address_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user, sha1('someone-else@example.com')))
            ->assertRedirect('http://localhost:3000/verify-email?status=invalid');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_unsigned_or_expired_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $signed = $this->verificationUrl($user);

        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertRedirect('http://localhost:3000/verify-email?status=expired');

        $this->travel(61)->minutes();
        $this->get($signed)->assertRedirect('http://localhost:3000/verify-email?status=expired');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampering_with_the_id_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $victim = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        $this->get(str_replace("/verify/{$user->id}/", "/verify/{$victim->id}/", $url))
            ->assertRedirect('http://localhost:3000/verify-email?status=expired');

        $this->assertFalse($victim->fresh()->hasVerifiedEmail());
    }

    public function test_a_signed_in_user_can_ask_for_another_email(): void
    {
        $user = $this->actingAsTenantUser(null, ['email_verified_at' => null]);

        $this->postJson('/api/email/verification-notification')->assertStatus(202);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resending_for_a_verified_account_sends_nothing(): void
    {
        $this->actingAsTenantUser();

        $this->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', __('verification.already_verified'));

        Notification::assertNothingSent();
    }

    public function test_resending_requires_authentication(): void
    {
        $this->postJson('/api/email/verification-notification')->assertUnauthorized();
    }

    public function test_accepting_an_invitation_counts_as_verifying_the_address(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'new@example.com']);

        $this->postJson("/api/invitations/{$invitation->token}/accept", [
            'name' => 'New Person',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();

        $this->assertTrue(User::where('email', 'new@example.com')->firstOrFail()->hasVerifiedEmail());
    }
}
