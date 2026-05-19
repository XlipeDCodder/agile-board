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
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            urlencode($this->model),
        );

        // Aviso de segurança: SSL desligado em produção é MITM aberto.
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

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            $finishReason = $response->json('candidates.0.finishReason') ?? 'desconhecido';
            throw new BotException("Resposta vazia do Gemini (motivo: {$finishReason}).");
        }

        return $text;
    }

    /**
     * Remove API keys, URLs com `key=...` e qualquer token longo que pareça
     * uma chave de qualquer mensagem de erro antes de ela vazar pro frontend
     * ou pros logs.
     */
    private function sanitizeError(string $message): string
    {
        // Remove URLs inteiras (mensagens de erro do cURL/Guzzle costumam citar a URL).
        $message = preg_replace('/https?:\/\/\S+/i', '[url-removida]', $message);
        // Remove qualquer ?key=... ou &key=... que tenha sobrado.
        $message = preg_replace('/[?&]key=[^\s&]+/i', '?key=[removida]', $message);
        // Remove a própria api_key caso ela apareça crua na mensagem.
        if ($this->apiKey !== '') {
            $message = str_replace($this->apiKey, '[api-key-removida]', $message);
        }
        return $message;
    }
}
