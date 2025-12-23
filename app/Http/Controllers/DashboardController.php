<?php

namespace App\Http\Controllers;

use App\Models\Column;
use Inertia\Inertia;
use Inertia\Response;

use App\Models\User;

use App\Models\Item;
use App\Models\Project;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Obtém todas as colunas com a contagem de itens em cada uma, excluindo subtarefas
        $columns = Column::orderBy('order')
            ->withCount(['items' => function ($query) {
                $query->whereNull('parent_id');
            }])
            ->get();

        // Obtém usuários que possuem itens alocados em colunas diferentes de "Feito"
        $activeUsers = User::whereHas('assignedItems', function ($query) {
            $query->whereHas('column', function ($q) {
                $q->where('name', '!=', 'Feito');
            });
        })
        ->with(['assignedItems' => function ($query) {
            $query->whereHas('column', function ($q) {
                $q->where('name', '!=', 'Feito');
            })
            ->with(['column', 'subtasks']); // Carrega a coluna e subtarefas
        }])
        ->get();

        // Obtém usuários que NÃO possuem itens alocados em colunas diferentes de "Feito" (usuários ociosos)
        $idleUsers = User::whereDoesntHave('assignedItems', function ($query) {
            $query->whereHas('column', function ($q) {
                $q->where('name', '!=', 'Feito');
            });
        })->get();

        // Obtém cards que não têm nenhum responsável (unassigned) e não estão na coluna "Feito"
        $unassignedItems = Item::doesntHave('assignees')
            ->whereNull('parent_id') // Exclui subtarefas
            ->whereHas('column', function ($q) {
                $q->where('name', '!=', 'Feito');
            })
            ->with(['column'])
            ->get();

        // Obtém todos os usuários para o dropdown de atribuição
        $allUsers = User::orderBy('name')->get();

        // Obtém estatísticas de projetos
        $totalProjects = Project::count();
        $overdueProjects = Project::where('due_date', '<', now()->startOfDay())->count();

        // Ranking de Entregas (Gamification)
        $leaderboard = User::withCount(['assignedItems' => function ($query) {
            $query->whereHas('column', function ($q) {
                $q->where('name', 'Feito');
            });
        }])
        ->withSum(['assignedItems' => function ($query) {
            $query->whereHas('column', function ($q) {
                $q->where('name', 'Feito');
            });
        }], 'estimation')
        ->orderByDesc('assigned_items_count')
        ->orderByDesc('assigned_items_sum_estimation') // Desempate por pontos
        ->take(5) // Top 5
        ->orderByDesc('assigned_items_sum_estimation') // Desempate por pontos
        ->take(5) // Top 5
        ->get();

        // Project Time Metrics
        $projectTimeGlobal = Project::withSum('timeEntries', 'minutes')->get();
        
        $projectTimeUser = Project::withSum(['timeEntries' => function($query) {
            $query->where('user_id', \Illuminate\Support\Facades\Auth::id());
        }], 'minutes')->get();


        return Inertia::render('Dashboard', [
            'columns' => $columns,
            'users' => $activeUsers,
            'idleUsers' => $idleUsers,
            'unassignedItems' => $unassignedItems,
            'allUsers' => $allUsers,
            'totalProjects' => $totalProjects,
            'overdueProjects' => $overdueProjects,
            'overdueProjects' => $overdueProjects,
            'leaderboard' => $leaderboard,
            'projectTimeGlobal' => $projectTimeGlobal,
            'projectTimeUser' => $projectTimeUser,
        ]);
    }
}
