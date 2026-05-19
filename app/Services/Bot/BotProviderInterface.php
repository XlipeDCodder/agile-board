<?php

namespace App\Services\Bot;

interface BotProviderInterface
{
    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     *   Mensagens da conversa. role: 'user' ou 'assistant'.
     */
    public function chat(string $systemPrompt, array $messages): string;
}
