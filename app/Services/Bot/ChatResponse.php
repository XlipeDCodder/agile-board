<?php

namespace App\Services\Bot;

/**
 * Resposta de um turno do bot. Pode ser texto final OU um pedido de execução
 * de função (function calling). O caller é responsável por decidir: se for
 * função, executar e chamar de novo o chat() com o functionResponse no
 * histórico; se for texto, devolver pro usuário.
 */
class ChatResponse
{
    public function __construct(
        public readonly ?string $text = null,
        public readonly ?array $functionCall = null, // ['name' => string, 'args' => array]
    ) {}

    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    public static function functionCall(string $name, array $args): self
    {
        return new self(functionCall: ['name' => $name, 'args' => $args]);
    }

    public function isText(): bool
    {
        return $this->text !== null;
    }

    public function isFunctionCall(): bool
    {
        return $this->functionCall !== null;
    }
}
