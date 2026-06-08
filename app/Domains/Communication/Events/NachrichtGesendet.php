<?php

namespace App\Domains\Communication\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NachrichtGesendet implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $konversationId,
        public readonly int $nachrichtId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('konversation.'.$this->konversationId);
    }
}
