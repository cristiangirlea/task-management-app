<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use Illuminate\Http\Request;

class TaskController extends ApiBaseController
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function index(Request $request)
    {
        $tasks = $this->taskService->getTasksByProject($request->query('project_id'));

        return $this->respondApiSuccess(TaskResource::class, $tasks, 'Tasks retrieved successfully');
    }

    public function store(StoreTaskRequest $request)
    {
        $task = $this->taskService->createTask($request->validated());

        return $this->respondApiSuccess(TaskResource::class, $task, 'Task created successfully', 201);
    }

    public function update(UpdateTaskRequest $request, $id)
    {
        $task = $this->taskService->updateTask($id, $request->validated());

        return $this->respondApiSuccess(TaskResource::class, $task, 'Task updated successfully');
    }

    public function destroy($id)
    {
        $this->taskService->deleteTask($id);

        return $this->respondApiSuccess(null, null, 'Task deleted successfully', 204);
    }

    public function reorder(Request $request)
    {
        $this->taskService->reorderTasks($request->input('priorities'));

        return $this->respondApiSuccess(null, null, 'Tasks reordered successfully');
    }
}
