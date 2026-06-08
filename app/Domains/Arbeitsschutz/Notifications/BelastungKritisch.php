<?php

namespace App\Domains\Arbeitsschutz\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Benachrichtigung an admin/super-admin wenn Belastungs-Index Meldepflichts-Schwelle überschreitet.
 * Norm-Anker: § 5 Abs. 3 Nr. 6 ArbSchG (psychische Belastung), § 6 ArbSchG (Dokumentationspflicht).
 */
class BelastungKritisch extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $wohnbereich,
        public readonly string $stufe,
        public readonly int $score,
        public readonly string $topSignal,
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
            'typ' => 'belastung',
            'titel' => "Belastungsmeldung: {$this->wohnbereich}",
            'text' => "Wohnbereich {$this->wohnbereich}: Belastung {$this->stufe} ({$this->score}/100) — {$this->topSignal}.",
            'url' => route('dienstplan'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
