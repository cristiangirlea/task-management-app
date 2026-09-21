<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Services\TaskService;
use App\Validation\TaskRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_task')]
#[IsDestructive]
#[Description('Delete a task. Ask the user for confirmation before calling this.')]
class DeleteTask extends Tool
{
    public function handle(Request $request, TaskService $tasks): Response
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer', TaskRules::taskExists($request->user()->tenant_id)],
        ], TaskRules::messages());

        $task = Task::findOrFail($data['task_id']);
        $tasks->deleteTask($task);

        return Response::json(['deleted' => true, 'task_id' => $task->id, 'title' => $task->title]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('Task to delete (see list_tasks).')->required(),
        ];
    }
}
