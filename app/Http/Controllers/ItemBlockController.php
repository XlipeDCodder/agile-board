<?php

namespace App\Http\Controllers;

use App\Events\ItemUpdated;
use App\Models\Item;
use App\Models\ItemBlockEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ItemBlockController extends Controller
{
    public function block(Request $request, Item $item)
    {
        if ($item->parent_id) {
            abort(422, 'Subtarefas não suportam marcação de impedimento.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'blocked_by_item_id' => [
                'nullable', 'integer', 'exists:items,id',
                'different:'.$item->id,
            ],
        ]);

        DB::transaction(function () use ($item, $validated) {
            ItemBlockEvent::create([
                'item_id' => $item->id,
                'event' => 'blocked',
                'reason' => $validated['reason'],
                'blocked_by_item_id' => $validated['blocked_by_item_id'] ?? null,
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);

            $item->update([
                'is_blocked' => true,
                'blocked_reason' => $validated['reason'],
                'blocked_by_item_id' => $validated['blocked_by_item_id'] ?? null,
                'blocked_at' => now(),
            ]);
        });

        broadcast(new ItemUpdated($item->fresh()))->toOthers();

        return back();
    }

    public function unblock(Request $request, Item $item)
    {
        if (! $item->is_blocked) {
            return back();
        }

        DB::transaction(function () use ($item) {
            ItemBlockEvent::create([
                'item_id' => $item->id,
                'event' => 'unblocked',
                'reason' => $item->blocked_reason, // preserva motivo do bloqueio anterior pro histórico
                'blocked_by_item_id' => $item->blocked_by_item_id,
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);

            $item->update([
                'is_blocked' => false,
                'blocked_reason' => null,
                'blocked_by_item_id' => null,
                'blocked_at' => null,
            ]);
        });

        broadcast(new ItemUpdated($item->fresh()))->toOthers();

        return back();
    }
}
