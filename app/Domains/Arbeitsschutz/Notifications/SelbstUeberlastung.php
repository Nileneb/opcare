<?php

namespace App\Domains\Arbeitsschutz\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Benachrichtigung an die Leitung bei selbst-initiierter Überlastungsmeldung (Mode C).
 *
 * Named ist zulässig — der/die MA hat den Knopf selbst gedrückt und einer Weiterleitung zugestimmt
 * (§ 87 BetrVG-Analogie: Freischaltung durch Mitarbeitenden-Beschluss als kollektive Einwilligung).
 */
class SelbstUeberlastung extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly int $wert,
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
            'typ' => 'selbst_ueberlastung',
            'titel' => 'Überlastungsmeldung',
            'text' => "{$this->name} meldet Überlastung (Wert {$this->wert}/10).",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
