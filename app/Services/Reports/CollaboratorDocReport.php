<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\CollaboratorContextBuilder;
use Carbon\Carbon;

class CollaboratorDocReport
{
    public function __construct(private CollaboratorContextBuilder $context) {}

    /**
     * @return array{title: string, markdown: string}
     */
    public function build(User $collaborator): array
    {
        $ctx = $this->context->build($collaborator);
        $title = "Relatório de Produtividade — {$collaborator->name} — ".Carbon::now()->format('d/m/Y');

        $md = "# {$title}\n\n";
        $md .= "Colaborador: **{$collaborator->name}** ({$collaborator->email})\n";
        $md .= "Período analisado: últimos {$ctx['window']['detailed_days']} dias\n\n";

        $agg = $ctx['aggregates_all_time'];
        $md .= "## Resumo executivo\n\n";
        $md .= "- Cards criados (all-time): **{$agg['items_created_total']}**\n";
        $md .= "- Cards atribuídos (all-time): **{$agg['items_assigned_total']}**\n";
        $md .= "- Cards concluídos como criador: **{$agg['items_completed_as_creator']}**\n";
        $md .= "- Cards concluídos como responsável: **{$agg['items_completed_as_assignee']}**\n";
        $md .= "- Subtarefas criadas: {$agg['subtasks_created_total']} (concluídas: {$agg['subtasks_completed_total']})\n";
        $md .= "- Cards reabertos: {$agg['cards_reopened_total']}\n";
        $md .= "- Cards atualmente impedidos: {$agg['cards_currently_blocked']}\n";
        $tempoMedio = $agg['avg_hours_blocked'] !== null ? "{$agg['avg_hours_blocked']}h" : "—";
        $md .= "- Tempo médio em impedimento: {$tempoMedio}\n";
        $md .= "- Horas apontadas (total): {$agg['total_hours_logged']}h\n\n";

        if (! empty($agg['minutes_by_project'])) {
            $md .= "## Tempo apontado por projeto\n\n";
            foreach ($agg['minutes_by_project'] as $row) {
                $md .= "- {$row['project_name']}: {$row['hours']}h\n";
            }
            $md .= "\n";
        }

        if (! empty($agg['priority_distribution'])) {
            $md .= "## Distribuição por prioridade\n\n";
            foreach ($agg['priority_distribution'] as $priority => $count) {
                $md .= "- {$priority}: {$count}\n";
            }
            $md .= "\n";
        }

        if (! empty($agg['avg_hours_in_column'])) {
            $md .= "## Tempo médio por coluna\n\n";
            foreach ($agg['avg_hours_in_column'] as $row) {
                $md .= "- {$row['column']}: {$row['avg_hours']}h (transições: {$row['transitions_counted']})\n";
            }
            $md .= "\n";
        }

        $md .= "## Cards recentes (últimos 90 dias)\n\n";
        if (empty($ctx['items_recent_90d'])) {
            $md .= "_Nenhum card no período._\n\n";
        } else {
            foreach ($ctx['items_recent_90d'] as $item) {
                $statusBits = [];
                if ($item['is_completed']) $statusBits[] = 'concluído';
                if ($item['is_blocked']) $statusBits[] = 'impedido';
                if ($item['is_reopen']) $statusBits[] = 'reabertura';
                $statusStr = $statusBits ? ' ('.implode(', ', $statusBits).')' : '';
                $md .= "### #{$item['id']} {$item['title']}{$statusStr}\n\n";
                $md .= "Projeto: {$item['project']} · Coluna atual: {$item['current_column']} · Prioridade: {$item['priority']}\n\n";
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

        $blockedItems = array_filter($ctx['items_recent_90d'], fn ($i) => $i['is_blocked']);
        if (! empty($blockedItems)) {
            $md .= "## Cards atualmente impedidos\n\n";
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
