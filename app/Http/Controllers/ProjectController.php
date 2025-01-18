<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;

class ProjectController extends ApiBaseController
{
    /**
     * Display a listing of projects.
     */
    public function index()
    {
        return $this->respondApiSuccess(ProjectResource::class, Project::all(), 'Projects retrieved successfully');
    }

    /**
     * Store a new project.
     */
    public function store(StoreProjectRequest $request)
    {
        $project = Project::create($request->validated());

        return $this->respondApiSuccess(ProjectResource::class, $project, 'Project created successfully', 201);
    }

    /**
     * Update a project.
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());

        return $this->respondApiSuccess(ProjectResource::class, $project, 'Project updated successfully');
    }

    /**
     * Delete a project.
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return $this->respondApiSuccess(null, null, 'Project deleted successfully', 204);
    }
}
