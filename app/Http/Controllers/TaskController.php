<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\ListTasksRequest;
use App\Http\Requests\Task\ReorderTasksRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;

class TaskController extends ApiBaseController
{
    public function __construct(protected TaskService $taskService) {}

    public function index(ListTasksRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $tasks = $this->taskService->listTasks($request->filters());

        return $this->respondApiSuccess(TaskResource::class, $tasks, 'Tasks retrieved successfully');
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $task = $this->taskService->createTask($request->validated());

        return $this->respondApiSuccess(TaskResource::class, $task, 'Task created successfully', 201);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return $this->respondApiSuccess(TaskResource::class, $task, 'Task retrieved successfully');
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = $this->taskService->updateTask($task, $request->validated());

        return $this->respondApiSuccess(TaskResource::class, $task, 'Task updated successfully');
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $this->taskService->deleteTask($task);

        return $this->respondApiSuccess(null, null, 'Task deleted successfully', 204);
    }

    /**
     * Move tasks into a Kanban column in the given order.
     */
    public function reorder(ReorderTasksRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $tasks = $this->taskService->reorderTasks($request->input('status'), $request->input('task_ids', []));

        return $this->respondApiSuccess(TaskResource::class, $tasks, 'Tasks reordered successfully');
    }
}
