<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends ApiBaseController
{
    public function __construct(protected TaskService $taskService) {}

    public function index(Request $request): JsonResponse
    {
        $tasks = $this->taskService->getTasksByProject((int) $request->query('project_id'));

        return $this->respondApiSuccess(TaskResource::class, $tasks, 'Tasks retrieved successfully');
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->createTask($request->validated());

        return $this->respondApiSuccess(TaskResource::class, $task, 'Task created successfully', 201);
    }

    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $task = $this->taskService->updateTask($id, $request->validated());

        return $this->respondApiSuccess(TaskResource::class, $task, 'Task updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->taskService->deleteTask($id);

        return $this->respondApiSuccess(null, null, 'Task deleted successfully', 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->taskService->reorderTasks($request->input('priorities'));

        return $this->respondApiSuccess(null, null, 'Tasks reordered successfully');
    }
}
