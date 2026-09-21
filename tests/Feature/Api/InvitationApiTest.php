<?php

namespace Tests\Feature\Api;

use App\Mail\WorkspaceInvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class InvitationApiTest extends TestCase
{
    use ActsAsTenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_owner_invites_by_email_and_a_mail_with_the_accept_link_is_sent(): void
    {
        $owner = $this->actingAsTenantUser();

        $response = $this->postJson('/api/tenant/invitations', ['email' => 'new@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.invited_by.id', $owner->id);

        $invitation = Invitation::withoutGlobalScopes()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame($owner->tenant_id, $invitation->tenant_id);
        $this->assertSame('http://localhost:3000/invite/'.$invitation->token, $response->json('data.accept_url'));

        Mail::assertSent(WorkspaceInvitationMail::class, fn (WorkspaceInvitationMail $mail) => $mail->hasTo('new@example.com')
            && $mail->invitation->is($invitation));
    }

    public function test_the_invitation_mail_renders_with_the_accept_link(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'new@example.com']);

        $html = (new WorkspaceInvitationMail($invitation))->render();

        $this->assertStringContainsString($invitation->acceptUrl(), $html);
        $this->assertStringContainsString($invitation->tenant->name, $html);
        $this->assertStringContainsString($invitation->inviter->name, $html);
    }

    public function test_members_cannot_invite(): void
    {
        $this->actingAsTenantUser(null, ['role' => User::ROLE_MEMBER]);

        $this->postJson('/api/tenant/invitations', ['email' => 'new@example.com'])->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_cannot_invite_an_existing_account_or_a_pending_email_twice(): void
    {
        $owner = $this->actingAsTenantUser();
        User::factory()->create(['email' => 'taken@example.com']);
        Invitation::factory()->create(['tenant_id' => $owner->tenant_id, 'email' => 'pending@example.com']);

        $this->postJson('/api/tenant/invitations', ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/tenant/invitations', ['email' => 'pending@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_an_expired_invitation_can_be_sent_again(): void
    {
        $owner = $this->actingAsTenantUser();
        Invitation::factory()->expired()->create(['tenant_id' => $owner->tenant_id, 'email' => 'late@example.com']);

        $this->postJson('/api/tenant/invitations', ['email' => 'late@example.com'])->assertCreated();
    }

    public function test_lists_only_pending_invitations_of_the_workspace(): void
    {
        $owner = $this->actingAsTenantUser();
        $pending = Invitation::factory()->create(['tenant_id' => $owner->tenant_id]);
        Invitation::factory()->expired()->create(['tenant_id' => $owner->tenant_id]);
        Invitation::factory()->accepted()->create(['tenant_id' => $owner->tenant_id]);
        Invitation::factory()->create(); // another workspace

        $this->getJson('/api/tenant/invitations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonMissingPath('data.0.token');
    }

    public function test_owner_revokes_an_invitation_and_cannot_touch_other_workspaces(): void
    {
        $owner = $this->actingAsTenantUser();
        $mine = Invitation::factory()->create(['tenant_id' => $owner->tenant_id]);
        $foreign = Invitation::factory()->create();

        $this->deleteJson("/api/tenant/invitations/{$mine->id}")->assertNoContent();
        $this->deleteJson("/api/tenant/invitations/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseMissing('invitations', ['id' => $mine->id]);
        $this->assertDatabaseHas('invitations', ['id' => $foreign->id]);
    }

    public function test_anyone_can_preview_an_invitation_by_token(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Acme']);
        $invitation = Invitation::factory()->create(['tenant_id' => $tenant->id, 'email' => 'new@example.com']);

        $this->getJson("/api/invitations/{$invitation->token}")
            ->assertOk()
            ->assertJsonPath('data.workspace.name', 'Acme')
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.invited_by', $invitation->inviter->name)
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/invitations/not-a-real-token')->assertNotFound();
    }

    public function test_accepting_creates_a_member_in_the_workspace_and_signs_them_in(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Acme']);
        $invitation = Invitation::factory()->create(['tenant_id' => $tenant->id, 'email' => 'new@example.com']);

        $response = $this->postJson("/api/invitations/{$invitation->token}/accept", [
            'name' => 'New Person',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'new@example.com')
            ->assertJsonPath('data.user.role', 'member')
            ->assertJsonPath('data.user.tenant.id', $tenant->id)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'tenant_id' => $tenant->id, 'role' => 'member']);
        $this->assertNotNull($invitation->fresh()->accepted_at);

        $this->withToken($response->json('data.token'))->getJson('/api/user')->assertOk()->assertJsonPath('data.tenant.name', 'Acme');
    }

    public function test_accept_validates_input(): void
    {
        $invitation = Invitation::factory()->create();

        $this->postJson("/api/invitations/{$invitation->token}/accept", ['name' => 'x', 'password' => 'short', 'password_confirmation' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_expired_or_used_invitations_cannot_be_accepted(): void
    {
        $payload = ['name' => 'New', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'];
        $expired = Invitation::factory()->expired()->create();
        $used = Invitation::factory()->accepted()->create();

        $this->postJson("/api/invitations/{$expired->token}/accept", $payload)->assertStatus(410);
        $this->postJson("/api/invitations/{$used->token}/accept", $payload)->assertStatus(410);
        $this->getJson("/api/invitations/{$expired->token}")->assertOk()->assertJsonPath('data.status', 'expired');

        $this->assertDatabaseMissing('users', ['email' => $expired->email]);
    }

    public function test_accept_refuses_when_the_email_registered_in_the_meantime(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'new@example.com']);
        User::factory()->create(['email' => 'new@example.com']);

        $this->postJson("/api/invitations/{$invitation->token}/accept", ['name' => 'New', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'])
            ->assertUnprocessable();
    }
}
