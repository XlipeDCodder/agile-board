<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Comment;
use App\Models\Item;
use App\Models\ItemStatusHistory;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;

class CollaboratorContextBuilder
{
    /**
     * Monta um JSON-ready array com a "ficha" do colaborador para o LLM.
     *
     * @return array<string,mixed>
     */
    public function build(User $user): array
    {
        $now = Carbon::now();
        $cutoff = $now->copy()->subDays(90);
        $columns = Column::pluck('name', 'id')->all();
        $doneColumnId = Column::where('name', 'Feito')->value('id');

        $itemsRecent = Item::with(['project:id,name', 'column:id,name'])
            ->whereNull('parent_id')
            ->where(function ($q) use ($user) {
                $q->where('creator_id', $user->id)
                    ->orWhereHas('assignees', fn ($a) => $a->where('users.id', $user->id));
            })
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $itemIdsRecent = $itemsRecent->pluck('id')->all();
        $historiesByItem = ItemStatusHistory::whereIn('item_id', $itemIdsRecent)
            ->orderBy('created_at')
            ->get()
            ->groupBy('item_id');

        $itemsPayload = $itemsRecent->map(function (Item $item) use ($historiesByItem, $columns, $user, $doneColumnId) {
            $itemHistory = $historiesByItem[$item->id] ?? collect();
            $transitions = $itemHistory->map(fn ($h) => [
                'column' => $columns[$h->column_id] ?? 'desconhecida',
                'at' => $h->created_at?->toIso8601String() ?? $item->updated_at?->toIso8601String(),
            ])->all();

            // Inferir conclusão: no sistema, um card é "concluído" quando está
            // atualmente na coluna "Feito". A data de conclusão é o timestamp
            // da entrada do histórico em que ele entrou nessa coluna pela última vez.
            // Fallback: se o histórico não tem timestamp (registros antigos com
            // created_at NULL), usa o updated_at do item como aproximação.
            $isCompleted = $doneColumnId && $item->column_id == $doneColumnId;
            $completedAtInferred = null;
            if ($isCompleted) {
                $lastIntoDone = $itemHistory->where('column_id', $doneColumnId)->last();
                $completedAtInferred = $lastIntoDone?->created_at?->toIso8601String()
                    ?? $item->updated_at?->toIso8601String();
            }

            return [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->type,
                'priority' => $item->priority,
                'estimation' => $item->estimation,
                'current_column' => $item->column?->name,
                'project' => $item->project?->name,
                'created_at' => $item->created_at?->toIso8601String(),
                'is_completed' => $isCompleted,
                'completed_at_inferred' => $completedAtInferred,
                'is_creator' => $item->creator_id === $user->id,
                'transitions' => $transitions,
            ];
        })->all();

        $commentsRecent = Comment::with('item:id,title')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'item_id' => $c->item_id,
                'item_title' => $c->item?->title,
                'at' => $c->created_at?->toIso8601String(),
                'snippet' => mb_strlen($c->body) > 200 ? mb_substr($c->body, 0, 200).'…' : $c->body,
            ])->all();

        // Todas as contagens abaixo ignoram subtarefas (parent_id NOT NULL) —
        // a unidade de análise do gestor é o card, não a subtarefa.
        $totalCreated = Item::whereNull('parent_id')->where('creator_id', $user->id)->count();
        $totalAssigned = Item::whereNull('parent_id')
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))->count();
        $totalCompletedAsCreator = $doneColumnId
            ? Item::whereNull('parent_id')->where('creator_id', $user->id)->where('column_id', $doneColumnId)->count()
            : 0;
        $totalCompletedAsAssignee = $doneColumnId
            ? Item::whereNull('parent_id')
                ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                ->where('column_id', $doneColumnId)->count()
            : 0;
        $totalMinutes = (int) TimeEntry::where('user_id', $user->id)->sum('minutes');

        $minutesByProject = TimeEntry::where('time_entries.user_id', $user->id)
            ->join('items', 'time_entries.item_id', '=', 'items.id')
            ->leftJoin('projects', 'items.project_id', '=', 'projects.id')
            ->selectRaw('projects.id as project_id, projects.name as project_name, SUM(time_entries.minutes) as total_minutes')
            ->groupBy('projects.id', 'projects.name')
            ->get()
            ->map(fn ($r) => [
                'project_id' => $r->project_id,
                'project_name' => $r->project_name ?? '(sem projeto)',
                'hours' => round(((int) $r->total_minutes) / 60, 1),
            ])->all();

        $avgTimeInColumn = $this->averageTimeInColumns($user, $columns);

        $priorityDistribution = Item::whereNull('parent_id')
            ->where(function ($q) use ($user) {
                $q->where('creator_id', $user->id)
                    ->orWhereHas('assignees', fn ($a) => $a->where('users.id', $user->id));
            })
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->all();

        // Subtarefas têm contagem separada — não são "cards".
        $subtasksCreated = Item::whereNotNull('parent_id')
            ->where('creator_id', $user->id)
            ->count();
        $subtasksCompleted = Item::whereNotNull('parent_id')
            ->where('creator_id', $user->id)
            ->whereNotNull('completed_at')
            ->count();

        return [
            'collaborator' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'joined_at' => $user->created_at?->toIso8601String(),
                'is_admin' => (bool) $user->is_admin,
            ],
            'window' => [
                'detailed_days' => 90,
                'cutoff' => $cutoff->toIso8601String(),
                'now' => $now->toIso8601String(),
            ],
            'items_recent_90d' => $itemsPayload,
            'comments_recent_90d' => $commentsRecent,
            'completion_semantics' => 'Um card é considerado concluído quando sua coluna atual é "Feito". A data de conclusão é o timestamp da última entrada do histórico em que ele entrou na coluna "Feito" (campo completed_at_inferred em cada item).',
            'aggregates_all_time' => [
                'items_created_total' => $totalCreated,
                'items_assigned_total' => $totalAssigned,
                'items_completed_as_creator' => $totalCompletedAsCreator,
                'items_completed_as_assignee' => $totalCompletedAsAssignee,
                'subtasks_created_total' => $subtasksCreated,
                'subtasks_completed_total' => $subtasksCompleted,
                'total_minutes_logged' => $totalMinutes,
                'total_hours_logged' => round($totalMinutes / 60, 1),
                'minutes_by_project' => $minutesByProject,
                'priority_distribution' => $priorityDistribution,
                'avg_hours_in_column' => $avgTimeInColumn,
            ],
        ];
    }

    /**
     * Calcula o tempo médio (em horas) que os itens do usuário ficam em cada coluna,
     * usando as transições registradas em ItemStatusHistory.
     */
    private function averageTimeInColumns(User $user, array $columnsById): array
    {
        $itemIds = Item::whereNull('parent_id')
            ->where(function ($q) use ($user) {
                $q->where('creator_id', $user->id)
                    ->orWhereHas('assignees', fn ($a) => $a->where('users.id', $user->id));
            })->pluck('id');

        if ($itemIds->isEmpty()) {
            return [];
        }

        $histories = ItemStatusHistory::whereIn('item_id', $itemIds)
            ->whereNotNull('created_at')
            ->orderBy('item_id')
            ->orderBy('created_at')
            ->get(['item_id', 'column_id', 'created_at']);
        // Nota: aqui mantemos whereNotNull porque o cálculo de "tempo médio
        // em coluna" depende de dois timestamps consecutivos válidos — sem
        // dados confiáveis, o resultado seria enganoso.

        $sumByColumn = [];
        $countByColumn = [];

        $grouped = $histories->groupBy('item_id');
        foreach ($grouped as $itemHistory) {
            $rows = $itemHistory->values();
            for ($i = 0; $i < $rows->count(); $i++) {
                $cur = $rows[$i];
                $next = $rows[$i + 1] ?? null;
                if (! $next || ! $cur->created_at || ! $next->created_at) {
                    continue;
                }
                $hours = $cur->created_at->diffInMinutes($next->created_at) / 60;
                $colId = $cur->column_id;
                $sumByColumn[$colId] = ($sumByColumn[$colId] ?? 0) + $hours;
                $countByColumn[$colId] = ($countByColumn[$colId] ?? 0) + 1;
            }
        }

        $result = [];
        foreach ($sumByColumn as $colId => $sum) {
            $result[] = [
                'column' => $columnsById[$colId] ?? 'desconhecida',
                'avg_hours' => round($sum / $countByColumn[$colId], 2),
                'transitions_counted' => $countByColumn[$colId],
            ];
        }
        return $result;
    }
}
