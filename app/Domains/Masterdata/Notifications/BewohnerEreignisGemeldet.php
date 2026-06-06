<?php

namespace App\Domains\Masterdata\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * In-App-Benachrichtigung an eine Vertretung mit Nutzerkonto, wenn ein wesentliches Bewohner-Ereignis
 * (§ 1821 BGB) eintritt, das in ihren Aufgabenkreis fällt — wahrt das Beteiligungs-/Informationsrecht.
 */
class BewohnerEreignisGemeldet extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $ereignisId,
        public readonly string $bewohner,
        public readonly string $kategorie,
        public readonly string $titel,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'typ' => 'bewohner_ereignis',
            'titel' => "{$this->kategorie}: {$this->bewohner}",
            'text' => "„{$this->titel}“ betrifft {$this->bewohner} und fällt in Ihren Aufgabenkreis.",
            'url' => route('portal'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
