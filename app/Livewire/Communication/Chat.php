<?php

namespace App\Livewire\Communication;

use App\Domains\Communication\Models\Konversation;
use App\Domains\Communication\Models\KonversationTeilnehmer;
use App\Domains\Communication\Models\Nachricht;
use App\Domains\Communication\Services\AnkuendigungskanalHolen;
use App\Domains\Communication\Services\DirektnachrichtOeffnen;
use App\Domains\Communication\Services\GruppeErstellen;
use App\Domains\Communication\Services\KonversationGelesen;
use App\Domains\Communication\Services\NachrichtSenden;
use App\Domains\Communication\Services\NachrichtZurueckziehen;
use App\Domains\Communication\Services\StationskanalBeitreten;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Station;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Chat extends Component
{
    use ScopesTenantValidation;

    private const STAFF_ROLES = ['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche', 'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin'];

    public ?int $aktivKonversationId = null;

    public string $entwurf = '';

    public string $neuModus = '';

    public ?int $dmPartner = null;

    public string $gruppeTitel = '';

    public array $gruppeMitglieder = [];

    public ?int $stationWahl = null;

    public function mount(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(self::STAFF_ROLES), 403);
    }

    public function oeffne(int $konversationId): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(self::STAFF_ROLES), 403);

        $tid = app(CurrentTenant::class)->id();
        $konv = Konversation::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->findOrFail($konversationId);

        abort_unless($konv->istMitglied($u->id), 403);

        $this->aktivKonversationId = $konversationId;

        app(KonversationGelesen::class)->handle($konv, $u);
    }

    public function senden(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(self::STAFF_ROLES), 403);

        $this->validate(['entwurf' => 'required|max:2000']);

        $tid = app(CurrentTenant::class)->id();
        $konv = Konversation::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->findOrFail($this->aktivKonversationId);

        abort_unless($konv->istMitglied($u->id), 403);

        app(NachrichtSenden::class)->handle($konv, $u, $this->entwurf);

        $this->entwurf = '';
    }

    public function zuruckziehen(int $nachrichtId): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(self::STAFF_ROLES), 403);

        $tid = app(CurrentTenant::class)->id();
        $nachricht = Nachricht::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->findOrFail($nachrichtId);

        app(NachrichtZurueckziehen::class)->handle($nachricht, $u);
    }

    public function dmStarten(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(self::STAFF_ROLES), 403);

        $konv = app(DirektnachrichtOeffnen::class)->handle($u, (int) $this->dmPartner);
        $this->aktivKonversationId = $konv->id;
        $this->neuModus = '';
        $this->dmPartner = null;
    }

    public function gruppeAnlegen(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(self::STAFF_ROLES), 403);

        $this->validate([
            'gruppeTitel' => 'required|max:120',
            'gruppeMitglieder' => 'array',
        ]);

        $konv = app(GruppeErstellen::class)->handle($u, $this->gruppeTitel, $this->gruppeMitglieder);
        $this->aktivKonversationId = $konv->id;
        $this->neuModus = '';
        $this->gruppeTitel = '';
        $this->gruppeMitglieder = [];
    }

    public function stationBeitreten(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(self::STAFF_ROLES), 403);

        $konv = app(StationskanalBeitreten::class)->handle($u, (int) $this->stationWahl);
        $this->aktivKonversationId = $konv->id;
        $this->neuModus = '';
        $this->stationWahl = null;
    }

    public function ankuendigungOeffnen(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(self::STAFF_ROLES), 403);

        $tid = app(CurrentTenant::class)->id();
        $konv = app(AnkuendigungskanalHolen::class)->handle($tid);
        $this->aktivKonversationId = $konv->id;
        $this->neuModus = '';
    }

    public function render()
    {
        $u = auth()->user();
        $tid = app(CurrentTenant::class)->id();

        $konversationIds = KonversationTeilnehmer::withoutGlobalScopes()
            ->where('user_id', $u->id)
            ->pluck('konversation_id');

        $konversationen = Konversation::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->whereIn('id', $konversationIds)
            ->with(['teilnehmer.user'])
            ->get()
            ->map(function (Konversation $k) use ($u, $tid) {
                $letzte = $k->letzteNachricht();

                $teilnehmerRecord = $k->teilnehmer->firstWhere('user_id', $u->id);

                $ungelesen = Nachricht::withoutGlobalScopes()
                    ->where('konversation_id', $k->id)
                    ->where('tenant_id', $tid)
                    ->where('user_id', '!=', $u->id)
                    ->whereNull('geloescht_am')
                    ->when(
                        $teilnehmerRecord?->zuletzt_gelesen_am,
                        fn ($q, $ts) => $q->where('created_at', '>', $ts)
                    )
                    ->count();

                if ($k->typ->value === 'Direkt') {
                    $anderer = $k->teilnehmer->firstWhere('user_id', '!=', $u->id);
                    $anzeigeName = $anderer?->user->name ?? 'Direktnachricht';
                } elseif ($k->typ->value === 'Station') {
                    $anzeigeName = $k->titel ?? $k->station->name ?? 'Stationskanal';
                } elseif ($k->typ->value === 'Ankuendigung') {
                    $anzeigeName = 'Ankündigungen';
                } else {
                    $anzeigeName = $k->titel ?? 'Gruppe';
                }

                return [
                    'konversation' => $k,
                    'anzeigeName' => $anzeigeName,
                    'ungelesen' => $ungelesen,
                    'letzte' => $letzte,
                ];
            })
            ->sortByDesc(fn ($item) => $item['letzte']?->created_at)
            ->values();

        $aktiv = null;
        $darfSchreiben = false;
        $nachrichten = collect();

        if ($this->aktivKonversationId !== null) {
            $aktiv = Konversation::withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->where('id', $this->aktivKonversationId)
                ->with(['nachrichten.absender'])
                ->first();

            if ($aktiv !== null && $aktiv->istMitglied($u->id)) {
                $darfSchreiben = $aktiv->darfSchreiben($u);
                $nachrichten = $aktiv->nachrichten;
            } else {
                $aktiv = null;
            }
        }

        $kollegen = User::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->where('id', '!=', $u->id)
            ->role(self::STAFF_ROLES)
            ->orderBy('name')
            ->get();

        $stationen = Station::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->orderBy('name')
            ->get();

        return view('livewire.communication.chat', [
            'konversationen' => $konversationen,
            'aktiv' => $aktiv,
            'nachrichten' => $nachrichten,
            'darfSchreiben' => $darfSchreiben,
            'kollegen' => $kollegen,
            'stationen' => $stationen,
            'ichId' => $u->id,
        ]);
    }
}
