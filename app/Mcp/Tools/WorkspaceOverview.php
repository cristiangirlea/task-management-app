<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\Task;
use App\Services\BillingService;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('workspace_overview')]
#[IsReadOnly]
#[Description('A one-call summary of the workspace: every project with how many tasks sit in each Kanban '
    .'column and how many are overdue, plus workspace totals and the plan (free or team) with its seat '
    .'limit. Start here to get your bearings before calling list_tasks, rather than listing every task '
    .'to count them.')]
class WorkspaceOverview extends Tool
{
    public function handle(Request $request, BillingService $billing): Response
    {
        $user = $request->user();
        $tenant = $user->tenant;

        // Two grouped queries rather than one per project.
        $byStatus = Task::query()
            ->selectRaw('project_id, status, count(*) as total')
            ->groupBy('project_id', 'status')
            ->get()
            ->groupBy('project_id');

        $overdue = Task::query()
            ->overdue()
            ->selectRaw('project_id, count(*) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $projects = Project::query()->orderBy('name')->get()->map(function (Project $project) use ($byStatus, $overdue): array {
            $counts = $this->statusCounts($byStatus->get($project->id) ?? collect());

            return [
                'id' => $project->id,
                'name' => $project->name,
                'tasks' => $counts + ['total' => array_sum($counts)],
                'overdue' => (int) ($overdue[$project->id] ?? 0),
            ];
        });

        return Response::json([
            'workspace' => $tenant?->name,
            'you' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role],
            'members' => $tenant?->users()->count() ?? 0,
            'plan' => $tenant ? $billing->plan($tenant) : null,
            'seats' => [
                'used' => $tenant ? $billing->seatsUsed($tenant) : 0,
                'limit' => $tenant ? $billing->seatLimit($tenant) : 0,
            ],
            'projects' => $projects->all(),
            'totals' => [
                'projects' => $projects->count(),
                'tasks' => $projects->sum(fn (array $p) => $p['tasks']['total']),
                'overdue' => $projects->sum(fn (array $p) => $p['overdue']),
            ],
        ]);
    }

    /**
     * Zero-filled counts so every column is present, not just the used ones.
     *
     * @param  Collection<int, Task>  $rows
     * @return array<string, int>
     */
    private function statusCounts(Collection $rows): array
    {
        $counts = array_fill_keys(Task::STATUSES, 0);

        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->total;
        }

        return $counts;
    }
}
