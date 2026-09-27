<?php

namespace Tests\Feature\Api;

use App\Mcp\Servers\TaskBoardServer;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListTasks;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Regressions for weaknesses found in a security review of this branch.
 * Each test names the property that must hold, so a future change that
 * reintroduces the weakness fails here with an explanation.
 */
class SecurityRegressionTest extends TestCase
{
    use ActsAsTenantUser;

    /**
     * An invitation's accept link contains the token that authenticates the
     * invitee. A member who could read it could consume the invitation and
     * take the invited person's identity inside the workspace.
     */
    public function test_a_member_cannot_read_a_pending_invitations_accept_link(): void
    {
        $member = $this->actingAsTenantUser(null, ['role' => User::ROLE_MEMBER]);
        $invitation = Invitation::factory()->create([
            'tenant_id' => $member->tenant_id,
            'email' => 'incoming-cfo@example.com',
        ]);

        $response = $this->getJson('/api/tenant/invitations');

        $response->assertForbidden();
        $this->assertStringNotContainsString($invitation->token, $response->getContent());

        // The real invitee's link still works.
        $this->postJson("/api/invitations/{$invitation->token}/accept", [
            'name' => 'The Real CFO',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated();
    }

    /**
     * Authorization runs before validation, so the uniqueness rules on the
     * invite form cannot be used as an oracle for which addresses have
     * accounts anywhere in the system.
     */
    public function test_a_member_inviting_is_refused_before_the_email_is_looked_up(): void
    {
        $this->actingAsTenantUser(null, ['role' => User::ROLE_MEMBER]);
        User::factory()->create(['email' => 'registered@example.com']);

        $registered = $this->postJson('/api/tenant/invitations', ['email' => 'registered@example.com']);
        $unregistered = $this->postJson('/api/tenant/invitations', ['email' => 'nobody@example.com']);

        $registered->assertForbidden();
        $unregistered->assertForbidden();
        $this->assertSame($registered->json(), $unregistered->json());
    }

    /**
     * A user with no workspace (which a database upgraded from an older
     * release can contain) must reach nothing.
     *
     * The REST endpoints refuse them outright via the policies. The MCP tools
     * have no policy of their own and lean entirely on the tenant scope, so
     * this also pins the scope's fail-closed behaviour: were it to fall back
     * to "no constraint", such a user would read every workspace's data.
     */
    public function test_a_user_without_a_workspace_reaches_nothing(): void
    {
        Project::factory()->count(2)->create(['name' => 'Someone elses project']);
        Task::factory()->count(2)->create(['title' => 'Someone elses task']);

        $stray = User::factory()->create();
        $stray->forceFill(['tenant_id' => null])->save();
        $stray = $stray->fresh();

        Sanctum::actingAs($stray);
        $this->getJson('/api/projects')->assertForbidden();
        $this->getJson('/api/tasks')->assertForbidden();

        TaskBoardServer::actingAs($stray)
            ->tool(ListProjects::class)
            ->assertOk()
            ->assertDontSee('Someone elses project');

        TaskBoardServer::actingAs($stray)
            ->tool(ListTasks::class)
            ->assertOk()
            ->assertDontSee('Someone elses task');

        // The data is still there; it is the reader who is confined.
        $this->assertSame(2, Project::withoutGlobalScopes()->where('name', 'Someone elses project')->count());
    }

    /**
     * Changing the password ends every other session, matching a reset.
     */
    public function test_changing_the_password_revokes_other_sessions_but_not_the_current_one(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $other = $user->createToken('other-device')->plainTextToken;
        $current = $user->createToken('this-device')->plainTextToken;

        $this->withToken($current)->putJson('/api/user', [
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertOk();

        app('auth')->forgetGuards();
        $this->withToken($other)->getJson('/api/user')->assertUnauthorized();

        app('auth')->forgetGuards();
        $this->withToken($current)->getJson('/api/user')->assertOk();
    }

    public function test_changing_only_the_name_leaves_other_sessions_alone(): void
    {
        $user = User::factory()->create();
        $other = $user->createToken('other-device')->plainTextToken;
        $current = $user->createToken('this-device')->plainTextToken;

        $this->withToken($current)->putJson('/api/user', ['name' => 'Renamed'])->assertOk();

        app('auth')->forgetGuards();
        $this->withToken($other)->getJson('/api/user')->assertOk();
    }
}
