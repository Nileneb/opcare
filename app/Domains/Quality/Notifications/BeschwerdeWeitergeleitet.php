<?php

namespace App\Domains\Quality\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Benachrichtigung an die Empfänger-Rolle eines Bereichs, wenn eine Beschwerde an sie weitergeleitet wird.
 * Der Melder-Hinweis respektiert die Anonymitätswahl: bei anonym wird keine Identität übermittelt.
 */
class BeschwerdeWeitergeleitet extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $beschwerdeId,
        public readonly string $titel,
        public readonly string $bereich,
        public readonly bool $anonym,
        public readonly ?string $melder,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $herkunft = $this->anonym ? 'anonym' : ($this->melder ?? 'unbekannt');

        return [
            'typ' => 'beschwerde',
            'titel' => "Beschwerde weitergeleitet: {$this->bereich}",
            'text' => "„{$this->titel}“ (Melder: {$herkunft}) wurde an Ihren Bereich weitergeleitet.",
            'url' => route('quality.beschwerden', ['fokus' => $this->beschwerdeId]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
