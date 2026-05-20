<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Item;
use App\Models\ItemBlockEvent;
use App\Models\ItemStatusHistory;
use App\Models\Project;

class ProjectTimelineBuilder
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function build(Project $project): array
    {
        $events = [];

        $events[] = [
            'date' => $project->created_at?->toIso8601String(),
            'type' => 'project_created',
            'icon' => '🚀',
            'title' => "Projeto \"{$project->name}\" criado",
            'description' => $project->description ?: null,
            'actor' => null,
        ];

        $items = Item::with(['creator:id,name', 'assignees:id,name', 'column:id,name'])
            ->whereNull('parent_id')
            ->where('project_id', $project->id)
            ->get();

        $itemIds = $items->pluck('id');

        $columns = \App\Models\Column::pluck('name', 'id');
        $doneColumnId = \App\Models\Column::where('name', 'Feito')->value('id');

        $histories = ItemStatusHistory::whereIn('item_id', $itemIds)
            ->orderBy('item_id')
            ->orderBy('created_at')
            ->get();
        $historyByItem = $histories->groupBy('item_id');
        $itemsById = $items->keyBy('id');

        $comments = Comment::with('user:id,name')
            ->whereIn('item_id', $itemIds)
            ->get();

        foreach ($items as $item) {
            $events[] = [
                'date' => $item->created_at?->toIso8601String(),
                'type' => 'item_created',
                'icon' => $item->type === 'bug' ? '🐛' : '📝',
                'title' => "Card #{$item->id} \"{$item->title}\" criado",
                'description' => 'Prioridade: '.$item->priority.($item->estimation ? " · {$item->estimation} pts" : ''),
                'actor' => $item->creator?->name,
                'item_id' => $item->id,
            ];

        }

        foreach ($histories as $history) {
            $item = $itemsById[$history->item_id] ?? null;
            $columnName = $columns[$history->column_id] ?? 'desconhecida';
            // Fallback de timestamp para registros antigos com created_at NULL.
            $when = $history->created_at?->toIso8601String() ?? $item?->updated_at?->toIso8601String();
            if (! $when) {
                continue;
            }
            if ($doneColumnId && $history->column_id == $doneColumnId) {
                // Só emite "concluído" para a ÚLTIMA entrada em Feito.
                $lastDone = ($historyByItem[$history->item_id] ?? collect())
                    ->where('column_id', $doneColumnId)
                    ->last();
                if ($lastDone && $lastDone->id !== $history->id) {
                    continue;
                }
                $events[] = [
                    'date' => $when,
                    'type' => 'item_completed',
                    'icon' => '✅',
                    'title' => "Card #{$history->item_id} concluído",
                    'description' => null,
                    'actor' => null,
                    'item_id' => $history->item_id,
                ];
            } else {
                $events[] = [
                    'date' => $when,
                    'type' => 'item_moved',
                    'icon' => '➡️',
                    'title' => "Card #{$history->item_id} movido para \"{$columnName}\"",
                    'description' => null,
                    'actor' => null,
                    'item_id' => $history->item_id,
                ];
            }
        }

        // Cobertura para cards atualmente em "Feito" sem histórico válido.
        if ($doneColumnId) {
            foreach ($items->where('column_id', $doneColumnId) as $item) {
                $hasDoneHistory = ($historyByItem[$item->id] ?? collect())
                    ->where('column_id', $doneColumnId)
                    ->isNotEmpty();
                if ($hasDoneHistory) {
                    continue;
                }
                $events[] = [
                    'date' => $item->updated_at?->toIso8601String(),
                    'type' => 'item_completed',
                    'icon' => '✅',
                    'title' => "Card #{$item->id} concluído \"{$item->title}\"",
                    'description' => '(timestamp aproximado — histórico não registrado)',
                    'actor' => null,
                    'item_id' => $item->id,
                ];
            }
        }

        // Subtarefas dos cards do projeto: criação (sempre) e conclusão
        // (quando completed_at for preenchido pelo toggle do checkbox).
        $subtasks = Item::whereNotNull('parent_id')
            ->whereIn('parent_id', $itemIds)
            ->with('creator:id,name', 'parent:id,title')
            ->get();

        foreach ($subtasks as $subtask) {
            $parentRef = $subtask->parent
                ? "card #{$subtask->parent->id} \"{$subtask->parent->title}\""
                : 'card pai desconhecido';
            $events[] = [
                'date' => $subtask->created_at?->toIso8601String(),
                'type' => 'subtask_created',
                'icon' => '➕',
                'title' => "Subtarefa \"{$subtask->title}\" adicionada ao {$parentRef}",
                'description' => null,
                'actor' => $subtask->creator?->name,
                'item_id' => $subtask->id,
                'parent_id' => $subtask->parent_id,
            ];

            if ($subtask->completed_at) {
                $completedAt = $subtask->completed_at instanceof \Carbon\Carbon
                    ? $subtask->completed_at->toIso8601String()
                    : (string) $subtask->completed_at;
                $events[] = [
                    'date' => $completedAt,
                    'type' => 'subtask_completed',
                    'icon' => '✔️',
                    'title' => "Subtarefa \"{$subtask->title}\" do {$parentRef} concluída",
                    'description' => null,
                    'actor' => null,
                    'item_id' => $subtask->id,
                    'parent_id' => $subtask->parent_id,
                ];
            }
        }

        foreach ($comments as $comment) {
            $body = mb_strlen($comment->body) > 200
                ? mb_substr($comment->body, 0, 200).'…'
                : $comment->body;

            $events[] = [
                'date' => $comment->created_at?->toIso8601String(),
                'type' => 'comment',
                'icon' => '💬',
                'title' => "Comentário no card #{$comment->item_id}",
                'description' => $body,
                'actor' => $comment->user?->name,
                'item_id' => $comment->item_id,
            ];
        }

        // Reaberturas dentro do projeto (cards de tipo 'reabertura' cujo
        // reopened_from_id aponta para um dos cards desse projeto).
        $reopens = Item::whereNull('parent_id')
            ->where('type', 'reabertura')
            ->where('project_id', $project->id)
            ->whereNotNull('reopened_from_id')
            ->with(['creator:id,name', 'reopenedFrom:id,title'])
            ->get();

        foreach ($reopens as $reopen) {
            $origin = $reopen->reopenedFrom
                ? "card #{$reopen->reopenedFrom->id} \"{$reopen->reopenedFrom->title}\""
                : 'card desconhecido';
            $justification = $reopen->justification ?: '(sem justificativa registrada)';
            $events[] = [
                'date' => $reopen->created_at?->toIso8601String(),
                'type' => 'card_reopened',
                'icon' => '🔄',
                'title' => "{$origin} reaberto como card #{$reopen->id} \"{$reopen->title}\"",
                'description' => "Motivo: {$justification}",
                'actor' => $reopen->creator?->name,
                'item_id' => $reopen->id,
                'parent_id' => $reopen->reopened_from_id,
            ];
        }

        // Eventos de bloqueio/desbloqueio de cards do projeto.
        $blockEvents = ItemBlockEvent::whereIn('item_id', $itemIds)
            ->with(['user:id,name', 'item:id,title', 'blockedByItem:id,title'])
            ->orderBy('created_at')
            ->get();

        foreach ($blockEvents as $be) {
            $cardLabel = $be->item ? "card #{$be->item_id} \"{$be->item->title}\"" : "card #{$be->item_id}";
            if ($be->event === 'blocked') {
                $blockerLabel = $be->blockedByItem
                    ? " (bloqueado pelo card #{$be->blocked_by_item_id} \"{$be->blockedByItem->title}\")"
                    : '';
                $events[] = [
                    'date' => $be->created_at?->toIso8601String(),
                    'type' => 'card_blocked',
                    'icon' => '🚫',
                    'title' => "{$cardLabel} marcado como impedido{$blockerLabel}",
                    'description' => $be->reason ? "Motivo: {$be->reason}" : null,
                    'actor' => $be->user?->name,
                    'item_id' => $be->item_id,
                ];
            } else {
                $events[] = [
                    'date' => $be->created_at?->toIso8601String(),
                    'type' => 'card_unblocked',
                    'icon' => '✅',
                    'title' => "{$cardLabel} desimpedido",
                    'description' => null,
                    'actor' => $be->user?->name,
                    'item_id' => $be->item_id,
                ];
            }
        }

        if ($project->status === 'completed') {
            $events[] = [
                'date' => $project->updated_at?->toIso8601String(),
                'type' => 'project_completed',
                'icon' => '🏁',
                'title' => "Projeto \"{$project->name}\" concluído",
                'description' => null,
                'actor' => null,
            ];
        }

        usort($events, fn ($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));

        return $events;
    }
}
