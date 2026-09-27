<?php

namespace Tests\Feature\Mcp;

use App\Models\Project;
use App\Models\User;
use Tests\TestCase;

/**
 * The MCP server over its HTTP transport, authenticated with Sanctum tokens.
 */
class McpHttpTest extends TestCase
{
    private const ACCEPT = ['Accept' => 'application/json, text/event-stream'];

    private function rpc(string $method, array $params = [], int $id = 1): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params];
    }

    public function test_requires_a_bearer_token(): void
    {
        $this->withHeaders(self::ACCEPT)
            ->postJson('/mcp', $this->rpc('tools/list'))
            ->assertUnauthorized();
    }

    public function test_get_is_not_allowed_on_the_streamable_http_endpoint(): void
    {
        $this->get('/mcp')->assertStatus(405);
    }

    public function test_lists_tools_for_a_token_holder(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('claude')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(self::ACCEPT)
            ->postJson('/mcp', $this->rpc('tools/list'))
            ->assertOk()
            ->assertJsonPath('id', 1);

        $names = collect($response->json('result.tools'))->pluck('name')->all();

        $this->assertEqualsCanonicalizing(
            ['workspace_overview', 'list_projects', 'create_project', 'list_members', 'list_tasks', 'create_task', 'update_task', 'move_task', 'delete_task'],
            $names
        );
    }

    public function test_calls_a_tool_as_the_token_owner(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $user->tenant_id]);
        Project::factory()->create(['name' => 'Not yours']);
        $token = $user->createToken('claude')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(self::ACCEPT)
            ->postJson('/mcp', $this->rpc('tools/call', [
                'name' => 'create_task',
                'arguments' => ['title' => 'From an agent', 'project_id' => $project->id],
            ]))
            ->assertOk()
            ->assertJsonPath('result.isError', false);

        $this->assertStringContainsString('From an agent', $response->json('result.content.0.text'));
        $this->assertDatabaseHas('tasks', ['title' => 'From an agent', 'tenant_id' => $user->tenant_id, 'project_id' => $project->id]);

        $list = $this->withToken($token)
            ->withHeaders(self::ACCEPT)
            ->postJson('/mcp', $this->rpc('tools/call', ['name' => 'list_projects', 'arguments' => []], 2))
            ->assertOk();

        $this->assertStringNotContainsString('Not yours', $list->json('result.content.0.text'));
    }

    public function test_validation_failures_are_reported_as_tool_errors(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('claude')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(self::ACCEPT)
            ->postJson('/mcp', $this->rpc('tools/call', ['name' => 'create_task', 'arguments' => ['title' => 'No project']]))
            ->assertOk()
            ->assertJsonPath('result.isError', true);
    }
}
