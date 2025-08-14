<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemStatusHistory; // 1. Importe o novo model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:task,bug',
            'priority' => 'required|in:Baixa,Média,Alta,Crítica',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'column_id' => 'required|exists:columns,id',
            'estimation' => 'nullable|numeric|min:0|max:20',
        ]);

        $validated['creator_id'] = Auth::id();
        $validated['order_in_column'] = Item::where('column_id', $validated['column_id'])->max('order_in_column') + 1;

        $item = Item::create($validated);

        
        ItemStatusHistory::create([
            'item_id' => $item->id,
            'column_id' => $item->column_id,
        ]);

        return back();
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:task,bug',
            'priority' => 'required|in:Baixa,Média,Alta,Crítica',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'column_id' => 'required|exists:columns,id',
            'estimation' => 'nullable|numeric|min:0|max:20',
        ]);

        $item->update($validated);

        return back();
    }
}
