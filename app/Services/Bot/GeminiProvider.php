<?php

namespace App\Services\Bot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements BotProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.0-flash',
        private readonly bool $verifySsl = true,
    ) {
    }

    public function chat(string $systemPrompt, array $messages, array $tools = []): ChatResponse
    {
        $contents = $this->buildContents($messages);

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 2048,
            ],
        ];

        if (! empty($tools)) {
            $payload['tools'] = [
                ['functionDeclarations' => $tools],
            ];
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            urlencode($this->model),
        );

        if (! $this->verifySsl && app()->environment('production')) {
            Log::warning('GeminiProvider rodando com SSL verification desligado em produção (BOT_VERIFY_SSL=false).');
        }

        $request = Http::timeout(45)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['x-goog-api-key' => $this->apiKey]);

        if (! $this->verifySsl) {
            $request = $request->withoutVerifying();
        }

        $response = $request->post($url, $payload);

        if ($response->failed()) {
            $errorMessage = $response->json('error.message') ?? $response->body();
            throw new BotException(
                "Falha ao chamar Gemini ({$response->status()}): ".$this->sanitizeError($errorMessage),
            );
        }

        // O Gemini pode retornar várias parts; procuramos primeiro um
        // functionCall (prioridade) e caímos pra text se não houver.
        $parts = $response->json('candidates.0.content.parts') ?? [];
        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                return ChatResponse::functionCall(
                    name: $part['functionCall']['name'],
                    args: $part['functionCall']['args'] ?? [],
                );
            }
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (is_string($text) && $text !== '') {
            return ChatResponse::text($text);
        }

        $finishReason = $response->json('candidates.0.finishReason') ?? 'desconhecido';
        throw new BotException("Resposta vazia do Gemini (motivo: {$finishReason}).");
    }

    /**
     * Converte o histórico abstrato em "contents" no formato Gemini.
     * Suporta 3 tipos de mensagem: texto, function_call (turn do model
     * que pediu execução) e function_response (resultado da execução).
     */
    private function buildContents(array $messages): array
    {
        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';

            if (isset($msg['function_call'])) {
                $contents[] = [
                    'role' => 'model',
                    'parts' => [[
                        'functionCall' => [
                            'name' => $msg['function_call']['name'],
                            'args' => $msg['function_call']['args'] ?? new \stdClass(),
                        ],
                    ]],
                ];
                continue;
            }

            if ($role === 'function') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $msg['name'] ?? '',
                            'response' => $msg['response'] ?? new \stdClass(),
                        ],
                    ]],
                ];
                continue;
            }

            $geminiRole = $role === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $geminiRole,
                'parts' => [['text' => (string) ($msg['content'] ?? '')]],
            ];
        }
        return $contents;
    }

    /**
     * Remove API keys, URLs com `key=...` e qualquer token longo que pareça
     * uma chave de qualquer mensagem de erro antes de ela vazar pro frontend
     * ou pros logs.
     */
    private function sanitizeError(string $message): string
    {
        $message = preg_replace('/https?:\/\/\S+/i', '[url-removida]', $message);
        $message = preg_replace('/[?&]key=[^\s&]+/i', '?key=[removida]', $message);
        if ($this->apiKey !== '') {
            $message = str_replace($this->apiKey, '[api-key-removida]', $message);
        }
        return $message;
    }
}
