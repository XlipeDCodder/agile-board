<?php

namespace App\Services;

/**
 * Limpa sintaxe markdown que não interessa pro LLM (Icarus → Gemini).
 *
 * O caso crítico é imagem: `![alt](/storage/x.png)` ou
 * `![alt](data:image/png;base64,...gigante...)`. Mandar a URL/base64 pro
 * modelo desperdiça tokens e não agrega — então trocamos por `[imagem: alt]`,
 * preservando só o texto alternativo (que pode ter contexto útil tipo
 * "print do erro").
 */
class MarkdownStripper
{
    public function stripForLlm(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        // ![alt](url) → [imagem: alt]  (ou [imagem] se alt vazio)
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\([^\)]*\)/',
            function ($m) {
                $alt = trim($m[1]);
                return $alt !== '' ? "[imagem: {$alt}]" : '[imagem]';
            },
            $text,
        );

        // [texto](url) → texto  (mantém o rótulo, remove a URL)
        $text = preg_replace('/\[([^\]]+)\]\([^\)]*\)/', '$1', $text);

        return $text;
    }
}
