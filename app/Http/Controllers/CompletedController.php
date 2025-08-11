<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Item;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class CompletedController extends Controller
{
    /**
     * Exibe a lista de itens concluídos.
     */
    public function index(): Response
    {
        // Encontra o ID da coluna "Feito".
        $doneColumnId = Column::where('name', 'Feito')->value('id');

        $items = Item::query()
            // Apenas tarefas "pai"
            ->whereNull('parent_id')
            // Apenas itens da coluna "Feito"
            ->when($doneColumnId, function ($query) use ($doneColumnId) {
                $query->where('column_id', $doneColumnId);
            })
            ->with(['assignee', 'column'])
            // Ordena pelos mais recentemente concluídos primeiro
            ->latest('updated_at')
            ->paginate(15);

        return Inertia::render('Completed/Index', [
            'items' => $items,
            'users' => User::all(['id', 'name']),
        ]);
    }
}
