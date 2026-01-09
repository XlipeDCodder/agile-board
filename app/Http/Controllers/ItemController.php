<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Importa a trait

use App\Events\ItemCreated;

class ItemController extends Controller
{
    use AuthorizesRequests; // Usa a trait

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:task,bug',
            'priority' => 'required|in:Baixa,Média,Alta,Crítica',
            'due_date' => 'nullable|date',
            'column_id' => 'required|exists:columns,id',
            'project_id' => 'nullable|exists:projects,id',
            'estimation' => 'nullable|numeric|min:0|max:20',
            // 1. A validação agora espera um array de IDs de responsáveis
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'integer|exists:users,id',
        ]);

        // Remove os IDs dos responsáveis dos dados principais para evitar erros
        $assigneeIds = $validated['assignee_ids'] ?? [];
        unset($validated['assignee_ids']);

        $validated['creator_id'] = Auth::id();
        $validated['order_in_column'] = Item::where('column_id', $validated['column_id'])->max('order_in_column') + 1;

        $item = Item::create($validated);

        // 2. Sincroniza os responsáveis na tabela pivot
        if (!empty($assigneeIds)) {
            $item->assignees()->sync($assigneeIds);
        }

        ItemStatusHistory::create([
            'item_id' => $item->id,
            'column_id' => $item->column_id,
        ]);

        event(new ItemCreated($item));
        event(new \App\Events\ItemAssigned($item));

        return back();
    }

    public function update(Request $request, Item $item)
    {
        // 3. Autorização: Verifica se o utilizador pode ATUALIZAR os detalhes do item.
        $this->authorize('update', $item);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:task,bug',
            'priority' => 'required|in:Baixa,Média,Alta,Crítica',
            'due_date' => 'nullable|date',
            'column_id' => 'required|exists:columns,id',
            'project_id' => 'nullable|exists:projects,id',
            'estimation' => 'nullable|numeric|min:0|max:20',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'integer|exists:users,id',
        ]);

        $assigneeIds = $validated['assignee_ids'] ?? null;
        unset($validated['assignee_ids']);

        $item->update($validated);

        // 4. Se a lista de responsáveis foi enviada, verifica a permissão específica para os atribuir.
        if ($assigneeIds !== null) {
            $this->authorize('assignUsers', $item);
            $item->assignees()->sync($assigneeIds);
        }

        return back();
    }
}
