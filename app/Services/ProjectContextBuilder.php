<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Comment;
use App\Models\Item;
use App\Models\ItemBlockEvent;
use App\Models\ItemStatusHistory;
use App\Models\Project;
use App\Models\TimeEntry;
use Carbon\Carbon;

class ProjectContextBuilder
{
    public function __construct(private MarkdownStripper $stripper) {}

    /**
     * Monta um JSON-ready array com a "ficha" do projeto para o LLM e para
     * geração de relatórios. Espelha o CollaboratorContextBuilder mas com o
     * recorte sendo um projeto específico.
     *
     * @return array<string,mixed>
     */
    public function build(Project $project): array
    {
        $now = Carbon::now();
        $columns = Column::pluck('name', 'id')->all();
        $doneColumnId = Column::where('name', 'Feito')->value('id');

        $items = Item::with([
                'column:id,name',
                'creator:id,name',
                'assignees:id,name',
                'comments' => fn ($q) => $q->latest()->limit(5)->with('user:id,name'),
            ])
            ->whereNull('parent_id')
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->get();

        $itemIds = $items->pluck('id')->all();

        $historiesByItem = ItemStatusHistory::whereIn('item_id', $itemIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy('item_id');

        $itemsPayload = $items->map(function (Item $item) use ($historiesByItem, $columns, $doneColumnId) {
            $itemHistory = $historiesByItem[$item->id] ?? collect();
            $transitions = $itemHistory->map(fn ($h) => [
                'column' => $columns[$h->column_id] ?? 'desconhecida',
                'at' => $h->created_at?->toIso8601String() ?? $item->updated_at?->toIso8601String(),
            ])->all();

            $isCompleted = $doneColumnId && $item->column_id == $doneColumnId;
            $completedAtInferred = null;
            if ($isCompleted) {
                $lastIntoDone = $itemHistory->where('column_id', $doneColumnId)->last();
                $completedAtInferred = $lastIntoDone?->created_at?->toIso8601String()
                    ?? $item->updated_at?->toIso8601String();
            }

            $cleanDescription = $this->stripper->stripForLlm($item->description);
            $description = $cleanDescription
                ? (mb_strlen($cleanDescription) > 800
                    ? mb_substr($cleanDescription, 0, 800).'…'
                    : $cleanDescription)
                : null;

            $commentsForItem = ($item->relationLoaded('comments') ? $item->comments : collect())
                ->take(5)
                ->map(function ($c) {
                    $body = $this->stripper->stripForLlm($c->body) ?? '';
                    return [
                        'author' => $c->user?->name ?? '(desconhecido)',
                        'at' => $c->created_at?->toIso8601String(),
                        'snippet' => mb_strlen($body) > 300 ? mb_substr($body, 0, 300).'…' : $body,
                    ];
                })->values()->all();

            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $description,
                'type' => $item->type,
                'priority' => $item->priority,
                'estimation' => $item->estimation,
                'predicted' => $this->predictedSummary($item),
                'current_column' => $item->column?->name,
                'creator' => $item->creator?->name,
                'assignees' => $item->relationLoaded('assignees')
                    ? $item->assignees->pluck('name')->all()
                    : [],
                'created_at' => $item->created_at?->toIso8601String(),
                'is_completed' => $isCompleted,
                'completed_at_inferred' => $completedAtInferred,
                'is_reopen' => $item->type === 'reabertura',
                'reopened_from_id' => $item->reopened_from_id,
                'justification' => $item->justification,
                'is_blocked' => (bool) $item->is_blocked,
                'blocked_reason' => $item->blocked_reason,
                'blocked_by_item_id' => $item->blocked_by_item_id,
                'blocked_at' => $item->blocked_at?->toIso8601String(),
                'transitions' => $transitions,
                'recent_comments' => $commentsForItem,
            ];
        })->all();

        // Distribuição por coluna atual.
        $byColumn = [];
        foreach ($items as $item) {
            $col = $item->column?->name ?? 'sem coluna';
            $byColumn[$col] = ($byColumn[$col] ?? 0) + 1;
        }

        $byPriority = $items->groupBy('priority')->map->count()->all();

        // Contributors únicos: creators + assignees, com contagens.
        $contributors = [];
        foreach ($items as $item) {
            if ($item->creator) {
                $name = $item->creator->name;
                $contributors[$name] = $contributors[$name] ?? ['name' => $name, 'cards_created' => 0, 'cards_assigned' => 0];
                $contributors[$name]['cards_created']++;
            }
            if ($item->relationLoaded('assignees')) {
                foreach ($item->assignees as $assignee) {
                    $name = $assignee->name;
                    $contributors[$name] = $contributors[$name] ?? ['name' => $name, 'cards_created' => 0, 'cards_assigned' => 0];
                    $contributors[$name]['cards_assigned']++;
                }
            }
        }

        $totalMinutes = (int) TimeEntry::whereHas('item', fn ($q) => $q->where('project_id', $project->id))
            ->sum('minutes');

        $cardsReopened = $items->where('type', 'reabertura')->count();
        $cardsCurrentlyBlocked = $items->where('is_blocked', true)->count();

        $blockEvents = ItemBlockEvent::whereIn('item_id', $itemIds)
            ->orderBy('item_id')
            ->orderBy('created_at')
            ->get(['item_id', 'event', 'created_at']);
        $avgHoursBlocked = $this->averageHoursBlocked($blockEvents);

        $commentsRecent = Comment::with('item:id,title', 'user:id,name')
            ->whereIn('item_id', $itemIds)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($c) {
                $body = $this->stripper->stripForLlm($c->body) ?? '';
                return [
                    'item_id' => $c->item_id,
                    'item_title' => $c->item?->title,
                    'author' => $c->user?->name,
                    'at' => $c->created_at?->toIso8601String(),
                    'snippet' => mb_strlen($body) > 200 ? mb_substr($body, 0, 200).'…' : $body,
                ];
            })->all();

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'due_date' => $project->due_date, // string YYYY-MM-DD direto do DB (sem cast)
                'created_at' => $project->created_at?->toIso8601String(),
            ],
            'window' => [
                'now' => $now->toIso8601String(),
            ],
            'items' => $itemsPayload,
            'comments_recent' => $commentsRecent,
            'completion_semantics' => 'Um card é considerado concluído quando sua coluna atual é "Feito". A data de conclusão é o timestamp da última entrada do histórico em que ele entrou nessa coluna.',
            'aggregates' => [
                'cards_total' => $items->count(),
                'cards_by_column' => $byColumn,
                'cards_by_priority' => $byPriority,
                'cards_reopened' => $cardsReopened,
                'cards_currently_blocked' => $cardsCurrentlyBlocked,
                'avg_hours_blocked' => $avgHoursBlocked,
                'total_minutes_logged' => $totalMinutes,
                'total_hours_logged' => round($totalMinutes / 60, 1),
                'contributors' => array_values($contributors),
            ],
        ];
    }

    private function predictedSummary(Item $item): ?array
    {
        if (! $item->predicted_value || ! $item->predicted_unit) {
            return null;
        }
        $minutes = match ($item->predicted_unit) {
            'minutes' => $item->predicted_value,
            'hours' => $item->predicted_value * 60,
            'days' => $item->predicted_value * 60 * 24,
            default => null,
        };
        return [
            'value' => (int) $item->predicted_value,
            'unit' => $item->predicted_unit,
            'minutes_normalized' => $minutes,
        ];
    }

    private function averageHoursBlocked($events): ?float
    {
        if ($events->isEmpty()) {
            return null;
        }

        $totalHours = 0.0;
        $pairs = 0;
        foreach ($events->groupBy('item_id') as $eventsForItem) {
            $currentBlockedAt = null;
            foreach ($eventsForItem as $e) {
                if ($e->event === 'blocked') {
                    $currentBlockedAt = $e->created_at;
                } elseif ($e->event === 'unblocked' && $currentBlockedAt) {
                    $totalHours += $currentBlockedAt->diffInMinutes($e->created_at) / 60;
                    $pairs++;
                    $currentBlockedAt = null;
                }
            }
        }

        return $pairs > 0 ? round($totalHours / $pairs, 2) : null;
    }
}
