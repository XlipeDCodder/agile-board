<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela de troca obrigatória de senha. Disparada pelo middleware
 * ForcePasswordChange quando o usuário ainda tem must_change_password=true
 * (após admin criar conta ou resetar senha).
 */
class ChangePasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ChangePasswordRequired');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Senha atualizada.');
    }
}
