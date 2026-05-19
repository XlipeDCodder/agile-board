<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Item;
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
            ->where('project_id', $project->id)
            ->get();

        $itemIds = $items->pluck('id');

        $columns = \App\Models\Column::pluck('name', 'id');

        $histories = ItemStatusHistory::whereIn('item_id', $itemIds)
            ->whereNotNull('created_at')
            ->orderBy('created_at')
            ->get();

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

            if ($item->completed_at) {
                $events[] = [
                    'date' => $item->completed_at instanceof \Carbon\Carbon
                        ? $item->completed_at->toIso8601String()
                        : (string) $item->completed_at,
                    'type' => 'item_completed',
                    'icon' => '✅',
                    'title' => "Card #{$item->id} concluído",
                    'description' => $item->title,
                    'actor' => null,
                    'item_id' => $item->id,
                ];
            }
        }

        foreach ($histories as $history) {
            $columnName = $columns[$history->column_id] ?? 'desconhecida';
            $events[] = [
                'date' => $history->created_at?->toIso8601String(),
                'type' => 'item_moved',
                'icon' => '➡️',
                'title' => "Card #{$history->item_id} movido para \"{$columnName}\"",
                'description' => null,
                'actor' => null,
                'item_id' => $history->item_id,
            ];
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
