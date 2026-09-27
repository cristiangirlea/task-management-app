<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[IsReadOnly]
#[Description('List the projects in the workspace with their task counts. Call this first to find a project_id.')]
class ListProjects extends Tool
{
    public function handle(Request $request): Response
    {
        $projects = Project::withCount('tasks')->orderBy('name')->get();

        return Response::json([
            'projects' => ProjectResource::collection($projects)->resolve(),
        ]);
    }
}
