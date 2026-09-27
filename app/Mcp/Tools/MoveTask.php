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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('move_task')]
#[IsIdempotent]
#[Description('Move a task to a Kanban column and position, like dragging a card on the board. Omit position to drop it at the bottom.')]
class MoveTask extends Tool
{
    public function handle(Request $request, TaskService $tasks): Response
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'task_id' => ['required', 'integer', TaskRules::taskExists($tenantId)],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'position' => ['nullable', 'integer', 'min:0'],
        ], TaskRules::messages());

        $task = $tasks->moveTask(Task::findOrFail($data['task_id']), $data['status'], $data['position'] ?? null);

        return Response::json(['task' => TaskResource::make($task)->resolve()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('Task to move (see list_tasks).')->required(),
            'status' => $schema->string()->enum(Task::STATUSES)->description('Destination column.')->required(),
            'position' => $schema->integer()->min(0)->description('Index inside the column, 0 = top. Omit for bottom.'),
        ];
    }
}
