<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = Task::where('project_id', $request->query('project_id'))
            ->orderBy('priority')
            ->get();

        return response()->json($tasks);
    }

    public function store(StoreTaskRequest $request)
    {
        $maxPriority = Task::where('project_id', $request->input('project_id'))->max('priority') ?? 0;

        $task = Task::create([
            'name' => $request->input('name'),
            'project_id' => $request->input('project_id'),
            'priority' => $maxPriority + 1,
        ]);

        return response()->json($task, 201);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task->update($request->validated());

        return response()->json($task);
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully'], 204);
    }

    public function reorder(Request $request)
    {
        $priorities = $request->input('priorities');
        foreach ($priorities as $index => $taskId) {
            Task::where('id', $taskId)->update(['priority' => $index + 1]);
        }

        return response()->json(['message' => 'Tasks reordered successfully.']);
    }
}
