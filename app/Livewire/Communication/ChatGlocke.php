<?php

namespace App\Livewire\Communication;

use App\Domains\Communication\Models\KonversationTeilnehmer;
use App\Domains\Communication\Services\UngeleseneZaehler;
use Livewire\Component;

class ChatGlocke extends Component
{
    private const STAFF_ROLES = ['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche', 'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin'];

    /**
     * Echo-Push: zählt den Ungelesen-Badge sofort neu, sobald in IRGENDEINEM Kanal der
     * Person eine Nachricht ankommt. Brandneue Konversationen (nach Seitenaufbau eröffnet)
     * deckt der langsame wire:poll-Fallback ab.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $u = auth()->user();

        if ($u === null || ! $u->hasAnyRole(self::STAFF_ROLES)) {
            return [];
        }

        return KonversationTeilnehmer::withoutGlobalScopes()
            ->where('user_id', $u->id)
            ->pluck('konversation_id')
            // WHY: Führender Punkt vor dem Event-Namen — sonst prefixt Laravel Echo einen Namespace
            // (App.Events.) und der Listener matcht den per broadcastAs gesendeten Namen nie.
            ->mapWithKeys(fn (int $id): array => [
                "echo-private:konversation.{$id},.NachrichtGesendet" => '$refresh',
            ])
            ->all();
    }

    public function render()
    {
        $u = auth()->user();

        $istStaff = $u !== null && $u->hasAnyRole(self::STAFF_ROLES);

        $anzahl = $istStaff ? app(UngeleseneZaehler::class)->fuer($u) : 0;

        return view('livewire.communication.chat-glocke', [
            'anzahl' => $anzahl,
            'istStaff' => $istStaff,
        ]);
    }
}
