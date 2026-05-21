<?php

namespace App\Services\Reports;

use App\Models\Project;
use App\Services\ProjectTimelineBuilder;
use Carbon\Carbon;

class ProjectTimelineSheetReport
{
    public function __construct(private ProjectTimelineBuilder $timeline) {}

    /**
     * @return array{title: string, headers: array<int,string>, rows: array<int,array<int,string>>}
     */
    public function build(Project $project): array
    {
        $events = $this->timeline->build($project);
        $title = "Timeline — {$project->name} — ".Carbon::now()->format('d/m/Y');

        $headers = [
            'Data/Hora', 'Tipo', 'Título', 'Descrição', 'Ator', 'Card ID',
        ];

        $rows = [];
        foreach ($events as $event) {
            $rows[] = [
                $this->formatDate($event['date'] ?? null),
                (string) ($event['type'] ?? ''),
                (string) ($event['title'] ?? ''),
                (string) ($event['description'] ?? ''),
                (string) ($event['actor'] ?? ''),
                isset($event['item_id']) ? (string) $event['item_id'] : '',
            ];
        }

        return ['title' => $title, 'headers' => $headers, 'rows' => $rows];
    }

    private function formatDate(?string $iso): string
    {
        if (! $iso) return '';
        try {
            return Carbon::parse($iso)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
