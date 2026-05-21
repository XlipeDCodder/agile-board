<?php

namespace App\Services\Reports;

use App\Models\Project;
use App\Services\ProjectContextBuilder;
use Carbon\Carbon;

class ProjectDocReport
{
    public function __construct(private ProjectContextBuilder $context) {}

    /**
     * @return array{title: string, markdown: string}
     */
    public function build(Project $project): array
    {
        $ctx = $this->context->build($project);
        $title = "Relatório do Projeto — {$project->name} — ".Carbon::now()->format('d/m/Y');

        $md = "# {$title}\n\n";
        $md .= "Projeto: **{$project->name}**\n";
        if ($project->description) {
            $md .= "Descrição: {$project->description}\n";
        }
        $md .= "Status: {$project->status}\n";
        if ($ctx['project']['due_date']) {
            $md .= "Prazo: {$ctx['project']['due_date']}\n";
        }
        $md .= "\n";

        $agg = $ctx['aggregates'];
        $md .= "## Resumo executivo\n\n";
        $md .= "- Total de cards: **{$agg['cards_total']}**\n";
        $md .= "- Cards reabertos: {$agg['cards_reopened']}\n";
        $md .= "- Cards atualmente impedidos: {$agg['cards_currently_blocked']}\n";
        $tempoMedio = $agg['avg_hours_blocked'] !== null ? "{$agg['avg_hours_blocked']}h" : "—";
        $md .= "- Tempo médio em impedimento: {$tempoMedio}\n";
        $md .= "- Horas apontadas no projeto: {$agg['total_hours_logged']}h\n\n";

        if (! empty($agg['cards_by_column'])) {
            $md .= "## Cards por coluna\n\n";
            foreach ($agg['cards_by_column'] as $column => $count) {
                $md .= "- {$column}: {$count}\n";
            }
            $md .= "\n";
        }

        if (! empty($agg['cards_by_priority'])) {
            $md .= "## Cards por prioridade\n\n";
            foreach ($agg['cards_by_priority'] as $priority => $count) {
                $md .= "- {$priority}: {$count}\n";
            }
            $md .= "\n";
        }

        if (! empty($agg['contributors'])) {
            $md .= "## Contribuidores\n\n";
            foreach ($agg['contributors'] as $c) {
                $md .= "- {$c['name']} — criou {$c['cards_created']}, atribuído em {$c['cards_assigned']}\n";
            }
            $md .= "\n";
        }

        $md .= "## Cards do projeto\n\n";
        if (empty($ctx['items'])) {
            $md .= "_Nenhum card no projeto._\n\n";
        } else {
            foreach ($ctx['items'] as $item) {
                $statusBits = [];
                if ($item['is_completed']) $statusBits[] = 'concluído';
                if ($item['is_blocked']) $statusBits[] = 'impedido';
                if ($item['is_reopen']) $statusBits[] = 'reabertura';
                $statusStr = $statusBits ? ' ('.implode(', ', $statusBits).')' : '';
                $md .= "### #{$item['id']} {$item['title']}{$statusStr}\n\n";
                $md .= "Coluna atual: {$item['current_column']} · Prioridade: {$item['priority']}";
                if ($item['creator']) $md .= " · Criado por: {$item['creator']}";
                $md .= "\n\n";
                if ($item['description']) {
                    $md .= "{$item['description']}\n\n";
                }
                if ($item['is_blocked'] && $item['blocked_reason']) {
                    $md .= "**Motivo do impedimento:** {$item['blocked_reason']}\n\n";
                }
                if ($item['is_reopen'] && $item['justification']) {
                    $md .= "**Justificativa da reabertura:** {$item['justification']}\n\n";
                }
            }
        }

        $blockedItems = array_filter($ctx['items'], fn ($i) => $i['is_blocked']);
        if (! empty($blockedItems)) {
            $md .= "## Riscos / cards atualmente impedidos\n\n";
            foreach ($blockedItems as $item) {
                $motivo = $item['blocked_reason'] ?: '(sem motivo registrado)';
                $md .= "- #{$item['id']} {$item['title']} — {$motivo}\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";
        $md .= "_Relatório gerado pelo Icarus em ".Carbon::now()->format('d/m/Y H:i')."._\n";

        return ['title' => $title, 'markdown' => $md];
    }
}
