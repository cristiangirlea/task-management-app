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

#[Name('create_task')]
#[Description('Create a task in a project. It is placed at the bottom of its status column (pending by default).')]
class CreateTask extends Tool
{
    public function handle(Request $request, TaskService $tasks): Response
    {
        $data = $request->validate(TaskRules::store($request->user()->tenant_id), TaskRules::messages());

        $task = $tasks->createTask($data);

        return Response::json(['task' => TaskResource::make($task)->resolve()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->max(255)->description('Short task title.')->required(),
            'project_id' => $schema->integer()->description('Project the task belongs to (see list_projects).')->required(),
            'description' => $schema->string()->max(1000)->description('Longer description.'),
            'status' => $schema->string()->enum(Task::STATUSES)->description('Kanban column, default pending.'),
            'priority' => $schema->integer()->min(1)->max(5)->description('1 = highest, 5 = lowest, default 3.'),
            'due_date' => $schema->string()->description('Due date as YYYY-MM-DD.'),
            'user_id' => $schema->integer()->description('Assignee user id (must belong to the workspace).'),
        ];
    }
}
