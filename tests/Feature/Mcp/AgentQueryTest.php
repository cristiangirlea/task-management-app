<?php

namespace Tests\Feature\Mcp;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The queries an agent actually receives ("what's overdue?", "what's mine?",
 * "find the invoicing work") must be answerable in one call, and a large
 * workspace must not return an unbounded pile of tasks.
 */
class AgentQueryTest extends TestCase
{
    private User $user;

    private Project $project;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->user->tenant_id, 'name' => 'Launch']);
        $this->token = $this->user->createToken('agent')->plainTextToken;
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->create($attributes + [
            'project_id' => $this->project->id,
            'user_id' => null,
            'status' => 'pending',
            'due_date' => null,
        ]);
    }

    /**
     * Call a tool the way a real client does, over the HTTP transport.
     *
     * @return array<string, mixed>
     */
    private function runTool(string $tool, array $arguments = []): array
    {
        $response = $this->callTool($tool, $arguments);

        $this->assertFalse($response->json('result.isError'), "Tool [{$tool}] returned an error.");

        return json_decode($response->json('result.content.0.text'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function callTool(string $tool, array $arguments = []): TestResponse
    {
        return $this->withToken($this->token)
            ->withHeaders(['Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => $tool, 'arguments' => (object) $arguments],
            ])
            ->assertOk();
    }

    public function test_overdue_work_is_one_filtered_call(): void
    {
        $late = $this->task(['title' => 'Late invoice', 'due_date' => now()->subWeek()]);
        $this->task(['title' => 'Future work', 'due_date' => now()->addWeek()]);
        $this->task(['title' => 'Late but done', 'due_date' => now()->subWeek(), 'status' => 'completed']);

        $result = $this->runTool('list_tasks', ['overdue' => true]);

        $this->assertSame(1, $result['total_matching']);
        $this->assertSame([$late->id], array_column($result['tasks'], 'id'));
    }

    public function test_an_agent_can_ask_for_the_callers_own_work(): void
    {
        $colleague = User::factory()->member()->create(['tenant_id' => $this->user->tenant_id]);
        $mine = $this->task(['title' => 'Mine', 'user_id' => $this->user->id]);
        $this->task(['title' => 'Theirs', 'user_id' => $colleague->id]);

        $result = $this->runTool('list_tasks', ['assigned_to_me' => true]);
        $this->assertSame([$mine->id], array_column($result['tasks'], 'id'));

        $theirs = $this->runTool('list_tasks', ['assigned_to' => $colleague->id]);
        $this->assertSame(['Theirs'], array_column($theirs['tasks'], 'title'));
    }

    public function test_search_matches_title_and_description_and_escapes_wildcards(): void
    {
        $byTitle = $this->task(['title' => 'Rework invoicing', 'description' => 'nothing here']);
        $byBody = $this->task(['title' => 'Unrelated', 'description' => 'touches the invoicing module']);
        $this->task(['title' => 'Something else', 'description' => 'no match']);
        $literal = $this->task(['title' => 'Discount 100% applied', 'description' => 'edge case']);

        $result = $this->runTool('list_tasks', ['search' => 'invoicing']);
        $this->assertEqualsCanonicalizing([$byTitle->id, $byBody->id], array_column($result['tasks'], 'id'));

        // "%" is a literal here, not a wildcard that matches everything.
        $wildcard = $this->runTool('list_tasks', ['search' => '100%']);
        $this->assertSame([$literal->id], array_column($wildcard['tasks'], 'id'));
    }

    public function test_a_deadline_question_is_answerable(): void
    {
        $soon = $this->task(['title' => 'Due soon', 'due_date' => now()->addDays(2)]);
        $this->task(['title' => 'Due later', 'due_date' => now()->addMonth()]);
        $this->task(['title' => 'No deadline']);

        $result = $this->runTool('list_tasks', ['due_before' => now()->addWeek()->toDateString()]);

        $this->assertSame([$soon->id], array_column($result['tasks'], 'id'));
    }

    public function test_a_large_workspace_returns_a_bounded_list_that_says_how_much_was_left_out(): void
    {
        Task::factory()->count(60)->create(['project_id' => $this->project->id, 'user_id' => null]);

        $result = $this->runTool('list_tasks');

        $this->assertSame(50, $result['returned']);
        $this->assertCount(50, $result['tasks']);
        $this->assertSame(60, $result['total_matching']);
        $this->assertStringContainsString('50 of 60', $result['note']);

        // And the agent can widen it deliberately.
        $wider = $this->runTool('list_tasks', ['limit' => 60]);
        $this->assertSame(60, $wider['returned']);
        $this->assertArrayNotHasKey('note', $wider);
    }

    public function test_the_limit_is_capped(): void
    {
        $this->callTool('list_tasks', ['limit' => 5000])->assertJsonPath('result.isError', true);
    }

    public function test_the_overview_summarises_the_workspace_without_listing_tasks(): void
    {
        $this->task(['status' => 'pending']);
        $this->task(['status' => 'pending']);
        $this->task(['status' => 'in_progress']);
        $this->task(['status' => 'completed', 'due_date' => now()->subWeek()]);
        $this->task(['status' => 'pending', 'due_date' => now()->subWeek()]);

        $empty = Project::factory()->create(['tenant_id' => $this->user->tenant_id, 'name' => 'Empty']);
        User::factory()->member()->create(['tenant_id' => $this->user->tenant_id]);

        $result = $this->runTool('workspace_overview');

        $this->assertSame(2, $result['members']);
        $this->assertSame(2, $result['totals']['projects']);
        $this->assertSame(5, $result['totals']['tasks']);
        $this->assertSame(1, $result['totals']['overdue']);
        $this->assertSame($this->user->id, $result['you']['id']);

        $launch = collect($result['projects'])->firstWhere('name', 'Launch');
        $this->assertSame(['pending' => 3, 'in_progress' => 1, 'completed' => 1, 'total' => 5], $launch['tasks']);
        $this->assertSame(1, $launch['overdue']);

        // Every column is reported for an empty project, not omitted.
        $blank = collect($result['projects'])->firstWhere('name', 'Empty');
        $this->assertSame(['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'total' => 0], $blank['tasks']);
        $this->assertSame($empty->id, $blank['id']);
    }

    public function test_the_overview_only_counts_the_callers_workspace(): void
    {
        Task::factory()->count(3)->create();
        Project::factory()->create(['name' => 'Someone elses']);
        $this->task();

        $result = $this->runTool('workspace_overview');

        $this->assertSame(1, $result['totals']['tasks']);
        $this->assertSame(1, $result['totals']['projects']);
        $this->assertStringNotContainsString('Someone elses', json_encode($result));
    }
}
