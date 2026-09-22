<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use App\Validation\TaskRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tasks')]
#[IsReadOnly]
#[Description('Find tasks. Combine filters to answer a question directly rather than listing everything: '
    .'overdue=true for what is late, assigned_to_me=true for the caller\'s own work, search for words in the '
    .'title or description, due_before for a deadline. Results are ordered as on the Kanban board. '
    .'The reply reports how many tasks matched in total, so a truncated list can be narrowed with more filters.')]
class ListTasks extends Tool
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    public function handle(Request $request, TaskService $tasks): Response
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $input = $request->validate([
            'project_id' => ['nullable', 'integer', TaskRules::projectExists($tenantId)],
            'status' => ['nullable', Rule::in(Task::STATUSES)],
            'assigned_to' => ['nullable', 'integer', TaskRules::userExists($tenantId)],
            'assigned_to_me' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
            'due_before' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ], TaskRules::messages());

        $limit = (int) ($input['limit'] ?? self::DEFAULT_LIMIT);
        $flag = fn (string $key) => filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL);

        $filters = [
            'project_id' => $input['project_id'] ?? null,
            'status' => $input['status'] ?? null,
            'assigned_to' => $flag('assigned_to_me') ? $user->id : ($input['assigned_to'] ?? null),
            'overdue' => $flag('overdue'),
            'due_before' => $input['due_before'] ?? null,
            'search' => $input['search'] ?? null,
        ];

        $matching = $tasks->countTasks($filters);
        $found = $tasks->listTasks($filters + ['limit' => $limit]);

        $payload = [
            'tasks' => TaskResource::collection($found)->resolve(),
            'returned' => $found->count(),
            'total_matching' => $matching,
        ];

        if ($matching > $found->count()) {
            $payload['note'] = "Showing {$found->count()} of {$matching} matching tasks. "
                .'Add filters, or raise limit (max '.self::MAX_LIMIT.'), to see more.';
        }

        return Response::json($payload);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Only tasks in this project (see list_projects).'),
            'status' => $schema->string()->enum(Task::STATUSES)->description('Only tasks in this Kanban column.'),
            'assigned_to_me' => $schema->boolean()->description('Only tasks assigned to the connected user.'),
            'assigned_to' => $schema->integer()->description('Only tasks assigned to this user id (see list_members).'),
            'overdue' => $schema->boolean()->description('Only tasks past their due date and not completed.'),
            'due_before' => $schema->string()->description('Only tasks due before this date (YYYY-MM-DD).'),
            'search' => $schema->string()->max(255)->description('Words to look for in the title or description.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->description('How many to return, default '.self::DEFAULT_LIMIT.'.'),
        ];
    }
}
