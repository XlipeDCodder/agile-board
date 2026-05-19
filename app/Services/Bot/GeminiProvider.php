<?php

namespace App\Services\Bot;

use Illuminate\Support\Facades\Http;

class GeminiProvider implements BotProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.0-flash',
    ) {
    }

    public function chat(string $systemPrompt, array $messages): string
    {
        $contents = [];
        foreach ($messages as $msg) {
            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content'] ?? '']],
            ];
        }

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

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            urlencode($this->model),
            urlencode($this->apiKey),
        );

        $response = Http::timeout(45)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if ($response->failed()) {
            $errorMessage = $response->json('error.message') ?? $response->body();
            throw new BotException("Falha ao chamar Gemini ({$response->status()}): {$errorMessage}");
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            $finishReason = $response->json('candidates.0.finishReason') ?? 'desconhecido';
            throw new BotException("Resposta vazia do Gemini (motivo: {$finishReason}).");
        }

        return $text;
    }
}
