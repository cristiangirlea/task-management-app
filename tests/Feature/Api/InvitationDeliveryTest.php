<?php

namespace Tests\Feature\Api;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\CreatesSubscriptions;
use Tests\TestCase;

/**
 * An invitation's link is shown once, so a failed email must never cost the
 * owner the link, and re-sending must stay safe under concurrency and abuse.
 */
class InvitationDeliveryTest extends TestCase
{
    use ActsAsTenantUser, CreatesSubscriptions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->actingAsTenantUser();
    }

    /** Make every email fail the way the mail provider's API errors do. */
    private function breakMail(): void
    {
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('The mail provider rejected the request.');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });

        config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
        app('mail.manager')->forgetMailers();
    }

    private function pending(string $email = 'slow@example.com'): Invitation
    {
        return Invitation::factory()->create([
            'tenant_id' => $this->owner->tenant_id,
            'invited_by' => $this->owner->id,
            'email' => $email,
        ]);
    }

    private function tokenFrom(string $url): string
    {
        return str($url)->afterLast('/invite/')->toString();
    }

    public function test_a_sent_invitation_says_so(): void
    {
        Mail::fake();

        $this->postJson('/api/tenant/invitations', ['email' => 'new@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.email_sent', true)
            ->assertJsonPath('message', __('invitation.store.success'));
    }

    public function test_when_the_email_fails_the_invitation_stands_and_the_owner_gets_the_link(): void
    {
        $this->breakMail();

        $response = $this->postJson('/api/tenant/invitations', ['email' => 'new@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.email_sent', false)
            ->assertJsonPath('message', __('invitation.store.mail_failed'));

        $this->getJson('/api/invitations/'.$this->tokenFrom($response->json('data.accept_url')))->assertOk();
    }

    public function test_when_a_resend_email_fails_the_new_link_is_returned_and_works(): void
    {
        $invitation = $this->pending();
        $this->breakMail();

        $response = $this->postJson("/api/tenant/invitations/{$invitation->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.email_sent', false)
            ->assertJsonPath('message', __('invitation.resend.mail_failed'));

        $this->getJson('/api/invitations/'.$this->tokenFrom($response->json('data.accept_url')))->assertOk();
        $this->getJson("/api/invitations/{$invitation->plainToken}")->assertNotFound();
    }

    public function test_an_invitation_whose_email_now_has_an_account_is_not_resent(): void
    {
        Mail::fake();
        $invitation = $this->pending('taken@example.com');
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson("/api/tenant/invitations/{$invitation->id}/resend")
            ->assertStatus(422)
            ->assertJsonPath('message', __('invitation.resend.registered'));

        Mail::assertNothingSent();
        $this->assertSame($invitation->token_hash, $invitation->fresh()->token_hash);
    }

    public function test_inviting_is_limited_per_owner_not_per_address(): void
    {
        Mail::fake();
        $this->subscribe($this->owner->tenant);

        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/tenant/invitations', ['email' => "person{$i}@example.com"])->assertCreated();
        }
        $this->postJson('/api/tenant/invitations', ['email' => 'person11@example.com'])->assertStatus(429);
        $this->postJson('/api/tenant/invitations/'.Invitation::withoutGlobalScopes()->value('id').'/resend')->assertStatus(429);

        // Another owner behind the same IP has their own allowance.
        $this->actingAsTenantUser();
        $this->postJson('/api/tenant/invitations', ['email' => 'elsewhere@example.com'])->assertCreated();
    }
}
