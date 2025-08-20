<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ItemPolicy
{
    /**
     * Dá acesso total a administradores antes de qualquer outra verificação.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->is_admin) {
            return true;
        }

        return null;
    }

    /**
     * Determina se o utilizador pode atribuir responsáveis a um item.
     */
    public function assignUsers(User $user, Item $item): bool
    {
        // Regra 1: O criador do item pode atribuir.
        if ($user->id === $item->creator_id) {
            return true;
        }

        // Regra 2: Alguém que já é responsável pelo item pode atribuir.
        if ($item->assignees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Se nenhuma das regras acima for atendida, nega a permissão.
        return false;
    }

    /**
     * Determina se o utilizador pode atualizar um item.
     * (Vamos adicionar uma regra básica aqui para segurança geral)
     */
    public function update(User $user, Item $item): bool
    {
        // Apenas o criador ou um responsável pode editar os detalhes de um item.
        return $user->id === $item->creator_id || $item->assignees()->where('user_id', $user->id)->exists();
    }

    /**
     * Determina se o utilizador pode apagar um item.
     * (Apenas o criador pode apagar)
     */
    public function delete(User $user, Item $item): bool
    {
        return $user->id === $item->creator_id;
    }
}
