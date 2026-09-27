<?php

namespace Tests\Feature\Api;

use App\Mail\WorkspaceInvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Invitation links are secrets stored only as hashes: a copy of the database
 * (a leak, an off-site backup) must not contain a working link. The link is
 * shown when it is issued; after that the owner re-sends to get a new one.
 */
class InvitationTokenTest extends TestCase
{
    use ActsAsTenantUser;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->owner = $this->actingAsTenantUser();
    }

    private function tokenFrom(string $url): string
    {
        return str($url)->afterLast('/invite/')->toString();
    }

    private function accept(string $token)
    {
        app('auth')->forgetGuards();

        return $this->postJson("/api/invitations/{$token}/accept", [
            'name' => 'New Person',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);
    }

    public function test_the_database_never_holds_a_working_link(): void
    {
        $url = $this->postJson('/api/tenant/invitations', ['email' => 'new@example.com'])->json('data.accept_url');
        $token = $this->tokenFrom($url);

        $row = (array) DB::table('invitations')->first();

        $this->assertArrayNotHasKey('token', $row);
        $this->assertStringNotContainsString($token, json_encode($row));
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->getJson("/api/invitations/{$token}")->assertOk();
    }

    public function test_the_link_is_shown_once_and_not_in_the_list(): void
    {
        $this->postJson('/api/tenant/invitations', ['email' => 'new@example.com'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['accept_url']]);

        $this->getJson('/api/tenant/invitations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.accept_url');
    }

    public function test_resending_replaces_the_link_and_extends_the_expiry(): void
    {
        $invitation = Invitation::factory()->create([
            'tenant_id' => $this->owner->tenant_id,
            'invited_by' => $this->owner->id,
            'email' => 'slow@example.com',
            'expires_at' => now()->addDay(),
        ]);
        $oldToken = $invitation->plainToken;

        $response = $this->postJson("/api/tenant/invitations/{$invitation->id}/resend")
            ->assertOk()
            ->assertJsonPath('message', __('invitation.resend.success'));

        $newUrl = $response->json('data.accept_url');
        $newToken = $this->tokenFrom($newUrl);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertTrue($invitation->fresh()->expires_at->greaterThan(now()->addDays(Invitation::LIFETIME_DAYS - 1)));

        Mail::assertSent(WorkspaceInvitationMail::class, fn (WorkspaceInvitationMail $mail) => $mail->hasTo('slow@example.com')
            && $mail->acceptUrl === $newUrl);

        $this->getJson("/api/invitations/{$oldToken}")->assertNotFound();
        $this->accept($newToken)->assertCreated();
    }

    public function test_only_owners_resend_and_only_their_own_workspaces_invitations(): void
    {
        $mine = Invitation::factory()->create(['tenant_id' => $this->owner->tenant_id, 'invited_by' => $this->owner->id]);
        $foreign = Invitation::factory()->create();

        $this->postJson("/api/tenant/invitations/{$foreign->id}/resend")->assertNotFound();

        $this->actingAsTenantUser($this->owner->tenant, ['role' => User::ROLE_MEMBER]);
        $this->postJson("/api/tenant/invitations/{$mine->id}/resend")->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_accepted_and_expired_invitations_cannot_be_resent(): void
    {
        $accepted = Invitation::factory()->accepted()->create(['tenant_id' => $this->owner->tenant_id, 'invited_by' => $this->owner->id]);
        $expired = Invitation::factory()->expired()->create(['tenant_id' => $this->owner->tenant_id, 'invited_by' => $this->owner->id]);

        $this->postJson("/api/tenant/invitations/{$accepted->id}/resend")
            ->assertStatus(410)
            ->assertJsonPath('message', __('invitation.resend.unavailable'));
        $this->postJson("/api/tenant/invitations/{$expired->id}/resend")->assertStatus(410);

        Mail::assertNothingSent();
    }

    /**
     * Links sent before the upgrade keep working: the migration hashes the
     * stored tokens in place.
     */
    public function test_links_sent_before_hashing_still_work_after_the_migration(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('invitations')->insert([
            'tenant_id' => $tenant->id,
            'email' => 'early@example.com',
            'token' => $legacy = str_repeat('a1', 32),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertSame('early@example.com', Invitation::findByToken($legacy)?->email);
        $this->accept($legacy)->assertCreated();
    }
}
