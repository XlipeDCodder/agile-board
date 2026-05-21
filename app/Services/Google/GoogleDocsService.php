<?php

namespace App\Services\Google;

use App\Models\User;
use Google\Service\Docs;
use Google\Service\Docs\BatchUpdateDocumentRequest;
use Google\Service\Docs\Document;
use Google\Service\Docs\Request as DocsRequest;

class GoogleDocsService
{
    public function __construct(private GoogleClientFactory $clientFactory) {}

    /**
     * Cria um Google Doc no Drive do user e popula com markdown simples.
     *
     * @return array{file_id: string, file_url: string, title: string}
     */
    public function createDoc(User $user, string $title, string $markdownBody): array
    {
        $client = $this->clientFactory->forUser($user);
        $docs = new Docs($client);

        $doc = $docs->documents->create(new Document(['title' => $title]));
        $docId = $doc->getDocumentId();

        $requests = $this->markdownToRequests($markdownBody, 1);

        if (! empty($requests)) {
            $docs->documents->batchUpdate($docId, new BatchUpdateDocumentRequest([
                'requests' => $requests,
            ]));
        }

        return [
            'file_id' => $docId,
            'file_url' => "https://docs.google.com/document/d/{$docId}/edit",
            'title' => $title,
        ];
    }

    /**
     * Anexa markdown ao FIM do doc existente. Útil pra "adiciona assinatura",
     * "adiciona uma nova seção", etc.
     *
     * @return array{file_id: string, file_url: string, title: string}
     */
    public function appendToDoc(User $user, string $fileId, string $markdownBody): array
    {
        $client = $this->clientFactory->forUser($user);
        $docs = new Docs($client);

        // Lê o doc pra descobrir o endIndex do body. O Google sempre mantém um
        // newline final, então o último índice "seguro" é endIndex - 1.
        $doc = $docs->documents->get($fileId);
        $body = $doc->getBody();
        $content = $body->getContent();
        $lastEnd = 1;
        foreach ($content as $element) {
            if ($element->getEndIndex() !== null) {
                $lastEnd = $element->getEndIndex();
            }
        }
        $insertAt = max(1, $lastEnd - 1);

        // Adiciona uma quebra de linha de "separação" antes do conteúdo novo
        // pra ele não emendar visualmente no parágrafo anterior.
        $body = "\n".$markdownBody;
        $requests = $this->markdownToRequests($body, $insertAt);

        if (! empty($requests)) {
            $docs->documents->batchUpdate($fileId, new BatchUpdateDocumentRequest([
                'requests' => $requests,
            ]));
        }

        return [
            'file_id' => $fileId,
            'file_url' => "https://docs.google.com/document/d/{$fileId}/edit",
            'title' => $doc->getTitle(),
        ];
    }

    /**
     * Substitui todas as ocorrências de `find` por `replace` no doc inteiro.
     * Case-sensitive (Docs API default). Se nada bater, retorna 0 ocorrências
     * no campo `replacements_count`.
     *
     * @return array{file_id: string, file_url: string, title: string, replacements_count: int}
     */
    public function replaceInDoc(User $user, string $fileId, string $find, string $replace): array
    {
        $client = $this->clientFactory->forUser($user);
        $docs = new Docs($client);

        $response = $docs->documents->batchUpdate($fileId, new BatchUpdateDocumentRequest([
            'requests' => [
                new DocsRequest([
                    'replaceAllText' => [
                        'containsText' => ['text' => $find, 'matchCase' => true],
                        'replaceText' => $replace,
                    ],
                ]),
            ],
        ]));

        $replies = $response->getReplies();
        $count = 0;
        if ($replies && count($replies) > 0) {
            $first = $replies[0];
            if (method_exists($first, 'getReplaceAllText') && $first->getReplaceAllText()) {
                $count = (int) $first->getReplaceAllText()->getOccurrencesChanged();
            }
        }

        $doc = $docs->documents->get($fileId);

        return [
            'file_id' => $fileId,
            'file_url' => "https://docs.google.com/document/d/{$fileId}/edit",
            'title' => $doc->getTitle(),
            'replacements_count' => $count,
        ];
    }

