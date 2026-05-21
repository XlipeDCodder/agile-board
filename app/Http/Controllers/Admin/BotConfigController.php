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
    public function index(Request $request): Response
    {
        $config = BotConfig::active();
        $user = $request->user();
        $token = $user?->googleToken;
        $allowedDomain = config('services.google.allowed_domain');
        $oauthConfigured = (bool) config('services.google.client_id') && (bool) config('services.google.client_secret');

        return Inertia::render('Admin/BotConfig/Index', [
            'config' => $config ? [
                'id' => $config->id,
                'provider' => $config->provider,
                'model' => $config->model,
                'has_api_key' => true,
                'updated_at' => $config->updated_at?->toIso8601String(),
            ] : null,
            'googleConnection' => $token ? [
                'google_email' => $token->google_email,
                'expires_at' => $token->expires_at?->toIso8601String(),
            ] : null,
            'googleOAuthConfigured' => $oauthConfigured,
            'googleAllowedDomain' => $allowedDomain,
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
            $response = $provider->chat(
                'Você é um teste de conexão. Responda apenas: OK',
                [['role' => 'user', 'content' => 'ping']],
            );
            $reply = $response->text ?? '(resposta sem texto)';

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
