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

        // 1) Cria doc vazio.
        $doc = $docs->documents->create(new Document(['title' => $title]));
        $docId = $doc->getDocumentId();

        // 2) Converte markdown em requests pro batchUpdate.
        $requests = $this->markdownToRequests($markdownBody);

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
     * Parser super-simples de markdown → requests do Docs API.
     * Suporte: headings (# ## ###), parágrafos, listas (- ).
     * Negrito (**) e tabelas viram texto cru (V1).
     */
    private function markdownToRequests(string $markdown): array
    {
        $lines = explode("\n", $markdown);

        // Acumulamos o texto puro + uma lista de "ranges" que precisam de
        // formatação específica (heading 1/2/3, list bullet).
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
                // separador horizontal — vira parágrafo vazio
                $content = '';
            }

            // Captura ranges de **negrito** dentro do conteúdo já limpo.
            // Substitui ** por vazio e remarca o offset.
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
            $end = mb_strlen($text); // inclui o \n

            if ($kind === 'h1') $rangesH1[] = [$start, $end];
            if ($kind === 'h2') $rangesH2[] = [$start, $end];
            if ($kind === 'h3') $rangesH3[] = [$start, $end];
            if ($kind === 'bullet') $rangesBullet[] = [$start, $end];
        }

        $requests = [];

        if ($text === '') {
            return $requests;
        }

        // Insere todo o texto de uma vez no índice 1 (logo após o início do body).
        $requests[] = new DocsRequest([
            'insertText' => [
                'location' => ['index' => 1],
                'text' => $text,
            ],
        ]);

        // Os offsets dos ranges acima são "no texto"; no doc, eles começam em 1
        // (porque o índice 1 é onde inserimos), então deslocamos +1.
        $shift = fn ($r) => [$r[0] + 1, $r[1] + 1];

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
