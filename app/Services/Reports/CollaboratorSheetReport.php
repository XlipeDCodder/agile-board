<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\CollaboratorContextBuilder;
use Carbon\Carbon;

class CollaboratorSheetReport
{
    public function __construct(private CollaboratorContextBuilder $context) {}

    /**
     * @return array{title: string, headers: array<int,string>, rows: array<int,array<int,string>>}
     */
    public function build(User $collaborator): array
    {
        $ctx = $this->context->build($collaborator);
        $title = "Cards de {$collaborator->name} — ".Carbon::now()->format('d/m/Y');

        $headers = [
            '#', 'Título', 'Projeto', 'Tipo', 'Coluna atual', 'Prioridade',
            'Estimation (pts)', 'Previsto', 'Impedido?', 'Motivo do impedimento',
            'Reabertura?', 'Criado em', 'Concluído em',
        ];

        $rows = [];
        foreach ($ctx['items_recent_90d'] as $item) {
            $predicted = $item['predicted']
                ? "{$item['predicted']['value']} {$item['predicted']['unit']}"
                : '';
            $rows[] = [
                (string) $item['id'],
                (string) $item['title'],
                (string) ($item['project'] ?? ''),
                (string) $item['type'],
                (string) ($item['current_column'] ?? ''),
                (string) ($item['priority'] ?? ''),
                (string) ($item['estimation'] ?? ''),
                $predicted,
                $item['is_blocked'] ? 'Sim' : 'Não',
                (string) ($item['blocked_reason'] ?? ''),
                $item['is_reopen'] ? 'Sim' : 'Não',
                $this->formatDate($item['created_at']),
                $this->formatDate($item['completed_at_inferred']),
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
