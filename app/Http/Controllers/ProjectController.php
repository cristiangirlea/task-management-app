<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * Projects are tenant-scoped by the model's global scope; the policy is a
 * second line of defence.
 */
class ProjectController extends ApiBaseController
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::withCount('tasks')->orderBy('name')->get();

        return $this->respondApiSuccess(ProjectResource::class, $projects, 'Projects retrieved successfully');
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = Project::create($request->validated());
        $project->loadCount('tasks');

        return $this->respondApiSuccess(ProjectResource::class, $project, 'Project created successfully', 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->loadCount('tasks');

        return $this->respondApiSuccess(ProjectResource::class, $project, 'Project retrieved successfully');
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());
        $project->loadCount('tasks');

        return $this->respondApiSuccess(ProjectResource::class, $project, 'Project updated successfully');
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return $this->respondApiSuccess(null, null, 'Project deleted successfully', 204);
    }
}
