<?php

namespace App\Services\Bot;

interface BotProviderInterface
{
    /**
     * Executa um turno de chat com suporte opcional a function calling.
     *
     * @param  string  $systemPrompt
     * @param  array<int,array<string,mixed>>  $messages
     *   Cada item pode ser:
     *     - texto:            {role: 'user'|'assistant', content: string}
     *     - chamada anterior: {role: 'assistant', function_call: {name, args}}
     *     - resposta de tool: {role: 'function', name: string, response: array}
     * @param  array<int,array<string,mixed>>  $tools
     *   Declarações de funções no formato Gemini function_declarations.
     *   Vazio = chat normal sem tools.
     */
    public function chat(string $systemPrompt, array $messages, array $tools = []): ChatResponse;
}
