<?php

namespace App\Http\Controllers;

use App\Models\Column;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ColumnController extends Controller
{
    /**
     * Cria uma nova coluna no quadro.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Column::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:columns,name',
        ]);


        Column::create([
            'name' => $validated['name'],

            'order' => Column::max('order') + 1,
        ]);

        return back()->with('success', 'Coluna criada com sucesso!');
    }

    /**
     * Reordena as colunas do quadro.
     */
    public function reorder(Request $request)
    {
 
        $this->authorize('update', Column::class);


        $request->validate([
            'columns' => ['required', 'array'],
            'columns.*' => ['integer', 'exists:columns,id'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->columns as $order => $columnId) {
                Column::where('id', $columnId)->update(['order' => $order + 1]);
            }
        });

        return back()->with('success', 'Ordem das colunas atualizada!');
    }
    /**
     * Atualiza o nome da coluna.
     */
    public function update(Request $request, Column $column)
    {
        $this->authorize('update', Column::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:columns,name,' . $column->id,
        ]);

        $column->update(['name' => $validated['name']]);

        return back()->with('success', 'Coluna atualizada com sucesso!');
    }

    /**
     * Remove uma coluna.
     */
    public function destroy(Column $column)
    {
        $this->authorize('delete', Column::class);

        if ($column->items()->count() > 0) {
            return back()->withErrors(['error' => 'Não é possível excluir uma coluna que contém cards. Mova os cards primeiro.']);
        }

        $column->delete();

        return back()->with('success', 'Coluna removida com sucesso!');
    }
}
