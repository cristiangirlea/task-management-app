<?php

namespace Tests\Feature\Api;

use App\Events\BoardChanged;
use App\Mcp\Servers\TaskBoardServer;
use App\Mcp\Tools\MoveTask;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class BoardBroadcastTest extends TestCase
{
    use ActsAsTenantUser;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsTenantUser();
        $this->project = Project::factory()->create(['tenant_id' => $this->user->tenant_id]);
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->create($attributes + ['project_id' => $this->project->id, 'user_id' => null]);
    }

    /**
     * @return list<int>
     */
    private function announcedProjects(): array
    {
        return Event::dispatched(BoardChanged::class)->map(fn (array $call) => $call[0]->projectId)->values()->all();
    }

    /** Reverb as the broadcaster, with the channels registered on it, as in production. */
    private function useReverb(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'app-key',
            'broadcasting.connections.reverb.secret' => 'app-secret',
            'broadcasting.connections.reverb.app_id' => 'app-id',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
    }

    public function test_every_change_through_the_api_announces_the_project(): void
    {
        $task = $this->task(['status' => 'pending']);
        $other = $this->task(['status' => 'pending']);
        Event::fake([BoardChanged::class]);

        $this->postJson('/api/tasks', ['title' => 'New', 'project_id' => $this->project->id])->assertCreated();
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Renamed'])->assertOk();
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'completed'])->assertOk();
        $this->postJson('/api/tasks/reorder', ['status' => 'pending', 'task_ids' => [$other->id]])->assertOk();
        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertSame(array_fill(0, 5, $this->project->id), $this->announcedProjects());
    }

    public function test_moving_a_task_to_another_project_announces_both(): void
    {
        $task = $this->task();
        $destination = Project::factory()->create(['tenant_id' => $this->user->tenant_id]);
        Event::fake([BoardChanged::class]);

        $this->putJson("/api/tasks/{$task->id}", ['project_id' => $destination->id])->assertOk();

        $this->assertSame([$this->project->id, $destination->id], $this->announcedProjects());
    }

    public function test_changes_made_by_an_mcp_client_are_announced(): void
    {
        $task = $this->task(['status' => 'pending']);
        Event::fake([BoardChanged::class]);

        TaskBoardServer::actingAs($this->user)
            ->tool(MoveTask::class, ['task_id' => $task->id, 'status' => 'in_progress'])
            ->assertOk();

        $this->assertSame([$this->project->id], $this->announcedProjects());
    }

    public function test_the_browser_that_made_the_change_is_not_told_about_it(): void
    {
        $task = $this->task();
        Event::fake([BoardChanged::class]);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Renamed'], ['X-Socket-ID' => '1234.5678'])->assertOk();

        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event) => $event->socket === '1234.5678');
    }

    public function test_nothing_is_announced_for_a_change_that_is_rolled_back(): void
    {
        Event::fake([BoardChanged::class]);

        try {
            DB::transaction(function () {
                app(TaskService::class)->createTask(['title' => 'Never saved', 'project_id' => $this->project->id]);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(BoardChanged::class);
        $this->assertDatabaseMissing('tasks', ['title' => 'Never saved']);
    }

    public function test_a_change_is_saved_even_when_reverb_cannot_be_reached(): void
    {
        $this->useReverb();
        Exceptions::fake();
        $task = $this->task();

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Saved anyway'])->assertOk();

        $this->assertSame('Saved anyway', $task->fresh()->title);
        Exceptions::assertReported(BroadcastException::class);
    }

    public function test_members_of_the_workspace_may_listen_to_its_projects_only(): void
    {
        $this->useReverb();
        $foreign = Project::factory()->create();
        $auth = fn (Project $project) => $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-projects.{$project->id}",
        ]);

        $auth($this->project)->assertOk()->assertJsonStructure(['auth']);
        $auth($foreign)->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create(['tenant_id' => $foreign->tenant_id]));
        $auth($this->project)->assertForbidden();
        $auth($foreign)->assertOk();
    }

    public function test_listening_needs_a_signed_in_user(): void
    {
        $this->useReverb();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-projects.{$this->project->id}",
        ])->assertUnauthorized();
    }
}
