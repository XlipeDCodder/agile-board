<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Item;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class BacklogController extends Controller
{
    /**
     * Exibe a lista de itens do backlog.
     */
    public function index(): Response
    {
        
        $doneColumnId = Column::where('name', 'Feito')->value('id');


        $items = Item::query()

            ->whereNull('parent_id')
            // Exclui os itens da coluna "Feito", se ela existir
            ->when($doneColumnId, function ($query) use ($doneColumnId) {
                $query->where('column_id', '!=', $doneColumnId);
            })
            // Carrega os relacionamentos necessários para a exibição
            ->with(['assignees', 'column', 'subtasks', 'comments.user'])
            // Ordena pelos mais recentes primeiro
            ->latest()
            // Pagina os resultados para melhor performance
            ->paginate(15);

        return Inertia::render('Backlog/Index', [
            'items' => $items,
            // Precisamos dos usuários para o modal de edição
            'users' => User::all(['id', 'name']),
        ]);
    }
}
