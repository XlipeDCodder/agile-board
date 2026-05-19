<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Bot\BotException;
use App\Services\Bot\BotFactory;
use App\Services\CollaboratorContextBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IcarusController extends Controller
{
    public function chat(
        Request $request,
        User $user,
        BotFactory $factory,
        CollaboratorContextBuilder $contextBuilder,
    ): JsonResponse {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:30'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $context = $contextBuilder->build($user);
            $systemPrompt = $this->buildSystemPrompt($user, $context);
            $provider = $factory->fromActiveConfig();
            $reply = $provider->chat($systemPrompt, $validated['messages']);

            return response()->json([
                'reply' => $reply,
            ]);
        } catch (BotException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function buildSystemPrompt(User $user, array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return <<<PROMPT
Você é Icarus, assistente de análise de produtividade do sistema B-Agile. Você só pode falar sobre o colaborador "{$user->name}" (id {$user->id}) com base nos dados fornecidos abaixo.

Regras absolutas:
1. Nunca invente dados. Se a informação não está no contexto, diga que não tem dados suficientes para responder.
2. Recuse educadamente perguntas sobre outros colaboradores ou assuntos não relacionados a este colaborador.
3. Responda sempre em português brasileiro, de forma clara e objetiva, para um gestor não-técnico.
4. Use datas, números e nomes específicos quando responder. Cite cards pelo "#id" quando relevante.
5. Quando o gestor pedir estimativas (ex: "quando o card X será concluído?"), use o histórico de transições e tempo médio em cada coluna como base, e deixe claro que é uma estimativa baseada no histórico, não uma promessa.
6. Mantenha respostas concisas. Use listas e marcadores quando ajudar a leitura.

DADOS DO COLABORADOR (snapshot atual):
{$json}
PROMPT;
    }
}
