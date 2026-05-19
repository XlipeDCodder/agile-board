<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Comment;
use App\Models\Item;
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

        // Conclusões: para cada item criado pelo ou atribuído ao usuário,
        // o evento de conclusão é a última entrada no histórico em que o item
        // entrou na coluna "Feito".
        $doneColumnId = Column::where('name', 'Feito')->value('id');
        if ($doneColumnId) {
            $relatedItemIds = $createdItems->pluck('id')->merge($assignedItemsList->pluck('id'))->unique();
            $doneHistories = ItemStatusHistory::whereIn('item_id', $relatedItemIds)
                ->where('column_id', $doneColumnId)
                ->whereNotNull('created_at')
                ->orderBy('created_at')
                ->get()
                ->groupBy('item_id');

            $itemsById = $createdItems->keyBy('id')->union($assignedItemsList->keyBy('id'));
            foreach ($doneHistories as $itemId => $history) {
                $last = $history->last();
                $item = $itemsById[$itemId] ?? null;
                $events[] = [
                    'date' => $last->created_at?->toIso8601String(),
                    'type' => 'item_completed',
                    'icon' => '✅',
                    'title' => "Card #{$itemId} concluído".($item?->title ? " \"{$item->title}\"" : ''),
                    'description' => null,
                    'actor' => null,
                    'item_id' => $itemId,
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
