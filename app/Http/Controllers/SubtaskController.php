<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubtaskController extends Controller
{
    /**
     * Cria uma nova subtarefa.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'required|exists:items,id',
        ]);

        $parentItem = Item::find($validated['parent_id']);

        Item::create([
            'title' => $validated['title'],
            'parent_id' => $parentItem->id,
            'column_id' => $parentItem->column_id,
            'creator_id' => Auth::id(),
            'type' => 'task',
        ]);

        // AQUI ESTÁ A MUDANÇA: Retornando um redirect simples,
        // exatamente como o ItemController faz.
        return back();
    }

    /**
     * Atualiza o status de conclusão de uma subtarefa.
     */
    public function update(Request $request, Item $item)
    {
        if ($item->parent_id === null) {
            abort(403, 'Apenas subtarefas podem ser marcadas como concluídas desta forma.');
        }

        $item->update([
            'completed_at' => $item->completed_at ? null : now(),
        ]);

        // AQUI ESTÁ A MUDANÇA: Retornando um redirect simples.
        return back();
    }
}
