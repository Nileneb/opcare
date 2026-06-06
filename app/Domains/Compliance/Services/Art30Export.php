<?php

namespace App\Domains\Compliance\Services;

use App\Domains\Compliance\Models\Auftragsverarbeitung;
use App\Domains\Compliance\Models\Verarbeitungstaetigkeit;

/**
 * Erzeugt das vorlagefähige Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO) als Klartext für die
 * Aufsichtsbehörde — inkl. der zugehörigen Auftragsverarbeitungen (Art. 28). Reine Textfunktion, damit der
 * Inhalt unabhängig vom Download-Kanal testbar bleibt.
 */
class Art30Export
{
    public function render(int $tenantId, string $tenantName): string
    {
        $vts = Verarbeitungstaetigkeit::where('tenant_id', $tenantId)->orderBy('id')->get();
        $avvs = Auftragsverarbeitung::where('tenant_id', $tenantId)->orderBy('id')->get();

        $out = "Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO)\n";
        $out .= 'Verantwortlicher: '.$tenantName."\n";
        $out .= 'Stand: '.today()->format('d.m.Y')."\n";
        $out .= str_repeat('=', 70)."\n\n";

        foreach ($vts as $i => $v) {
            $out .= ($i + 1).'. '.$v->name."\n";
            $out .= '   Zweck: '.$v->zweck."\n";
            $out .= '   Rechtsgrundlage: '.$v->rechtsgrundlage->label().' ('.$v->rechtsgrundlage->artikel().")\n";
            $out .= '   Kategorien Betroffener: '.$v->kategorien_betroffene."\n";
            $out .= '   Kategorien Daten: '.$v->kategorien_daten."\n";
            $out .= '   Empfänger: '.($v->empfaenger ?? '—')."\n";
            $out .= '   Drittlandtransfer: '.($v->drittland ?? 'nein')."\n";
            $out .= '   Löschfrist: '.$v->loeschfrist."\n";
            $out .= '   TOM: '.($v->tom ?? '—')."\n";
            $out .= '   Zuletzt geprüft: '.($v->geprueft_am?->format('d.m.Y') ?? 'ungeprüft')."\n\n";
        }

        $out .= "\nAuftragsverarbeitungen (Art. 28 DSGVO)\n";
        $out .= str_repeat('-', 70)."\n\n";
        foreach ($avvs as $i => $a) {
            $out .= ($i + 1).'. '.$a->dienstleister."\n";
            $out .= '   Zweck: '.$a->zweck."\n";
            $out .= '   Kategorien Daten: '.$a->kategorien_daten."\n";
            $out .= '   Unterauftragnehmer: '.($a->unterauftragnehmer ? 'ja' : 'nein')."\n";
            $out .= '   Drittland: '.($a->drittland ?? 'nein')."\n";
            $out .= '   AVV geschlossen am: '.($a->vertrag_geschlossen_am?->format('d.m.Y') ?? 'KEIN AVV')."\n\n";
        }

        return $out;
    }
}
