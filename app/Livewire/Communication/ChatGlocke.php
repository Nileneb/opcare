<?php

namespace App\Livewire\Communication;

use App\Domains\Communication\Services\UngeleseneZaehler;
use Livewire\Component;

class ChatGlocke extends Component
{
    private const STAFF_ROLES = ['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche', 'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin'];

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
