<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class TimeEntryController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $entries = TimeEntry::where('user_id', $user->id)
            ->with('item.project')
            ->get();

        // Apontamento só rola contra CARDS (parent_id IS NULL). Subtarefas
        // não são unidade de trabalho independente — o tempo gasto numa
        // subtarefa é apontado no card pai. Sem o whereNull, a lista mostrava
        // subtarefas como se fossem cards e o filtro de coluna no front
        // ("Em Aberto" vs "Feito") trazia subtarefa do card já concluído
        // como "em aberto", porque a subtarefa mantém o column_id de quando
        // foi criada.
        $items = Item::whereNull('parent_id')
            ->where(function ($q) use ($user) {
                $q->whereHas('assignees', fn ($a) => $a->where('users.id', $user->id))
                    ->orWhere('creator_id', $user->id);
            })
            ->with(['project', 'column'])
            ->get();

        return Inertia::render('TimeEntries/Index', [
            'entries' => $entries,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'date' => 'required|date',
            'hours' => 'required|integer|min:0',
            'minutes' => 'required|integer|min:0|max:59',
        ]);

        // Defesa em profundidade: o frontend só lista cards, mas se alguém
        // burlar via Postman/cURL e mandar item_id de subtarefa, rejeita aqui.
        $item = Item::find($request->item_id);
        if ($item && $item->parent_id !== null) {
            return back()->withErrors(['item_id' => 'Não é possível apontar horas diretamente numa subtarefa — use o card pai.']);
        }

        $user = Auth::user();
        $date = $request->date;
        
        $newMinutes = ($request->hours * 60) + $request->minutes;

        if ($newMinutes <= 0) {
            return back()->withErrors(['minutes' => 'O tempo apontado deve ser maior que zero.']);
        }

        // Calculate total minutes already logged for this date
        $loggedMinutes = TimeEntry::where('user_id', $user->id)
            ->where('date', $date)
            ->sum('minutes');

        if (($loggedMinutes + $newMinutes) > 600) { // 10 hours * 60 minutes
            $remaining = 600 - $loggedMinutes;
            $remainingHours = floor($remaining / 60);
            $remainingMinutes = $remaining % 60;
            
            return back()->withErrors(['time' => "O limite de 10 horas diárias seria excedido. Você só pode apontar mais {$remainingHours}h {$remainingMinutes}m para esta data."]);
        }

        // Check for duplicate entry
        $exists = TimeEntry::where('user_id', $user->id)
            ->where('item_id', $request->item_id)
            ->where('date', $date)
            ->exists();

        if ($exists) {
             return back()->withErrors(['item_id' => 'Você já fez um apontamento para este card nesta data. Edite o apontamento existente.']);
        }

        TimeEntry::create([
            'user_id' => $user->id,
            'item_id' => $request->item_id,
            'date' => $date,
            'minutes' => $newMinutes,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, TimeEntry $timeEntry)
    {
        if ($timeEntry->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'hours' => 'required|integer|min:0',
            'minutes' => 'required|integer|min:0|max:59',
        ]);

        $newMinutes = ($request->hours * 60) + $request->minutes;

        if ($newMinutes <= 0) {
            return back()->withErrors(['minutes' => 'O tempo apontado deve ser maior que zero.']);
        }

        // Calculate total minutes for this date EXCLUDING current entry
        $loggedMinutes = TimeEntry::where('user_id', Auth::id())
            ->where('date', $timeEntry->date)
            ->where('id', '!=', $timeEntry->id)
            ->sum('minutes');

        if (($loggedMinutes + $newMinutes) > 600) {
            $remaining = 600 - $loggedMinutes;
            $remainingHours = floor($remaining / 60);
            $remainingMinutes = $remaining % 60;
            
            return back()->withErrors(['time' => "O limite de 10 horas diárias seria excedido. Você só pode apontar até {$remainingHours}h {$remainingMinutes}m neste ajuste."]);
        }

        $timeEntry->update([
            'minutes' => $newMinutes,
        ]);

        return redirect()->back();
    }

    public function destroy(TimeEntry $timeEntry)
    {
        if ($timeEntry->user_id !== Auth::id()) {
            abort(403);
        }
        $timeEntry->delete();
        return redirect()->back();
    }
}
