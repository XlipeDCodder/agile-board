<?php

namespace App\Services\Bot\Tools;

use App\Models\Project;
use App\Models\User;
use App\Services\Google\GoogleAuthException;
use App\Services\Google\GoogleDocsService;
use App\Services\Google\GoogleSheetsService;
use App\Services\Reports\CollaboratorDocReport;
use App\Services\Reports\CollaboratorSheetReport;
use App\Services\Reports\ProjectDocReport;
use App\Services\Reports\ProjectTimelineSheetReport;
use Illuminate\Support\Facades\Log;

class IcarusToolRegistry
{
    public function __construct(
        private GoogleDocsService $docs,
        private GoogleSheetsService $sheets,
        private CollaboratorDocReport $collabDoc,
        private CollaboratorSheetReport $collabSheet,
        private ProjectDocReport $projectDoc,
        private ProjectTimelineSheetReport $projectTimelineSheet,
    ) {}

    /**
     * Declarações das funções no formato Gemini function_declarations.
     * Tipos: TYPE_STRING, TYPE_INTEGER, TYPE_NUMBER, TYPE_BOOLEAN, TYPE_OBJECT, TYPE_ARRAY.
     */
    public function declarations(): array
    {
        return [
            [
                'name' => 'generate_collaborator_doc',
                'description' => 'Gera um Google Doc com o relatório de produtividade do colaborador em foco. O documento é criado no Google Drive do gestor que está conversando. Retorna a URL do arquivo. Use quando o gestor pedir um "relatório", "report", "documento de produtividade" ou similar sobre o colaborador.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
            [
                'name' => 'generate_collaborator_sheet',
                'description' => 'Gera uma Google Sheet com a lista de cards do colaborador em foco (uma linha por card, com colunas como título, projeto, status, prioridade, prazo, impedimento). Criada no Drive do gestor. Use quando o gestor pedir "planilha", "spreadsheet" ou listagem estruturada dos cards do colaborador.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new \stdClass(),
                    'required' => [],
                ],
            ],
            [
                'name' => 'generate_project_doc',
                'description' => 'Gera um Google Doc com o relatório de um projeto específico (resumo executivo, métricas, lista de cards, riscos/impedimentos). Criado no Drive do gestor. Use quando o gestor pedir relatório de um projeto. Requer o ID numérico do projeto — se não souber, pergunte ao gestor antes de chamar.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID numérico do projeto.',
                        ],
                    ],
                    'required' => ['project_id'],
                ],
            ],
            [
                'name' => 'generate_project_timeline_sheet',
                'description' => 'Gera uma Google Sheet com a timeline cronológica de eventos de um projeto (criação de cards, transições, comentários, bloqueios/desbloqueios, reaberturas, conclusões). Criada no Drive do gestor. Requer o ID do projeto.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID numérico do projeto.',
                        ],
                    ],
                    'required' => ['project_id'],
                ],
            ],
        ];
    }

    /**
     * Executa uma tool. Retorna o payload que vai virar functionResponse pro LLM.
     *
     * @param  string  $name  Nome da tool (deve bater com declarations)
     * @param  array  $args   Args do LLM
     * @param  User   $actor  Gestor logado (dono do Drive onde o arquivo será criado)
     * @param  User   $focusedCollaborator  Colaborador em foco no chat atual
     */
    public function execute(string $name, array $args, User $actor, User $focusedCollaborator): array
    {
        $start = microtime(true);
        try {
            $result = match ($name) {
                'generate_collaborator_doc' => $this->runCollaboratorDoc($actor, $focusedCollaborator),
                'generate_collaborator_sheet' => $this->runCollaboratorSheet($actor, $focusedCollaborator),
                'generate_project_doc' => $this->runProjectDoc($actor, $args),
                'generate_project_timeline_sheet' => $this->runProjectTimelineSheet($actor, $args),
                default => ['error' => "Tool desconhecida: {$name}"],
            };
        } catch (GoogleAuthException $e) {
            $result = ['error' => $e->getMessage(), 'reconnect_required' => true];
        } catch (\Throwable $e) {
            Log::error('Falha ao executar tool do Icarus', [
                'tool' => $name,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);
            $result = ['error' => "Não foi possível gerar o arquivo agora. Tente novamente em alguns instantes."];
        }

        $latency = (int) ((microtime(true) - $start) * 1000);
        Log::info('Icarus tool executed', [
            'tool' => $name,
            'actor_id' => $actor->id,
            'focused_collaborator_id' => $focusedCollaborator->id,
            'latency_ms' => $latency,
            'success' => ! isset($result['error']),
            'file_id' => $result['file_id'] ?? null,
        ]);
        return $result;
    }

    private function runCollaboratorDoc(User $actor, User $collaborator): array
    {
        $built = $this->collabDoc->build($collaborator);
        return $this->docs->createDoc($actor, $built['title'], $built['markdown']);
    }

    private function runCollaboratorSheet(User $actor, User $collaborator): array
    {
        $built = $this->collabSheet->build($collaborator);
        return $this->sheets->createSheet($actor, $built['title'], $built['headers'], $built['rows']);
    }

    private function runProjectDoc(User $actor, array $args): array
    {
        $project = $this->resolveProject($args);
        if (! $project) {
            return ['error' => 'Projeto não encontrado. Confirme o ID e tente de novo.'];
        }
        $built = $this->projectDoc->build($project);
        return $this->docs->createDoc($actor, $built['title'], $built['markdown']);
    }

    private function runProjectTimelineSheet(User $actor, array $args): array
    {
        $project = $this->resolveProject($args);
        if (! $project) {
            return ['error' => 'Projeto não encontrado. Confirme o ID e tente de novo.'];
        }
        $built = $this->projectTimelineSheet->build($project);
        return $this->sheets->createSheet($actor, $built['title'], $built['headers'], $built['rows']);
    }

    private function resolveProject(array $args): ?Project
    {
        $id = $args['project_id'] ?? null;
        if (! is_int($id) && ! ctype_digit((string) $id)) {
            return null;
        }
        return Project::find((int) $id);
    }
}
