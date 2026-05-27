<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::withTrashed()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_admin', 'must_change_password', 'created_at', 'deleted_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_admin' => $u->is_admin,
                'must_change_password' => $u->must_change_password,
                'created_at' => $u->created_at?->toIso8601String(),
                'deleted_at' => $u->deleted_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'registrationEnabled' => SystemSetting::getBool('registration_enabled', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'is_admin' => ['boolean'],
        ]);

        // Se admin não preencher senha, geramos uma temporária. Em ambos
        // os casos must_change_password=true — força o user a definir uma
        // senha própria no primeiro login.
        $tempPassword = $validated['password'] ?? Str::random(12);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($tempPassword),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
            'must_change_password' => true,
        ]);

        return back()->with([
            'success' => "Usuário {$validated['name']} criado.",
            'temp_password' => $tempPassword, // exibido UMA vez no front pro admin copiar
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardSelf($user, 'editar');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'is_admin' => ['boolean'],
        ]);

        $newIsAdmin = (bool) ($validated['is_admin'] ?? false);

        // Guarda: não rebaixar o último admin do sistema, senão ninguém
        // mais consegue acessar /admin/*.
        if ($user->is_admin && ! $newIsAdmin && $this->countActiveAdmins() <= 1) {
            return back()->withErrors([
                'is_admin' => 'Não é possível rebaixar o último admin do sistema.',
            ]);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => $newIsAdmin,
        ]);

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->guardSelf($user, 'excluir');

        if ($user->is_admin && $this->countActiveAdmins() <= 1) {
            return back()->withErrors([
                'general' => 'Não é possível excluir o último admin do sistema.',
            ]);
        }

        $user->delete(); // soft delete

        return back()->with('success', 'Usuário excluído.');
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return back()->with('success', "Usuário {$user->name} restaurado.");
    }

    public function resetPassword(User $user): RedirectResponse
    {
        // Reset a senha pra uma temporária e força troca no próximo login.
        // Útil quando o user esquece a senha — admin gera uma nova e passa.
        $tempPassword = Str::random(12);
        $user->update([
            'password' => Hash::make($tempPassword),
            'must_change_password' => true,
        ]);

        return back()->with([
            'success' => "Nova senha temporária gerada para {$user->name}.",
            'temp_password' => $tempPassword,
        ]);
    }

    private function guardSelf(User $user, string $action): void
    {
        if ($user->id === Auth::id()) {
            abort(403, "Você não pode {$action} a si mesmo. Peça pra outro admin.");
        }
    }

    private function countActiveAdmins(): int
    {
        return User::where('is_admin', true)->whereNull('deleted_at')->count();
    }
}
