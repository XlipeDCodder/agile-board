<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia GET/POST /register quando o admin desabilita o cadastro público.
 * Aplica-se mesmo se alguém digitar a URL direto — defesa server-side
 * complementar ao botão escondido na Welcome.
 */
class BlockPublicRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SystemSetting::getBool('registration_enabled', false)) {
            abort(404, 'Cadastro público está desabilitado neste sistema.');
        }
        return $next($request);
    }
}
