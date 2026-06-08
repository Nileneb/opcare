<?php

namespace App\Domains\Arbeitsschutz\Services;

use App\Domains\Arbeitsschutz\Models\BelastungFreischaltung;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Models\Abstimmung;
use App\Domains\Voting\Services\Auszaehlung;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Schaltet die Selbst-Ampel (Mode B/C) auf Basis eines geschlossenen Mitarbeitenden-Beschlusses frei.
 *
 * Invariante: Ohne angenommenen Beschluss (echte Stimmenmehrheit unter Abgestimmten) keine Freischaltung.
 * Norm-Anker: § 87 Abs. 1 Nr. 6 BetrVG (Mitbestimmung technische Einrichtungen).
 */
class BelastungFreischalten
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly Auszaehlung $auszaehlung,
    ) {}

    /**
     * Schaltet aus einem geschlossenen und angenommenen Mitarbeitenden-Beschluss frei.
     *
     * @param  int  $zustimmungOptionId  ID der Option, die als „Ja/Zustimmung" gilt
     *
     * @throws InvalidArgumentException wenn Voraussetzungen nicht erfüllt
     */
    public function ausBeschluss(Abstimmung $abstimmung, int $zustimmungOptionId, User $user): BelastungFreischaltung
    {
        $tenantId = $this->currentTenant->id();

        if ($abstimmung->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Abstimmung gehört nicht zum aktuellen Mandanten.');
        }

        if ($abstimmung->art !== Abstimmungsart::Beschluss) {
            throw new InvalidArgumentException('Nur Abstimmungen der Art „Beschluss" dürfen als Freischaltungs-Grundlage dienen.');
        }

        if ($abstimmung->elektorat !== Elektorat::Mitarbeitende) {
            throw new InvalidArgumentException('Der Beschluss muss vom Elektorat „Mitarbeitende" stammen.');
        }

        if ($abstimmung->status !== AbstimmungStatus::Geschlossen) {
            throw new InvalidArgumentException('Der Beschluss muss den Status „Geschlossen" haben.');
        }

        $ergebnis = $this->auszaehlung->ergebnis($abstimmung);

        if (! array_key_exists($zustimmungOptionId, $ergebnis['optionen'])) {
            throw new InvalidArgumentException('Die angegebene Zustimmungs-Option existiert nicht in diesem Beschluss.');
        }

        $abgestimmt = $ergebnis['beteiligung']['abgestimmt'];
        $zustimmungsStimmen = $ergebnis['optionen'][$zustimmungOptionId]['stimmen'];

        // WHY: echte Mehrheit der abgegebenen Stimmen (>50 %), nicht nur Pluralität —
        // § 87 BetrVG verlangt Mehrheitsbeschluss des Gremiums.
        if ($abgestimmt === 0 || $zustimmungsStimmen <= $abgestimmt / 2) {
            throw new InvalidArgumentException('Der Beschluss wurde nicht angenommen (keine echte Mehrheit der abgegebenen Stimmen).');
        }

        return DB::transaction(function () use ($abstimmung, $user, $tenantId): BelastungFreischaltung {
            // Vorherige aktive Freischaltung zurücknehmen
            BelastungFreischaltung::where('tenant_id', $tenantId)
                ->whereNull('zurueckgenommen_am')
                ->update([
                    'zurueckgenommen_von' => $user->id,
                    'zurueckgenommen_am' => today(),
                ]);

            return BelastungFreischaltung::create([
                'tenant_id' => $tenantId,
                'abstimmung_id' => $abstimmung->id,
                'freigeschaltet_von' => $user->id,
                'freigeschaltet_am' => today(),
            ]);
        });
    }

    public function zuruecknehmen(User $user): void
    {
        $tenantId = $this->currentTenant->id();

        BelastungFreischaltung::where('tenant_id', $tenantId)
            ->whereNull('zurueckgenommen_am')
            ->update([
                'zurueckgenommen_von' => $user->id,
                'zurueckgenommen_am' => today(),
            ]);
    }
}
