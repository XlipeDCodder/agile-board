<?php

namespace App\Services\Bot\Tools;

use App\Models\Project;
use App\Models\User;
use App\Services\Google\GoogleAuthException;
use App\Services\Google\GoogleDocsService;
use App\Services\Google\GoogleDriveService;
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
        private GoogleDriveService $drive,
        private CollaboratorDocReport $collabDoc,
        private CollaboratorSheetReport $collabSheet,
        private ProjectDocReport $projectDoc,
        private ProjectTimelineSheetReport $projectTimelineSheet,
    ) {}

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
                'description' => 'Gera um Google Doc com o relatório de um projeto específico (resumo executivo, métricas, lista de cards, riscos/impedimentos). Criado no Drive do gestor. Use quando o gestor pedir relatório de um projeto. Requer o ID numérico do projeto — se o gestor citar apenas o nome do projeto e você não souber o ID, PERGUNTE ao gestor antes de chamar (não ofereça alternativa).',
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
                'description' => 'Gera uma Google Sheet com a timeline cronológica de eventos de um projeto (criação de cards, transições, comentários, bloqueios/desbloqueios, reaberturas, conclusões). Criada no Drive do gestor. Requer o ID do projeto — pergunte se não souber.',
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
                'name' => 'append_to_doc',
                'description' => 'Anexa conteúdo ao FINAL de um Google Doc existente que o Icarus criou anteriormente. Use quando o gestor pedir "adicione X no documento", "inclua uma assinatura", "põe mais essa seção", etc. Só funciona em docs criados pelo próprio Icarus — qualquer doc de fora retorna erro.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'file' => [
                            'type' => 'STRING',
                            'description' => 'URL completa do doc (ex: https://docs.google.com/document/d/ABC/edit) OU apenas o ID do arquivo.',
                        ],
                        'markdown' => [
                            'type' => 'STRING',
                            'description' => 'Conteúdo em Markdown a ser adicionado. Suporta # ## ### headings, listas com "- ", **negrito**.',
                        ],
                    ],
                    'required' => ['file', 'markdown'],
                ],
            ],
            [
                'name' => 'replace_in_doc',
                'description' => 'Substitui TODAS as ocorrências de um texto por outro em um Google Doc existente. Útil pra trocar nomes, datas, valores. Case-sensitive. Retorna quantas substituições foram feitas.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'file' => [
                            'type' => 'STRING',
                            'description' => 'URL completa do doc OU ID do arquivo.',
                        ],
                        'find' => [
                            'type' => 'STRING',
                            'description' => 'Texto a procurar.',
                        ],
                        'replace' => [
                            'type' => 'STRING',
                            'description' => 'Texto que vai substituir o anterior.',
                        ],
                    ],
                    'required' => ['file', 'find', 'replace'],
                ],
            ],
            [
                'name' => 'append_rows_to_sheet',
                'description' => 'Anexa linhas ao final da primeira aba de uma Google Sheet existente. Use quando o gestor pedir "adiciona mais essas linhas", "inclui mais cards na planilha", etc. Só funciona em sheets criadas pelo Icarus.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'file' => [
                            'type' => 'STRING',
                            'description' => 'URL completa da sheet OU ID do arquivo.',
                        ],
                        'rows' => [
                            'type' => 'ARRAY',
                            'description' => 'Array de linhas, onde cada linha é um array de strings (colunas). Ex: [["A1","B1"],["A2","B2"]].',
                            'items' => [
                                'type' => 'ARRAY',
                                'items' => ['type' => 'STRING'],
                            ],
                        ],
                    ],
                    'required' => ['file', 'rows'],
                ],
            ],
            [
                'name' => 'update_sheet_range',
                'description' => 'Atualiza um range específico de células em uma Google Sheet (ex: A1:C5). Sobrescreve o conteúdo existente. Útil pra corrigir valores em massa.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'file' => [
                            'type' => 'STRING',
                            'description' => 'URL completa da sheet OU ID do arquivo.',
                        ],
                        'range' => [
                            'type' => 'STRING',
                            'description' => 'Range em notação A1, ex: "A1:C5" ou "Página1!B3:D10".',
                        ],
                        'values' => [
                            'type' => 'ARRAY',
                            'description' => 'Array de linhas (cada linha é array de strings) que vai preencher o range.',
                            'items' => [
                                'type' => 'ARRAY',
                                'items' => ['type' => 'STRING'],
                            ],
                        ],
                    ],
                    'required' => ['file', 'range', 'values'],
                ],
            ],
            [
                'name' => 'trash_drive_file',
                'description' => 'Move um Doc ou Sheet criado pelo Icarus para a LIXEIRA do Drive do gestor. Reversível: o gestor pode restaurar manualmente em drive.google.com/drive/trash em até 30 dias. Antes de chamar, CONFIRME com o gestor ("Tem certeza que quer mover X pra lixeira?") — exceto se a confirmação já estiver explícita na mensagem.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'file' => [
                            'type' => 'STRING',
                            'description' => 'URL completa do arquivo OU ID.',
                        ],
                    ],
                    'required' => ['file'],
                ],
            ],
        ];
    }

    /**
     * Executa uma tool. Retorna o payload que vai virar functionResponse pro LLM.
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
                'append_to_doc' => $this->runAppendToDoc($actor, $args),
                'replace_in_doc' => $this->runReplaceInDoc($actor, $args),
                'append_rows_to_sheet' => $this->runAppendRowsToSheet($actor, $args),
                'update_sheet_range' => $this->runUpdateSheetRange($actor, $args),
                'trash_drive_file' => $this->runTrashDriveFile($actor, $args),
                default => ['error' => "Tool desconhecida: {$name}"],
            };
        } catch (GoogleAuthException $e) {
            $result = ['error' => $e->getMessage(), 'reconnect_required' => true];
        } catch (\Google\Service\Exception $e) {
            // Erros vindos da API do Google (403 = drive.file scope, 404 = não existe, etc).
            $result = ['error' => $this->humanizeGoogleError($e)];
        } catch (\Throwable $e) {
            Log::error('Falha ao executar tool do Icarus', [
                'tool' => $name,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);
            $result = ['error' => "Não foi possível completar a operação agora. Tente novamente em alguns instantes."];
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

    private function runAppendToDoc(User $actor, array $args): array
    {
        $fileId = $this->parseFileId((string) ($args['file'] ?? ''));
        if (! $fileId) return ['error' => 'URL ou ID do arquivo inválido.'];
        $markdown = (string) ($args['markdown'] ?? '');
        if ($markdown === '') return ['error' => 'O conteúdo a anexar está vazio.'];
        return $this->docs->appendToDoc($actor, $fileId, $markdown);
    }

    private function runReplaceInDoc(User $actor, array $args): array
    {
        $fileId = $this->parseFileId((string) ($args['file'] ?? ''));
        if (! $fileId) return ['error' => 'URL ou ID do arquivo inválido.'];
        $find = (string) ($args['find'] ?? '');
        if ($find === '') return ['error' => 'O texto a procurar está vazio.'];
        $replace = (string) ($args['replace'] ?? '');
        return $this->docs->replaceInDoc($actor, $fileId, $find, $replace);
    }

    private function runAppendRowsToSheet(User $actor, array $args): array
    {
        $fileId = $this->parseFileId((string) ($args['file'] ?? ''));
        if (! $fileId) return ['error' => 'URL ou ID do arquivo inválido.'];
        $rows = $this->normalizeRows($args['rows'] ?? []);
        if (empty($rows)) return ['error' => 'Nenhuma linha foi fornecida pra anexar.'];
        return $this->sheets->appendRows($actor, $fileId, $rows);
    }

    private function runUpdateSheetRange(User $actor, array $args): array
    {
        $fileId = $this->parseFileId((string) ($args['file'] ?? ''));
        if (! $fileId) return ['error' => 'URL ou ID do arquivo inválido.'];
        $range = (string) ($args['range'] ?? '');
        if ($range === '') return ['error' => 'O range a atualizar não foi informado.'];
        $values = $this->normalizeRows($args['values'] ?? []);
        if (empty($values)) return ['error' => 'Nenhum valor fornecido pra atualizar o range.'];
        return $this->sheets->updateRange($actor, $fileId, $range, $values);
    }

    private function runTrashDriveFile(User $actor, array $args): array
    {
        $fileId = $this->parseFileId((string) ($args['file'] ?? ''));
        if (! $fileId) return ['error' => 'URL ou ID do arquivo inválido.'];
        return $this->drive->trashFile($actor, $fileId);
    }

    private function resolveProject(array $args): ?Project
    {
        $id = $args['project_id'] ?? null;
        if (! is_int($id) && ! ctype_digit((string) $id)) {
            return null;
        }
        return Project::find((int) $id);
    }

    /**
     * Extrai o ID de uma URL de Doc/Sheet/Drive, ou aceita o ID puro.
     */
    private function parseFileId(string $urlOrId): ?string
    {
        $urlOrId = trim($urlOrId);
        if ($urlOrId === '') return null;

        if (preg_match('#docs\.google\.com/(?:document|spreadsheets)/d/([a-zA-Z0-9_-]+)#', $urlOrId, $m)) {
            return $m[1];
        }
        if (preg_match('#drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#', $urlOrId, $m)) {
            return $m[1];
        }
        // ID puro do Google: ~44 chars com letras, dígitos, _ e -. Aceita 20+ pra ter margem.
        if (preg_match('#^[a-zA-Z0-9_-]{20,}$#', $urlOrId)) {
            return $urlOrId;
        }
        return null;
    }

    /**
     * Garante que rows é array de array de strings (Gemini às vezes manda
     * objetos diferentes — normaliza pra string).
     */
    private function normalizeRows($rows): array
    {
        if (! is_array($rows)) return [];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) continue;
            $out[] = array_map(fn ($v) => (string) $v, array_values($row));
        }
        return $out;
    }

    private function humanizeGoogleError(\Google\Service\Exception $e): string
    {
        $code = $e->getCode();
        if ($code === 404) {
            return 'Arquivo não encontrado. Confirme se o link/ID está correto.';
        }
        if ($code === 403) {
            return 'Sem permissão pra mexer nesse arquivo. O Icarus só consegue editar arquivos que ele mesmo criou no seu Drive.';
        }
        if ($code === 401) {
            return 'Sessão Google expirada. Tente desconectar e reconectar em Bot Config.';
        }
        return "Erro do Google API (HTTP {$code}). Tente novamente.";
    }
}
