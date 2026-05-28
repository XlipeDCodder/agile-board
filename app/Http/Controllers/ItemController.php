<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Importa a trait

use App\Events\ItemCreated;
use App\Events\ItemUpdated;
use App\Notifications\ItemAssignedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class ItemController extends Controller
{
    use AuthorizesRequests; // Usa a trait

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:task,bug,reabertura',
            'priority' => 'required|in:Baixa,Média,Alta,Crítica',
            'due_date' => 'nullable|date',
            'column_id' => 'required|exists:columns,id',
            'project_id' => 'required|exists:projects,id',
            'estimation' => 'nullable|numeric|min:0|max:20',
            'predicted_value' => 'nullable|integer|min:1|max:9999',
            'predicted_unit' => 'nullable|in:minutes,hours,days|required_with:predicted_value',
            'reopened_from_id' => 'nullable|integer|exists:items,id',
            'justification' => 'nullable|string|max:5000',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'integer|exists:users,id',
        ]);

        // Regras específicas para reabertura
        if ($validated['type'] === 'reabertura') {
            if (empty($validated['reopened_from_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reopened_from_id' => 'Reaberturas precisam apontar para um card original.',
                ]);
            }
            if (empty($validated['justification'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'justification' => 'A justificativa é obrigatória para reaberturas.',
                ]);
            }
            $original = Item::find($validated['reopened_from_id']);
            $doneColumnId = \App\Models\Column::where('name', 'Feito')->value('id');
            if (! $original || $original->column_id != $doneColumnId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reopened_from_id' => 'Só é possível reabrir cards que estão na coluna "Feito".',
                ]);
            }
        } else {
            // Não-reaberturas não devem carregar esses campos.
            $validated['reopened_from_id'] = null;
            $validated['justification'] = null;
        }

        // Remove os IDs dos responsáveis dos dados principais para evitar erros
        $assigneeIds = $validated['assignee_ids'] ?? [];
        unset($validated['assignee_ids']);

        $validated['creator_id'] = Auth::id();
        $validated['order_in_column'] = Item::where('column_id', $validated['column_id'])->max('order_in_column') + 1;

        $item = Item::create($validated);

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
        $this->authorize('update', $item);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:task,bug,reabertura',
            'priority' => 'required|in:Baixa,Média,Alta,Crítica',
            'due_date' => 'nullable|date',
            'column_id' => 'required|exists:columns,id',
            'project_id' => 'nullable|exists:projects,id',
            'estimation' => 'nullable|numeric|min:0|max:20',
            'predicted_value' => 'nullable|integer|min:1|max:9999',
            'predicted_unit' => 'nullable|in:minutes,hours,days|required_with:predicted_value',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'integer|exists:users,id',
        ]);

        // Campos imutáveis: tipo, reopened_from_id e justification não podem ser
        // alterados depois da criação. Se o cliente mandar valores diferentes,
        // ignoramos e reportamos via validação.
        if ($validated['type'] !== $item->type) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'type' => 'O tipo do card não pode ser alterado depois de criado.',
            ]);
        }
        unset($validated['type']); // já confirmado igual; não reescreve.

        $assigneeIds = $validated['assignee_ids'] ?? null;
        unset($validated['assignee_ids']);

        // Captura assignees ANTES do sync pra detectar quem foi recém-adicionado.
        $previousAssigneeIds = $item->assignees()->pluck('users.id')->all();

        $item->update($validated);

        if ($assigneeIds !== null) {
            $this->authorize('assignUsers', $item);
            $item->assignees()->sync($assigneeIds);

            // Notifica APENAS os assignees novos (diff pós-sync).
            // Quem já estava no card não recebe notificação repetida.
            $newAssigneeIds = array_diff($assigneeIds, $previousAssigneeIds);
            // Não notifica o próprio editor se ele se auto-adicionou.
            $newAssigneeIds = array_diff($newAssigneeIds, [Auth::id()]);

            if (! empty($newAssigneeIds)) {
                $newAssignees = User::whereIn('id', $newAssigneeIds)->get();
                Notification::send($newAssignees, new ItemAssignedNotification($item->fresh()));
            }
        }

        // Dispara broadcast pra todos os boards abertos atualizarem o card
        // (real-time pra edição igual já temos pra movimentação).
        // toOthers() omite quem fez a edição (já tem o estado novo via Inertia).
        broadcast(new ItemUpdated($item->fresh()))->toOthers();

        return back();
    }
}
