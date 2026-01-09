<?php

namespace App\Listeners;

use App\Events\ItemAssigned;
use App\Notifications\ItemAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendItemAssignmentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ItemAssigned $event): void
    {
        $item = $event->item;
        
        if (!$item->relationLoaded('assignees')) {
            $item->load('assignees');
        }

        foreach ($item->assignees as $user) {
            // Não notificar o criador do item
            if ($user->id !== $item->creator_id) {
                try {
                    $user->notify(new ItemAssignedNotification($item));
                } catch (\Exception $e) {
                    Log::error("Falha ao enviar notificação para usuário {$user->id}: " . $e->getMessage());
                }
            }
        }
    }
}
