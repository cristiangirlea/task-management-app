<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use App\Validation\TaskRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_task')]
#[IsIdempotent]
#[Description('Update a task\'s fields. Only the fields you pass change. Changing status moves the task to the bottom of the new column; use move_task to control the position.')]
class UpdateTask extends Tool
{
    public function handle(Request $request, TaskService $tasks): Response
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate(
            ['task_id' => ['required', 'integer', TaskRules::taskExists($tenantId)]] + TaskRules::update($tenantId),
            TaskRules::messages(),
        );

        $task = Task::findOrFail($data['task_id']);
        unset($data['task_id']);

        $task = $tasks->updateTask($task, $data);

        return Response::json(['task' => TaskResource::make($task)->resolve()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('Task to update (see list_tasks).')->required(),
            'title' => $schema->string()->max(255),
            'description' => $schema->string()->max(1000)->nullable(),
            'status' => $schema->string()->enum(Task::STATUSES),
            'priority' => $schema->integer()->min(1)->max(5)->description('1 = highest, 5 = lowest.'),
            'due_date' => $schema->string()->nullable()->description('YYYY-MM-DD, or null to clear.'),
            'user_id' => $schema->integer()->nullable()->description('Assignee user id, or null to unassign.'),
            'project_id' => $schema->integer()->description('Move the task to another project.'),
        ];
    }
}
