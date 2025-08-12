<?php

namespace App\Policies;

use App\Models\Column;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ColumnPolicy
{
    /**
     * Este método é executado ANTES de qualquer outra regra na policy.
     * É perfeito para dar acesso total a administradores.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->is_admin) {
            return true; // Se for admin, permite a ação e não verifica mais nada.
        }

        return null; // Se não for admin, continua para as outras regras.
    }

    /**
     * Determina se o utilizador pode ver qualquer coluna.
     * (Neste caso, todos podem ver, mas a regra está aqui para consistência).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determina se o utilizador pode criar novas colunas.
     * (Apenas admins poderão, por causa do método 'before').
     */
    public function create(User $user): bool
    {
        return false; // Um utilizador normal não pode criar colunas.
    }

    /**
     * Determina se o utilizador pode atualizar uma coluna.
     * (Apenas admins poderão).
     */
    public function update(User $user, Column $column): bool
    {
        return false; // Um utilizador normal não pode atualizar colunas.
    }

    /**
     * Determina se o utilizador pode apagar uma coluna.
     * (Apenas admins poderão).
     */
    public function delete(User $user, Column $column): bool
    {
        return false; // Um utilizador normal não pode apagar colunas.
    }
}
