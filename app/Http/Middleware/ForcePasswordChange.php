<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quando o admin cria/reseta um usuário com senha temporária, must_change_password
 * fica true. Esse middleware força o usuário pra página de troca obrigatória
 * antes que ele consiga acessar qualquer outra rota autenticada.
 *
 * Permite acesso a: a própria página de troca, o POST de troca, e logout.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $allowedRouteNames = [
            'password.change-required',
            'password.change-required.update',
            'logout',
        ];
        $currentRoute = $request->route()?->getName();
        if (in_array($currentRoute, $allowedRouteNames, true)) {
            return $next($request);
        }

        return redirect()->route('password.change-required');
    }
}
