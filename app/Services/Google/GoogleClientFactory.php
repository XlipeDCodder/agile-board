<?php

namespace App\Services\Google;

use App\Models\GoogleOAuthToken;
use App\Models\User;
use Carbon\Carbon;
use Google\Client;
use Illuminate\Support\Facades\Log;

class GoogleClientFactory
{
    /**
     * Scopes mínimos necessários para o MVP:
     * - drive.file: criar/editar APENAS arquivos criados pelo app (não dá acesso ao Drive inteiro)
     * - documents: criar/editar Google Docs criados pelo app
     * - spreadsheets: criar/editar Google Sheets criados pelo app
     * - userinfo.email: ler o email do usuário no callback pra validar domínio
     */
    public const SCOPES = [
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/documents',
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/userinfo.email',
    ];

    /**
     * Cliente "anônimo" pra uso no fluxo OAuth (gerar URL de consent,
     * trocar code por tokens).
     */
    public function unauthenticated(): Client
    {
        $client = $this->baseClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // garante refresh_token mesmo em reconexão
        $client->setIncludeGrantedScopes(true);
        $client->setScopes(self::SCOPES);
        return $client;
    }

    /**
     * Cliente autenticado pra um user. Carrega tokens do banco, registra
     * callback de refresh automático que persiste novo access_token.
     */
    public function forUser(User $user): Client
    {
        $token = $user->googleToken;
        if (! $token) {
            throw new GoogleAuthException("Usuário {$user->email} não conectou o Google Workspace.");
        }

        $client = $this->baseClient();
        $client->setScopes(self::SCOPES);
        $client->setAccessToken([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in' => max(0, $token->expires_at ? $token->expires_at->diffInSeconds(Carbon::now(), false) * -1 : 0),
            'created' => $token->updated_at?->timestamp ?? time(),
        ]);

        // Refresh automático: se expirou, troca o refresh_token por um novo
        // access_token e persiste no banco.
        if ($client->isAccessTokenExpired()) {
            if (! $token->refresh_token) {
                throw new GoogleAuthException("Token Google expirado e sem refresh_token. Reconecte sua conta Google.");
            }
            try {
                $newToken = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
                if (isset($newToken['error'])) {
                    throw new GoogleAuthException("Falha ao renovar token Google: ".$newToken['error']);
                }
                $token->update([
                    'access_token' => $newToken['access_token'],
                    'expires_at' => isset($newToken['expires_in'])
                        ? Carbon::now()->addSeconds((int) $newToken['expires_in'])
                        : null,
                    // refresh_token geralmente não vem na renovação; só atualiza se vier.
                    'refresh_token' => $newToken['refresh_token'] ?? $token->refresh_token,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Falha ao renovar token Google', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                throw new GoogleAuthException("Não foi possível renovar a sessão do Google. Reconecte sua conta.");
            }
        }

        return $client;
    }

    /**
     * Troca um auth code (vindo do callback OAuth) por tokens.
     *
     * @return array{tokens: array, email: string}
     */
    public function fromAuthCode(string $code): array
    {
        $client = $this->unauthenticated();
        $tokens = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($tokens['error'])) {
            throw new GoogleAuthException("Erro ao trocar code por token: ".$tokens['error']);
        }

        // Busca email via userinfo endpoint.
        $client->setAccessToken($tokens);
        $oauth = new \Google\Service\Oauth2($client);
        $email = $oauth->userinfo->get()->getEmail();
        if (! $email) {
            throw new GoogleAuthException("Não foi possível obter o email da conta Google.");
        }

        return ['tokens' => $tokens, 'email' => $email];
    }

    private function baseClient(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        return $client;
    }
}
