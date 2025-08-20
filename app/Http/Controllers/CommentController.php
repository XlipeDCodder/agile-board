<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    /**
     * Guarda um novo comentário no banco de dados.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'body' => 'required|string',
        ]);

        Comment::create([
            'item_id' => $validated['item_id'],
            'body' => $validated['body'],
            'user_id' => Auth::id(),
        ]);

        return back(303);
    }
}
