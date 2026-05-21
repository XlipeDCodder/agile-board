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
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    public function __construct(private GoogleClientFactory $clientFactory) {}

    /**
     * Nome do cookie que carrega o state OAuth. Cookie em vez de session
     * porque a sessão Laravel é regenerada no login — se a sessão expira
     * durante o consent do Google, o user precisa logar de novo e o state
     * em sessão some. Cookie sobrevive a essa regeneração.
     */
    private const STATE_COOKIE = 'google_oauth_state';

    public function connect(Request $request): RedirectResponse
    {
        $client = $this->clientFactory->unauthenticated();
        $state = Str::random(32);
        $client->setState($state);

        // Cookie HTTP-only de 10 min — tempo suficiente pro consent.
        // O cookie é cifrado pelo EncryptCookies middleware do Laravel.
        return redirect()->away($client->createAuthUrl())
            ->withCookie(cookie(self::STATE_COOKIE, $state, 10));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()
                ->route('admin.bot-config.index')
                ->with('google_error', 'Conexão com o Google cancelada: '.$request->input('error'))
                ->withCookie(Cookie::forget(self::STATE_COOKIE));
        }

        $expectedState = $request->cookie(self::STATE_COOKIE);
        if (! $expectedState || $request->input('state') !== $expectedState) {
            return redirect()
                ->route('admin.bot-config.index')
                ->with('google_error', 'Estado OAuth inválido (cookie expirou ou ausente). Tente conectar novamente.')
                ->withCookie(Cookie::forget(self::STATE_COOKIE));
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
