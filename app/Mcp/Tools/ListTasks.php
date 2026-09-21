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
#[Description('List tasks, optionally filtered by project and/or status column. Results are ordered as on the Kanban board (column, then position).')]
class ListTasks extends Tool
{
    public function handle(Request $request, TaskService $tasks): Response
    {
        $tenantId = $request->user()->tenant_id;

        $filters = $request->validate([
            'project_id' => ['nullable', 'integer', TaskRules::projectExists($tenantId)],
            'status' => ['nullable', Rule::in(Task::STATUSES)],
        ], TaskRules::messages());

        return Response::json([
            'tasks' => TaskResource::collection($tasks->listTasks($filters))->resolve(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Only tasks in this project (see list_projects).'),
            'status' => $schema->string()->enum(Task::STATUSES)->description('Only tasks in this column.'),
        ];
    }
}