    /**
     * Parser super-simples de markdown → requests do Docs API, gerando
     * conteúdo a partir de $startIndex (1 pra docs novos, endIndex-1 pra
     * append em docs existentes).
     */
    private function markdownToRequests(string $markdown, int $startIndex): array
    {
        $lines = explode("\n", $markdown);

        $text = '';
        $rangesH1 = [];
        $rangesH2 = [];
        $rangesH3 = [];
        $rangesBullet = [];
        $rangesBold = [];

        foreach ($lines as $line) {
            $start = mb_strlen($text);
            $content = $line;
            $kind = 'paragraph';

            if (preg_match('/^### (.+)$/', $line, $m)) {
                $content = $m[1];
                $kind = 'h3';
            } elseif (preg_match('/^## (.+)$/', $line, $m)) {
                $content = $m[1];
                $kind = 'h2';
            } elseif (preg_match('/^# (.+)$/', $line, $m)) {
                $content = $m[1];
                $kind = 'h1';
            } elseif (preg_match('/^- (.+)$/', $line, $m)) {
                $content = $m[1];
                $kind = 'bullet';
            } elseif (preg_match('/^---$/', $line)) {
                $content = '';
            }

            $cleanContent = '';
            $i = 0;
            $boldOpen = null;
            while ($i < mb_strlen($content)) {
                if (mb_substr($content, $i, 2) === '**') {
                    if ($boldOpen === null) {
                        $boldOpen = mb_strlen($cleanContent);
                    } else {
                        $rangesBold[] = [
                            $start + $boldOpen,
                            $start + mb_strlen($cleanContent),
                        ];
                        $boldOpen = null;
                    }
                    $i += 2;
                    continue;
                }
                $cleanContent .= mb_substr($content, $i, 1);
                $i++;
            }

            $text .= $cleanContent."\n";
            $end = mb_strlen($text);

            if ($kind === 'h1') $rangesH1[] = [$start, $end];
            if ($kind === 'h2') $rangesH2[] = [$start, $end];
            if ($kind === 'h3') $rangesH3[] = [$start, $end];
            if ($kind === 'bullet') $rangesBullet[] = [$start, $end];
        }

        $requests = [];

        if ($text === '') {
            return $requests;
        }

        $requests[] = new DocsRequest([
            'insertText' => [
                'location' => ['index' => $startIndex],
                'text' => $text,
            ],
        ]);

        $shift = fn ($r) => [$r[0] + $startIndex, $r[1] + $startIndex];

        foreach ($rangesH1 as $r) {
            $r = $shift($r);
            $requests[] = $this->paragraphStyleRequest($r[0], $r[1], 'HEADING_1');
        }
        foreach ($rangesH2 as $r) {
            $r = $shift($r);
            $requests[] = $this->paragraphStyleRequest($r[0], $r[1], 'HEADING_2');
        }
        foreach ($rangesH3 as $r) {
            $r = $shift($r);
            $requests[] = $this->paragraphStyleRequest($r[0], $r[1], 'HEADING_3');
        }
        foreach ($rangesBullet as $r) {
            $r = $shift($r);
            $requests[] = new DocsRequest([
                'createParagraphBullets' => [
                    'range' => ['startIndex' => $r[0], 'endIndex' => $r[1]],
                    'bulletPreset' => 'BULLET_DISC_CIRCLE_SQUARE',
                ],
            ]);
        }
        foreach ($rangesBold as $r) {
            $r = $shift($r);
            if ($r[1] <= $r[0]) continue;
            $requests[] = new DocsRequest([
                'updateTextStyle' => [
                    'range' => ['startIndex' => $r[0], 'endIndex' => $r[1]],
                    'textStyle' => ['bold' => true],
                    'fields' => 'bold',
                ],
            ]);
        }

        return $requests;
    }

    private function paragraphStyleRequest(int $start, int $end, string $namedStyleType): DocsRequest
    {
        return new DocsRequest([
            'updateParagraphStyle' => [
                'range' => ['startIndex' => $start, 'endIndex' => $end],
                'paragraphStyle' => ['namedStyleType' => $namedStyleType],
                'fields' => 'namedStyleType',
            ],
        ]);
    }
}
