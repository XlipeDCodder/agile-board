<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Página completa /notifications — listagem paginada de todas as
     * notificações do user logado.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($n) => $this->serializeNotification($n));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Endpoint do sino — retorna as últimas N notificações sem paginação,
     * junto com contagem de não-lidas. Usado pelo dropdown do header.
     */
    public function dropdown(): JsonResponse
    {
        $user = Auth::user();

        $latest = $user->notifications()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($n) => $this->serializeNotification($n));

        return response()->json([
            'notifications' => $latest,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification && ! $notification->read_at) {
            $notification->markAsRead();
        }
        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        Auth::user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Todas as notificações marcadas como lidas.');
    }

    private function serializeNotification($n): array
    {
        $data = is_array($n->data) ? $n->data : json_decode($n->data, true);
        return [
            'id' => $n->id,
            'type' => $data['type'] ?? $n->type,
            'message' => $data['message'] ?? '(sem mensagem)',
            'data' => $data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
