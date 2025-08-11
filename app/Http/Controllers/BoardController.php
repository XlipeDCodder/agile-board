<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function show(Request $request): Response
    {
        $columns = Column::orderBy('order')
            ->with(['items' => function ($query) {
                $query->whereNull('parent_id')
                      ->with(['assignee', 'subtasks']);
            }])
            ->get();

        return Inertia::render('Board/Index', [
            'columns' => $columns,
            'users' => User::all(['id', 'name']),
        ]);
    }

    public function reorder(Request $request)
    {
        $request->validate(['columns' => ['required', 'array']]);

        // Busca a coluna "Feito" uma única vez para referência
        $doneColumn = Column::where('name', 'Feito')->first();
        $doneColumnId = $doneColumn ? $doneColumn->id : null;

        DB::transaction(function () use ($request, $doneColumnId) {
            foreach ($request->columns as $columnData) {
                foreach ($columnData['items'] as $order => $itemId) {
                    $item = Item::find($itemId);
                    if ($item) {
                        $item->update([
                            'column_id' => $columnData['id'],
                            'order_in_column' => $order + 1,
                        ]);

                        // AQUI ESTÁ A NOVA LÓGICA
                        // Se o item foi movido para a coluna "Feito"...
                        if ($doneColumnId && $item->column_id == $doneColumnId) {
                            // ...marca todas as suas subtarefas como concluídas.
                            $item->subtasks()->update(['completed_at' => now()]);
                        }
                    }
                }
            }
        });

        return back();
    }
}
