<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $item;

    public function __construct($item)
    {
        $this->item = $item;
    }

    public function broadcastOn(): array
    {
        return [new Channel('board')];
    }

    public function broadcastAs(): string
    {
        return 'item.updated';
    }
}
