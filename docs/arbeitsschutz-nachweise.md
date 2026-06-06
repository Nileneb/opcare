# Arbeitsschutz-Nachweise (Nachweis-mit-Frist)

Recherche-Strang C: der generische **„Nachweis-mit-Frist"-Mechanismus** für den Arbeitsschutz — eine Matrix
Mitarbeiter:innen × Nachweis-Typ mit Fälligkeits-Ampel. Ein Baustein, der für viele Pflichten wiederverwendbar ist.

> Screenshot: siehe Wiki-Seite **Arbeitsschutz Nachweise**.

## Nachweis-Typen (Katalog)

| Typ | Standard-Intervall | Rechtsbezug |
|---|---|---|
| Unterweisung (Arbeitsschutz) | 12 Monate | § 12 ArbSchG / DGUV V1 § 4 |
| Arbeitsmedizinische Vorsorge | 24 Monate | ArbMedVV |
| Erste-Hilfe-Ausbildung | 24 Monate | DGUV V1 § 26 |
| Brandschutzhelfer:in | 60 Monate | ASR A2.2 |
| BEM-Gespräch | anlassbezogen (keine Frist) | § 167 Abs. 2 SGB IX |

`NachweisTyp` liefert Standard-Intervall + Gesetzesbezug; das Intervall ist je Nachweis überschreibbar
(Tarif/Anlass). Anlassbezogene Nachweise (BEM) haben kein Fälligkeitsdatum.

## Logik & Ampel

Aus **Datum + Intervall** errechnet der `Schutznachweis` die Fälligkeit und daraus den Status:
- **gültig** (grün) · **fällig** (gelb, innerhalb 30 Tagen) · **überfällig** (rot) · **anlassbezogen** (grau, keine Frist).
Fehlt ein fristgebundener Nachweis ganz, zeigt die Matrix **„fehlt"** (rot).

Operativer Einstiegspunkt: Seite **Arbeitsschutz** (Leitungs-Nav) — Übersicht aller Mitarbeitenden mit
Personalakte × Nachweis-Typen, jüngster Nachweis je Zelle, Anzahl überfälliger Nachweise im Kopf, Erfassen direkt.
Rollen: `admin`/`pflegefachkraft` (Leitung).

## Wiederverwendbarkeit

Dieser Mechanismus (Pflicht-Ereignis je Subjekt + Wiederholungsintervall + Ampel + Erinnerung) ist das in der
Recherche identifizierte Querschnitts-Muster — dieselbe Struktur trägt später u. a. STK/MTK-Prüfungen
(Medizinprodukte), Legionellen-Untersuchungen und Räumungsübungen.

## Architektur

Domäne `App\Domains\Personnel`: `NachweisTyp`-Enum, `Schutznachweis`-Model (Fälligkeit/Status/Ampel),
Livewire `Arbeitsschutz` (Matrix + Erfassung). Route `/arbeitsschutz/nachweise`, Nav „Arbeitsschutz".
Tests: `tests/Feature/Personnel/ArbeitsschutzTest.php`.
