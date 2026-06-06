<?php

namespace App\Domains\Scheduling\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Benachrichtigung an die Leitung, wenn eine Krankmeldung Dienste als Vertretung offen lässt. Geht über den
 * database-Kanal (für die In-App-Glocke) und den broadcast-Kanal (Push via Reverb).
 */
class VertretungBenoetigt extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $mitarbeiter,
        public readonly string $zeitraum,
        public readonly int $anzahlDienste,
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
            'typ' => 'vertretung',
            'titel' => 'Vertretung gesucht',
            'text' => "{$this->mitarbeiter} ist abwesend ({$this->zeitraum}) — {$this->anzahlDienste} Dienst(e) offen.",
            'url' => route('tauschboerse'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
