<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Comment;
use App\Models\Item;
use App\Models\ItemBlockEvent;
use App\Models\ItemStatusHistory;
use App\Models\TimeEntry;
use App\Models\User;

class CollaboratorTimelineBuilder
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function build(User $user): array
    {
        $events = [];

        $events[] = [
            'date' => $user->created_at?->toIso8601String(),
            'type' => 'user_joined',
            'icon' => '👤',
            'title' => "{$user->name} entrou na plataforma",
            'description' => $user->email,
            'actor' => null,
        ];

        $createdItems = Item::with('project:id,name')
            ->whereNull('parent_id')
            ->where('creator_id', $user->id)
            ->get();

        foreach ($createdItems as $item) {
            $events[] = [
                'date' => $item->created_at?->toIso8601String(),
                'type' => 'item_created',
                'icon' => $item->type === 'bug' ? '🐛' : '📝',
                'title' => "Criou o card #{$item->id} \"{$item->title}\"",
                'description' => ($item->project?->name ? "Projeto: {$item->project->name}" : null),
                'actor' => $user->name,
                'item_id' => $item->id,
            ];

        }

        $assignedItemsList = Item::whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->whereNull('parent_id')
            ->with('project:id,name')
            ->get();

        foreach ($assignedItemsList as $item) {
            if ($item->creator_id === $user->id) {
                continue; // já contado acima
            }
            $events[] = [
                'date' => $item->created_at?->toIso8601String(),
                'type' => 'item_assigned',
                'icon' => '🎯',
                'title' => "Foi atribuído ao card #{$item->id} \"{$item->title}\"",
                'description' => ($item->project?->name ? "Projeto: {$item->project->name}" : null),
                'actor' => $user->name,
                'item_id' => $item->id,
            ];
        }

        // Subtarefas criadas pelo usuário: emite criação e, se houver,
        // conclusão (subtarefas usam o campo completed_at de verdade,
        // diferente dos cards).
        $createdSubtasks = Item::whereNotNull('parent_id')
            ->where('creator_id', $user->id)
            ->with('parent:id,title,project_id')
            ->with('parent.project:id,name')
            ->get();

        foreach ($createdSubtasks as $subtask) {
            $parentRef = $subtask->parent
                ? "card #{$subtask->parent->id} \"{$subtask->parent->title}\""
                : 'card pai desconhecido';

            $events[] = [
                'date' => $subtask->created_at?->toIso8601String(),
                'type' => 'subtask_created',
                'icon' => '➕',
                'title' => "Criou a subtarefa \"{$subtask->title}\" no {$parentRef}",
                'description' => $subtask->parent?->project?->name ? "Projeto: {$subtask->parent->project->name}" : null,
                'actor' => $user->name,
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
                    'title' => "Subtarefa \"{$subtask->title}\" concluída ({$parentRef})",
                    'description' => null,
                    'actor' => null,
                    'item_id' => $subtask->id,
                    'parent_id' => $subtask->parent_id,
                ];
            }
        }

        $comments = Comment::with('item:id,title')
            ->where('user_id', $user->id)
            ->get();

        foreach ($comments as $comment) {
            $body = mb_strlen($comment->body) > 200
                ? mb_substr($comment->body, 0, 200).'…'
                : $comment->body;

            $events[] = [
                'date' => $comment->created_at?->toIso8601String(),
                'type' => 'comment',
                'icon' => '💬',
                'title' => "Comentou no card #{$comment->item_id}".($comment->item?->title ? " \"{$comment->item->title}\"" : ''),
                'description' => $body,
                'actor' => $user->name,
                'item_id' => $comment->item_id,
            ];
        }

        // Transições e conclusões: usa o histórico de mudanças de coluna dos
        // cards criados pelo ou atribuídos ao usuário. Movimentações para a
        // coluna "Feito" viram evento de conclusão; demais viram "movido para".
        $columns = Column::pluck('name', 'id')->all();
        $doneColumnId = Column::where('name', 'Feito')->value('id');

        $relatedItems = $createdItems->merge($assignedItemsList)->unique('id')->keyBy('id');
        $relatedItemIds = $relatedItems->keys()->all();

        if (! empty($relatedItemIds)) {
            $allHistories = ItemStatusHistory::whereIn('item_id', $relatedItemIds)
                ->orderBy('item_id')
                ->orderBy('created_at')
                ->get(['id', 'item_id', 'column_id', 'created_at']);

            $historyByItem = $allHistories->groupBy('item_id');

            foreach ($allHistories as $h) {
                $item = $relatedItems[$h->item_id] ?? null;
                $colName = $columns[$h->column_id] ?? 'desconhecida';

                // Fallback de timestamp: muitos registros antigos têm created_at NULL
                // (o $timestamps = false do model nunca preenchia). Para esses,
                // usamos o updated_at do item como aproximação.
                $when = $h->created_at?->toIso8601String() ?? $item?->updated_at?->toIso8601String();
                if (! $when) {
                    continue;
                }

                if ($doneColumnId && $h->column_id == $doneColumnId) {
                    // Só emite o "concluído" para a ÚLTIMA entrada em Feito,
                    // para não duplicar caso o card tenha sido reaberto e fechado.
                    $lastDone = $historyByItem[$h->item_id]
                        ->where('column_id', $doneColumnId)
                        ->last();
                    if ($lastDone && $lastDone->id !== $h->id) {
                        continue;
                    }
                    $events[] = [
                        'date' => $when,
                        'type' => 'item_completed',
                        'icon' => '✅',
                        'title' => "Card #{$h->item_id} concluído".($item?->title ? " \"{$item->title}\"" : ''),
                        'description' => null,
                        'actor' => null,
                        'item_id' => $h->item_id,
                    ];
                } else {
                    $events[] = [
                        'date' => $when,
                        'type' => 'item_moved',
                        'icon' => '➡️',
                        'title' => "Card #{$h->item_id} movido para \"{$colName}\"".($item?->title ? " (\"{$item->title}\")" : ''),
                        'description' => null,
                        'actor' => null,
                        'item_id' => $h->item_id,
                    ];
                }
            }

            // Cobertura para cards atualmente em "Feito" que NÃO têm nenhum
            // ItemStatusHistory válido para essa coluna (bug histórico):
            // emitir evento de conclusão usando o updated_at do item.
            if ($doneColumnId) {
                $itemsInDone = $relatedItems->where('column_id', $doneColumnId);
                foreach ($itemsInDone as $item) {
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
        }

        // Reaberturas: cards do tipo 'reabertura' criados pelo usuário,
        // com link para o card original e a justificativa registrada.
        $reopens = Item::whereNull('parent_id')
            ->where('creator_id', $user->id)
            ->where('type', 'reabertura')
            ->whereNotNull('reopened_from_id')
            ->with('reopenedFrom:id,title')
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
                'title' => "Reabriu o {$origin} como card #{$reopen->id} \"{$reopen->title}\"",
                'description' => "Motivo: {$justification}",
                'actor' => $user->name,
                'item_id' => $reopen->id,
                'parent_id' => $reopen->reopened_from_id,
            ];
        }

        // Eventos de bloqueio/desbloqueio executados pelo usuário.
        $blockEvents = ItemBlockEvent::where('user_id', $user->id)
            ->with('item:id,title', 'blockedByItem:id,title')
            ->orderBy('created_at')
            ->get();

        foreach ($blockEvents as $be) {
            $cardLabel = $be->item ? "card #{$be->item_id} \"{$be->item->title}\"" : "card #{$be->item_id}";
            if ($be->event === 'blocked') {
                $blockerLabel = $be->blockedByItem
                    ? " (bloqueado por card #{$be->blocked_by_item_id} \"{$be->blockedByItem->title}\")"
                    : '';
                $events[] = [
                    'date' => $be->created_at?->toIso8601String(),
                    'type' => 'card_blocked',
                    'icon' => '🚫',
                    'title' => "Marcou o {$cardLabel} como impedido{$blockerLabel}",
                    'description' => $be->reason ? "Motivo: {$be->reason}" : null,
                    'actor' => $user->name,
                    'item_id' => $be->item_id,
                ];
            } else {
                $events[] = [
                    'date' => $be->created_at?->toIso8601String(),
                    'type' => 'card_unblocked',
                    'icon' => '✅',
                    'title' => "Desimpediu o {$cardLabel}",
                    'description' => null,
                    'actor' => $user->name,
                    'item_id' => $be->item_id,
                ];
            }
        }

        $entriesByDate = TimeEntry::where('user_id', $user->id)
            ->selectRaw('date, SUM(minutes) as total_minutes')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        foreach ($entriesByDate as $row) {
            $hours = round(((int) $row->total_minutes) / 60, 1);
            $events[] = [
                'date' => $row->date.'T12:00:00Z',
                'type' => 'time_logged',
                'icon' => '⏱️',
                'title' => "Apontou {$hours}h no dia ".date('d/m/Y', strtotime($row->date)),
                'description' => null,
                'actor' => $user->name,
            ];
        }

        usort($events, fn ($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));

        return $events;
    }
}
