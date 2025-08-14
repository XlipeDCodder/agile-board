<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Item;
use App\Models\User;
use App\Models\ItemStatusHistory;
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
        
        $columns->where('id', '!=', $doneColumn ? $doneColumn->id : 0)
            ->load(['items' => function ($query) {
                $query->whereNull('parent_id')->with(['assignee', 'subtasks']);
            }]);
        
        if ($doneColumn) {
            $doneColumn->load(['items' => function ($query) {
                $query->whereNull('parent_id')
                      ->where('items.updated_at', '>=', now()->subHours(2)) 
                      ->with(['assignee', 'subtasks']);
            }]);
        }

        return Inertia::render('Board/Index', [
            'columns' => $columns,
            'users' => User::all(['id', 'name']),
        ]);
    }

    public function reorder(Request $request)
    {
        $request->validate(['columns' => ['required', 'array']]);

        $doneColumn = Column::where('name', 'Feito')->first();
        $doneColumnId = $doneColumn ? $doneColumn->id : null;

        DB::transaction(function () use ($request, $doneColumnId) {
            foreach ($request->columns as $columnData) {
                foreach ($columnData['items'] as $order => $itemId) {
                    $item = Item::find($itemId);
                    if ($item) {
                        
                        $oldColumnId = $item->column_id;

                        $item->update([
                            'column_id' => $columnData['id'],
                            'order_in_column' => $order + 1,
                        ]);

                        
                        if ($oldColumnId != $item->column_id) {
                            ItemStatusHistory::create([
                                'item_id' => $item->id,
                                'column_id' => $item->column_id,
                            ]);
                        }

                        if ($doneColumnId && $item->column_id == $doneColumnId) {
                            $item->subtasks()->update(['completed_at' => now()]);
                        }
                    }
                }
            }
        });

        return back();
    }
}
