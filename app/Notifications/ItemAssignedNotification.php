<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ItemAssignedNotification extends Notification
{
    use Queueable;

    public $item;

    /**
     * Create a new notification instance.
     */
    public function __construct($item)
    {
        $this->item = $item;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'item_id' => $this->item->id,
            'title' => $this->item->title,
            'message' => 'Você foi atribuído ao card: ' . $this->item->title,
            'type' => 'assignment'
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'item_id' => $this->item->id,
            'title' => $this->item->title,
            'message' => 'Você foi atribuído ao card: ' . $this->item->title,
            'type' => 'assignment'
        ]);
    }
}
