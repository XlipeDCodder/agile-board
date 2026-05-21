<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleOAuthToken;
use App\Services\Google\GoogleAuthException;
use App\Services\Google\GoogleClientFactory;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    public function __construct(private GoogleClientFactory $clientFactory) {}

    public function connect(Request $request): RedirectResponse
    {
        $client = $this->clientFactory->unauthenticated();

        // CSRF token — guardamos na sessão e revalidamos no callback.
        $state = Str::random(32);
        $request->session()->put('google_oauth_state', $state);
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        // Erro retornado pelo Google (ex: usuário negou consent).
        if ($request->has('error')) {
            return redirect()
                ->route('admin.bot-config.index')
                ->with('google_error', 'Conexão com o Google cancelada: '.$request->input('error'));
        }

        $expectedState = $request->session()->pull('google_oauth_state');
        if (! $expectedState || $request->input('state') !== $expectedState) {
            return redirect()
                ->route('admin.bot-config.index')
                ->with('google_error', 'Estado OAuth inválido. Tente conectar novamente.');
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect()
                ->route('admin.bot-config.index')
                ->with('google_error', 'Não recebemos o código de autorização do Google.');
        }

        try {
            $result = $this->clientFactory->fromAuthCode($code);
        } catch (GoogleAuthException $e) {
            Log::warning('Falha ao trocar code Google', ['error' => $e->getMessage()]);
            return redirect()
                ->route('admin.bot-config.index')
                ->with('google_error', 'Falha ao validar o consentimento Google. Tente novamente.');
        }

        $email = $result['email'];
        $tokens = $result['tokens'];

        // Restrição de domínio.
        $allowedDomain = config('services.google.allowed_domain');
        if ($allowedDomain && ! str_ends_with(strtolower($email), '@'.strtolower($allowedDomain))) {
            return redirect()
                ->route('admin.bot-config.index')
                ->with('google_error', "Apenas contas @{$allowedDomain} podem conectar. O email autenticado foi {$email}.");
        }

        // Upsert: 1 token por user.
        $user = Auth::user();
        GoogleOAuthToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => isset($tokens['expires_in'])
                    ? Carbon::now()->addSeconds((int) $tokens['expires_in'])
                    : null,
                'scopes' => is_array($tokens['scope'] ?? null)
                    ? implode(' ', $tokens['scope'])
                    : ($tokens['scope'] ?? null),
                'google_email' => $email,
            ],
        );

        return redirect()
            ->route('admin.bot-config.index')
            ->with('google_success', "Conectado ao Google como {$email}.");
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $user->googleToken?->delete();

        return redirect()
            ->route('admin.bot-config.index')
            ->with('google_success', 'Conta Google desconectada.');
    }
}
