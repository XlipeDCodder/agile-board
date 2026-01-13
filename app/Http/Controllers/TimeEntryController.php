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
        
        // Fetch entries for the current user, maybe filter by month if we want optimization later
        $entries = TimeEntry::where('user_id', $user->id)
            ->with('item.project') // Eager load item and project info
            ->get();

        // Fetch items assigned to the user OR unassigned/open items that they might work on
        // For simplicity, let's allow them to log time on any item they are assigned to, or any item in general?
        // User request: "indicar qual ou quais cards trabalhou"
        // Let's provide all items for now, or filter by active columns. 
        // Better: Items assigned to user OR allow searching. 
        // Simplest valid path: All items not in "Done" or just All Items. 
        // Let's return Items where user is assignee OR items created by user.
        $items = Item::whereHas('assignees', function($q) use ($user) {
            $q->where('id', $user->id);
        })->orWhere('creator_id', $user->id)->with(['project', 'column'])->get();

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
