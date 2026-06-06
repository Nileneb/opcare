<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\BarbetragKategorie;
use App\Domains\Accounting\Enums\TreuhandVorgang;
use App\Domains\Accounting\Models\Treuhandbuchung;
use App\Domains\Accounting\Models\Treuhandkonto;
use App\Domains\Accounting\Support\BudgetMonitor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Erzeugt eine Treuhand-Buchung append-only (HeimsicherungsV § 17): laufende Nummer + fortgeschriebener
 * Saldo werden berechnet, der Saldo darf nicht negativ werden (kein Überziehen fremden Geldes), Korrekturen
 * verlangen einen Bezug auf die Fehlbuchung + Grund. Eine Auszahlung, die ein Budget mit aktiver Sperre
 * reißt, wird abgewiesen (Sperr-Ampel). Bestehende Buchungen werden nie verändert.
 */
class TreuhandBuchen
{
    public function __construct(private readonly BudgetMonitor $budgets) {}

    /**
     * @param  float  $betrag  positiver Betrag (Vorzeichen ergibt sich aus dem Vorgang); bei Korrektur vorzeichenbehaftet.
     * @param  array<string, mixed>  $extra  kategorie, zweck, beleg_nr, erfasst_von, korrigiert_buchung_id, grund
     */
    public function handle(Treuhandkonto $konto, TreuhandVorgang $vorgang, float $betrag, string $datum, array $extra = []): Treuhandbuchung
    {
        if (! $konto->offen()) {
            throw new InvalidArgumentException('Treuhandkonto ist geschlossen.');
        }
        if (empty($extra['zweck'])) {
            throw new InvalidArgumentException('Verwendungszweck ist Pflicht (Einzelbelegpflicht).');
        }
        if ($vorgang === TreuhandVorgang::Korrektur) {
            if (empty($extra['korrigiert_buchung_id']) || empty($extra['grund'])) {
                throw new InvalidArgumentException('Korrektur braucht Bezugsbuchung und Grund.');
            }
        } elseif ($betrag <= 0) {
            throw new InvalidArgumentException('Betrag muss positiv sein.');
        }

        $kategorie = $this->kategorie($extra['kategorie'] ?? null);

        // Budget-Sperre: eine Auszahlung, die ein gesperrtes Kategorie- oder Gesamtbudget reißt, wird blockiert.
        if ($vorgang === TreuhandVorgang::Auszahlung) {
            foreach ([$kategorie, null] as $topf) {
                $status = $this->budgets->status($konto, $topf, $datum);
                if ($status->istGesperrt(abs($betrag))) {
                    $name = $topf?->label() ?? 'Gesamtbudget';
                    throw new InvalidArgumentException(
                        'Budget gesperrt: '.$name.' (Limit '.number_format((float) $status->limit(), 2, ',', '.').' €).');
                }
            }
        }

        return DB::transaction(function () use ($konto, $vorgang, $betrag, $datum, $extra, $kategorie) {
            $letzte = Treuhandbuchung::where('treuhand_konto_id', $konto->id)->lockForUpdate()->orderByDesc('lfd_nr')->first();
            $lfdNr = ($letzte->lfd_nr ?? 0) + 1;
            $vorsaldo = $letzte ? (float) $letzte->saldo_nach : 0.0;

            $signiert = $vorgang === TreuhandVorgang::Korrektur ? $betrag : $vorgang->vorzeichen() * abs($betrag);
            $saldoNach = round($vorsaldo + $signiert, 2);
            if ($saldoNach < 0) {
                throw new InvalidArgumentException('Auszahlung übersteigt das Guthaben ('.number_format($vorsaldo, 2, ',', '.').' €).');
            }

            return Treuhandbuchung::create([
                'tenant_id' => $konto->tenant_id,
                'treuhand_konto_id' => $konto->id,
                'lfd_nr' => $lfdNr,
                'vorgang' => $vorgang->value,
                'datum' => $datum,
                'betrag' => $signiert,
                'saldo_nach' => $saldoNach,
                'kategorie' => $kategorie?->value,
                'zweck' => $extra['zweck'],
                'beleg_nr' => $extra['beleg_nr'] ?? null,
                'erfasst_von' => $extra['erfasst_von'] ?? auth()->id(),
                'korrigiert_buchung_id' => $extra['korrigiert_buchung_id'] ?? null,
                'grund' => $extra['grund'] ?? null,
            ]);
        });
    }

    private function kategorie(mixed $value): ?BarbetragKategorie
    {
        if ($value instanceof BarbetragKategorie) {
            return $value;
        }

        return $value ? BarbetragKategorie::tryFrom((string) $value) : null;
    }
}
