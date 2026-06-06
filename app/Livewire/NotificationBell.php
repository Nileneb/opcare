<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Glocke im Header: zeigt ungelesene In-App-Benachrichtigungen (z. B. „Vertretung gesucht"). Aktualisiert
 * sich per Poll; der broadcast-Kanal (Reverb) liefert sie zusätzlich als Echtzeit-Push.
 */
class NotificationBell extends Component
{
    public bool $offen = false;

    public function umschalten(): void
    {
        $this->offen = ! $this->offen;
    }

    public function gelesen(string $id): void
    {
        auth()->user()?->notifications()->where('id', $id)->first()?->markAsRead();
    }

    public function alleGelesen(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'ungelesen' => $user?->unreadNotifications()->latest()->limit(10)->get() ?? collect(),
            'anzahl' => $user?->unreadNotifications()->count() ?? 0,
        ]);
    }
}
