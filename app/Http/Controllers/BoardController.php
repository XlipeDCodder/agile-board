<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Item;
use App\Models\ItemBlockEvent;
use App\Models\User;
use App\Models\ItemStatusHistory;
use App\Events\ItemMoved;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function show(Request $request): Response
    {
        $columns = Column::orderBy('order')->get();
        
        $doneColumn = $columns->firstWhere('name', 'Feito');
        
        // Carrega os itens para as colunas que não são "Feito"
        $columns->where('id', '!=', $doneColumn ? $doneColumn->id : 0)
            ->load(['items' => function ($query) {
                $query->whereNull('parent_id')->with(['assignees', 'subtasks', 'comments.user', 'comments.attachments', 'project']);
            }]);
        
        // Carrega os itens para a coluna "Feito" com o filtro de tempo
        if ($doneColumn) {
            $doneColumn->load(['items' => function ($query) {
                $query->whereNull('parent_id')
                      ->where('items.updated_at', '>=', now()->subMinutes(30)) 
                      // E AQUI TAMBÉM
                      ->with(['assignees', 'subtasks', 'comments.user', 'comments.attachments', 'project']);
            }]);
        }

        return Inertia::render('Board/Index', [
            'columns' => $columns,
            'users' => User::all(['id', 'name']),
            'projects' => \App\Models\Project::where('status', 'open')->get(['id', 'name', 'due_date']),
        ]);
    }

    public function reorder(Request $request)
    {
        $request->validate(['columns' => ['required', 'array']]);

        $doneColumn = Column::where('name', 'Feito')->first();
        $doneColumnId = $doneColumn ? $doneColumn->id : null;

        $movedItems = [];

        try {
            DB::transaction(function () use ($request, $doneColumnId, &$movedItems) {
                foreach ($request->columns as $columnData) {
                    foreach ($columnData['items'] as $order => $itemId) {
                        $item = Item::find($itemId);
                        if (! $item) {
                            continue;
                        }

                        $oldColumnId = $item->column_id;
                        $newColumnId = $columnData['id'];

                        // Trava: cards concluídos não podem voltar para colunas anteriores.
                        if ($doneColumnId
                            && $oldColumnId == $doneColumnId
                            && $newColumnId != $doneColumnId) {
                            abort(422, "Cards concluídos não podem voltar para colunas anteriores. Use a opção 'Reabrir' no card para criar uma reabertura vinculada.");
                        }

                        $item->fill([
                            'column_id' => $newColumnId,
                            'order_in_column' => $order + 1,
                        ]);

                        if ($item->isDirty(['column_id', 'order_in_column'])) {
                            $item->save();
                            $movedItems[] = $item;
                        }

                        if ($oldColumnId != $item->column_id) {
                            ItemStatusHistory::create([
                                'item_id' => $item->id,
                                'column_id' => $item->column_id,
                            ]);
                        }

                        if ($doneColumnId && $item->column_id == $doneColumnId) {
                            $item->subtasks()->update(['completed_at' => now()]);

                            // Auto-desimpede ao concluir: cards em "Feito" não
                            // podem ficar impedidos. Mantém histórico via
                            // ItemBlockEvent pra timeline.
                            if ($item->is_blocked) {
                                ItemBlockEvent::create([
                                    'item_id' => $item->id,
                                    'event' => 'unblocked',
                                    'reason' => $item->blocked_reason,
                                    'blocked_by_item_id' => $item->blocked_by_item_id,
                                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                                    'created_at' => now(),
                                ]);
                                $item->update([
                                    'is_blocked' => false,
                                    'blocked_reason' => null,
                                    'blocked_by_item_id' => null,
                                    'blocked_at' => null,
                                ]);
                            }
                        }
                    }
                }
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        }

        foreach ($movedItems as $item) {
            broadcast(new ItemMoved($item))->toOthers();
        }

        return back();
    }
}
