<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\CollaboratorTimelineBuilder;
use App\Services\ProjectTimelineBuilder;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index', [
            'projects' => Project::orderBy('name')->get(['id', 'name', 'status', 'due_date']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function project(Project $project, ProjectTimelineBuilder $builder): Response
    {
        $items = Item::where('project_id', $project->id)->get();
        $itemIds = $items->pluck('id');
        $totalMinutes = (int) TimeEntry::whereIn('item_id', $itemIds)->sum('minutes');

        return Inertia::render('Admin/Reports/Project', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'due_date' => $project->due_date,
                'created_at' => $project->created_at?->toIso8601String(),
                'updated_at' => $project->updated_at?->toIso8601String(),
            ],
            'stats' => [
                'items_total' => $items->count(),
                'items_completed' => $items->whereNotNull('completed_at')->count(),
                'hours_logged' => round($totalMinutes / 60, 1),
            ],
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'events' => $builder->build($project),
        ]);
    }

    public function collaborator(User $user, CollaboratorTimelineBuilder $builder): Response
    {
        $createdTotal = Item::where('creator_id', $user->id)->count();
        $assignedActive = Item::whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->whereNull('completed_at')->count();
        $totalMinutes = (int) TimeEntry::where('user_id', $user->id)->sum('minutes');
        $projectsCount = Item::whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->orWhere('creator_id', $user->id)
            ->distinct('project_id')->count('project_id');

        return Inertia::render('Admin/Reports/Collaborator', [
            'collaborator' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'joined_at' => $user->created_at?->toIso8601String(),
            ],
            'stats' => [
                'items_created' => $createdTotal,
                'items_assigned_active' => $assignedActive,
                'hours_logged' => round($totalMinutes / 60, 1),
                'projects_count' => $projectsCount,
            ],
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'events' => $builder->build($user),
        ]);
    }
}
