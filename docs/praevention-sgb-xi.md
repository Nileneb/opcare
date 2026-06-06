# Prävention in der stationären Pflege (§ 5 SGB XI)

Recherche-Strang D: **kassenfinanzierte** Präventionsprogramme je Handlungsfeld + dokumentierte Teilnahmen
je Bewohner als **Verwendungsnachweis** gegenüber der Pflegekasse. Anders als die übrigen Module ist dies
eine Erlösquelle — die Pflegekasse finanziert § 5-SGB-XI-Maßnahmen mit.

> Screenshot: siehe Wiki-Seite **Praevention**.

## Handlungsfelder (GKV-Leitfaden Prävention, 28.09.2023)

Ernährung · Körperliche Aktivität · Kognitive Ressourcen · Psychosoziale Gesundheit · Prävention von Gewalt.
`Handlungsfeld`-Enum; Programme sind je Feld gruppiert.

## Funktion

- **Programm anlegen** je Handlungsfeld (Titel, Frequenz, Verantwortliche:r).
- **Teilnahme dokumentieren**: je Termin ein Datum + Dauer + ausgewählte Bewohner:innen + Beobachtung.
- **Verwendungsnachweis**: je Programm werden Teilnahmen-Anzahl und Gesamt-Minuten ausgewiesen — die
  Grundlage für die Abrechnung/den Nachweis gegenüber der Pflegekasse.

Operativer Einstiegspunkt: Nav **Prävention** (Rollen `admin`/`pflegefachkraft`/`betreuungskraft`).
Knüpft an die soziale Betreuung (§ 43b) an, ist aber bewusst getrennt — § 5 SGB XI ist die kassenfinanzierte
Programmebene (über die Einzelpflege hinaus).

## Architektur

Domäne `App\Domains\SocialCare`: `Handlungsfeld`-Enum, Modelle `Praeventionsprogramm` (mit Teilnahmen-Aggregat)
und `Praeventionsteilnahme`, Livewire `Praevention`. Route `/praevention`. Tests:
`tests/Feature/SocialCare/PraeventionTest.php`. Brücke (geplant): Assessment-Risiko → Programm-Empfehlung.
