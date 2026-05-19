<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotConfig;
use App\Services\Bot\BotException;
use App\Services\Bot\BotFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotConfigController extends Controller
{
    public function index(): Response
    {
        $config = BotConfig::active();

        return Inertia::render('Admin/BotConfig/Index', [
            'config' => $config ? [
                'id' => $config->id,
                'provider' => $config->provider,
                'model' => $config->model,
                'has_api_key' => true,
                'updated_at' => $config->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:gemini'],
            'model' => ['required', 'string', 'max:120'],
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        BotConfig::query()->update(['is_active' => false]);

        BotConfig::create([
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'api_key' => $validated['api_key'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Configuração salva.');
    }

    public function test(Request $request, BotFactory $factory): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:gemini'],
            'model' => ['required', 'string', 'max:120'],
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        try {
            $provider = $factory->make($validated['provider'], $validated['api_key'], $validated['model']);
            $reply = $provider->chat(
                'Você é um teste de conexão. Responda apenas: OK',
                [['role' => 'user', 'content' => 'ping']],
            );

            return response()->json([
                'ok' => true,
                'sample' => mb_substr(trim($reply), 0, 200),
            ]);
        } catch (BotException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
