<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_project')]
#[Description('Create a new project in the workspace.')]
class CreateProject extends Tool
{
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $project = Project::create($data);
        $project->loadCount('tasks');

        return Response::json(['project' => ProjectResource::make($project)->resolve()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(255)->description('Project name.')->required(),
            'description' => $schema->string()->max(1000)->description('Optional description.'),
        ];
    }
}
