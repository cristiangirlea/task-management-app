<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class MemberApiTest extends TestCase
{
    use ActsAsTenantUser;

    public function test_lists_workspace_members_owners_first(): void
    {
        $owner = $this->actingAsTenantUser(null, ['name' => 'Zed Owner']);
        User::factory()->member()->create(['tenant_id' => $owner->tenant_id, 'name' => 'Amy Member']);
        User::factory()->create(); // another workspace

        $response = $this->getJson('/api/tenant/members')->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(['Zed Owner', 'Amy Member'], collect($response->json('data'))->pluck('name')->all());
        $this->assertSame(['owner', 'member'], collect($response->json('data'))->pluck('role')->all());
    }

    public function test_registration_makes_the_first_user_an_owner(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertCreated()->assertJsonPath('data.user.role', 'owner');
    }

    public function test_owner_removes_a_member_and_their_tasks_become_unassigned(): void
    {
        $owner = $this->actingAsTenantUser();
        $member = User::factory()->member()->create(['tenant_id' => $owner->tenant_id]);
        $member->createToken('claude');
        $task = Task::factory()->create([
            'project_id' => Project::factory()->create(['tenant_id' => $owner->tenant_id])->id,
            'user_id' => $member->id,
        ]);

        $this->deleteJson("/api/tenant/members/{$member->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $member->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'user_id' => null]);
    }

    public function test_members_cannot_remove_anyone_and_owners_cannot_remove_owners_or_themselves(): void
    {
        $owner = $this->actingAsTenantUser();
        $otherOwner = User::factory()->create(['tenant_id' => $owner->tenant_id]);
        $member = User::factory()->member()->create(['tenant_id' => $owner->tenant_id]);

        $this->deleteJson("/api/tenant/members/{$otherOwner->id}")->assertUnprocessable();
        $this->deleteJson("/api/tenant/members/{$owner->id}")->assertUnprocessable();

        $this->actingAsTenantUser($owner->tenant, ['role' => User::ROLE_MEMBER]);
        $this->deleteJson("/api/tenant/members/{$member->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }

    public function test_cannot_remove_users_of_other_workspaces(): void
    {
        $this->actingAsTenantUser();
        $stranger = User::factory()->member()->create();

        $this->deleteJson("/api/tenant/members/{$stranger->id}")->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $stranger->id]);
    }

    public function test_only_owners_can_rename_the_workspace(): void
    {
        $owner = $this->actingAsTenantUser();
        $this->putJson('/api/tenant', ['name' => 'Renamed by owner'])->assertOk();

        $this->actingAsTenantUser($owner->tenant, ['role' => User::ROLE_MEMBER]);
        $this->putJson('/api/tenant', ['name' => 'Renamed by member'])->assertForbidden();
    }
}
