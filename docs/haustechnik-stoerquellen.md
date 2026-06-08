# Top-Störquellen & Notfallvorsorge (Haustechnik)

Wertet die häufigsten Haustechnik-Ausfälle der letzten **6 bzw. 12 Monate** aus den Mängelmeldungen aus und führt
je Top-Störquelle eine **Notfallvorsorge**: Mindest-Ersatzteile, schriftlich fixierte Dienstleister-Reaktionszeit
und interne Sofortmaßnahmen-Checkliste. Macht die Lücke sichtbar — häufige Ausfälle **ohne** hinterlegte Vorsorge.

Route: `/haustechnik/stoerquellen` (verlinkt aus der Haustechnik-Seite).

## Auswertung (datengetrieben, kein Rateschätzen)

`StoerquellenAnalyzer::analysiere(tenantId, monate)` gruppiert die `FacilityMeldung`-Störungen im Zeitfenster je
Betriebsmittel (`FacilityAsset`), rankt nach Häufigkeit und liefert je Störquelle: Anzahl, davon offen, davon
dringend (Priorität hoch/dringend), Datum der letzten Meldung und ob bereits eine Vorsorge hinterlegt ist.

- **Zeitfenster** umschaltbar 6/12 Monate.
- **Meldungen ohne Anlagenbezug** werden NICHT verschluckt, sondern als eigene Zeile „nicht zugeordnet" geführt
  (Datenhygiene-Hinweis: Betriebsmittel zuordnen).
- **Kein stilles Kappen:** die UI zeigt die Top 10, nennt aber die Gesamtzahl der Störquellen im Zeitraum.
- **Lücken-Warnung:** häufige Störquellen ohne Vorsorge werden oben rot ausgewiesen — konkreter Handlungsdruck.

## Notfallvorsorge je Störquelle

`StoerquelleVorsorge` (tenant-scoped, auditiert) je Störquelle — entweder an ein konkretes Betriebsmittel
(`asset_id`) gebunden oder **kategorieweit** (`asset_id` null + Kategorie):

- **Mindest-Ersatzteile** — was vorzuhalten ist (z. B. Türkontakt, Notruf-Taster, Sicherungen).
- **Dienstleister + Kontakt** und **Reaktionszeit (schriftlich fixiert)** — Freitext-SLA („4 h", „nächster
  Werktag", „24/7-Notdienst") plus optionale Stundenzahl.
- **Interne Sofortmaßnahmen** — Checkliste der ersten Schritte vor Eintreffen des Dienstleisters (z. B. „Aufzug
  abschalten + absperren", „betroffene Bewohner umverlegen").

Anlegen/Bearbeiten/Löschen ist auf `admin`/`pflegefachkraft`/`haustechnik` (+ super-admin) beschränkt; melden und
die Auswertung sehen alle Mitarbeitenden. Tenant-Isolation + IDOR-Schutz auf jeder Schreibmethode.

## Bausteine

- `app/Domains/Facility/Models/StoerquelleVorsorge.php` (`deckt()`, `sofortmassnahmenListe()`)
- `app/Domains/Facility/Data/StoerquellenBefund.php` (DTO, `istLuecke()`)
- `app/Domains/Facility/Services/StoerquellenAnalyzer.php`
- `app/Livewire/Facility/Stoerquellen.php` + `resources/views/livewire/facility/stoerquellen.blade.php`
- Migration `stoerquelle_vorsorgen`
