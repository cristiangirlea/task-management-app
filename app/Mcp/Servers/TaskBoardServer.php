<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\CreateTask;
use App\Mcp\Tools\DeleteTask;
use App\Mcp\Tools\ListMembers;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListTasks;
use App\Mcp\Tools\MoveTask;
use App\Mcp\Tools\UpdateTask;
use App\Mcp\Tools\WorkspaceOverview;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * MCP server exposing the authenticated user's workspace. It is served over
 * HTTP behind Sanctum (see routes/ai.php), so every tool runs as a specific
 * user and the tenant scope applies exactly as it does for the REST API.
 */
#[Name('Task Board')]
#[Version('1.0.0')]
#[Instructions('Manage projects and Kanban tasks in the connected user\'s workspace. '
    .'Tasks belong to projects. Each task has a status column (pending, in_progress, completed), '
    .'a position inside that column (0 = top), a priority from 1 (highest) to 5 (lowest), '
    .'an optional due date and an optional assignee. '
    .'Call workspace_overview first to see the projects and where the work sits; then list_tasks with filters '
    .'(overdue, assigned_to_me, search) to answer a specific question rather than listing everything. '
    .'list_members gives the user ids needed to assign work. '
    .'Use move_task to change a task\'s column or order and update_task for everything else. '
    .'Dates are ISO 8601; send due dates as YYYY-MM-DD.')]
class TaskBoardServer extends Server
{
    protected array $tools = [
        WorkspaceOverview::class,
        ListProjects::class,
        CreateProject::class,
        ListMembers::class,
        ListTasks::class,
        CreateTask::class,
        UpdateTask::class,
        MoveTask::class,
        DeleteTask::class,
    ];
}
