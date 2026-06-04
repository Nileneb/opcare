<?php

namespace App\Domains\Speech\Events;

use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscriptionProgressed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TranscriptionJob $job) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("transcription.{$this->job->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->job->status->value,
            'rohtranskript' => $this->job->rohtranskript,
            'sis_vorschlag' => $this->job->sis_vorschlag,
        ];
    }
}
