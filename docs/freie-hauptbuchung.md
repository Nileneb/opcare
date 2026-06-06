# Freie Hauptbuchung

Die Buchhaltung konnte bisher nur Wareneingang/-verbrauch automatisch buchen. Für generische Geschäftsvorfälle
(Einzahlung, Zahlung, Korrektur, Spende) fehlte eine **freie Buchungsmaske** im Hauptbuch — die wird hier
ergänzt, ohne neue Logik: die zentrale `Buchen`-Action hat bereits alles.

## Norm → App-Logik

| Norm / Quelle | App-Logik |
|---|---|
| GoB / Pflege-Buchführungsverordnung ([PBV](https://www.gesetze-im-internet.de/pbv/)) — Buchungssatz „Soll an Haben" | `App\Domains\Accounting\Actions\Buchen` (bestehend) |
| jeder Buchungssatz: Soll- und Haben-Konto verschieden, Betrag positiv | Validierung `different:b_soll` + `gt:0`; die Action wirft sonst `InvalidArgumentException` |
| tenant-getrennte Konten (kein IDOR) | `tenantExists('konten')` für beide Konto-FKs |

## Operativ

Eine Karte „Freie Buchung" im Buchhaltungs-Livewire (`App\Livewire\Accounting\Buchhaltung::freieBuchung()`):
Soll-Konto, Haben-Konto, Betrag, Datum, Buchungstext, optionaler Beleg → ein Buchungssatz im Journal. Sichtbar
für die Finanzrollen (admin/buchhaltung). Die Salden aktualisieren sich nach Kontoart wie bei allen Buchungen.

## Datenmodell

Keine neue Tabelle — schreibt in `buchungen` über die vorhandene `Buchen`-Action. Siehe auch
[Buchhaltung & Warenwirtschaft](buchhaltung-warenwirtschaft.md).
