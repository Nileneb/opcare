# Auto-Dienstplan-Generator

Erstellt automatisch einen Wochen-Dienstplan-**Vorschlag**, den die PDL nur prüft und freigibt. Verknüpft alle
bereits gebauten Bausteine: **Soll-Besetzung** je Schicht, **harte ArbZG-Grenzen**, **ergonomische Empfehlungen**,
den **Wunschdienstplan** und das **Vertrags-Pensum**.

> Screenshot: Wiki-Seite **Auto Dienstplan**.

## Bedienung

Im Dienstplan: **✨ Automatisch erstellen** füllt die offenen Slots der Woche als Vorschlag (✨, gestrichelt markiert).
Bestehende manuelle Zuweisungen bleiben unangetastet. Dann **Vorschläge freigeben** (werden normale Dienste) oder
**Verwerfen** (gelöscht). Re-Run ersetzt nur die eigenen Vorschläge.

## Algorithmus (Greedy mit Constraint-Scoring)

1. **Bedarf**: je Tag × aktiver Schicht × `soll_besetzung` ein Slot. Schwer zu besetzende zuerst (Nacht, Wochenende).
2. **Harte Filter** (kein Kandidat, der verletzt): eine Schicht je Tag/Person, **§ 5 ArbZG Ruhezeit** (≥ 11 h zum
   Vor-/Folgetag, Nacht über Mitternacht korrekt), **§ 3 ArbZG Wochenhöchstarbeitszeit** (≤ 48 h), Wunsch ≠ „Frei/Nicht verfügbar".
3. **Scoring** (bester Kandidat gewinnt): Wunsch „Arbeiten" (+), Rest-Pensum bis zum Vertrag (Fairness, +/−),
   **Fachkraft-Abdeckung** je Schicht (+), Ergonomie-Strafen (Rückwärtsrotation, Quick-Return, zu viele Folge-Tage/-Nächte),
   **Wochenend-Gerechtigkeit**. Deterministischer Tie-Break (kein Zufall → reproduzierbar).
4. **Unterdeckung** wird **transparent gemeldet** (offene Slots je Tag/Schicht) — nichts wird stillschweigend übergangen.

Die Schwellwerte stammen aus denselben editierbaren Regeln wie die Live-Prüfung (ArbZG-Regelwerk + Ergonomie-Regeln),
sodass Generator und Validierung konsistent sind. Nach dem Lauf zeigen der ArbZG-Befund, die Ergonomie-Hinweise und
die §-113c-Ampel sofort die Qualität des Vorschlags.

## Architektur

`App\Domains\Scheduling\Support\DienstplanGenerator` (+ `GenerationResult`), neue Felder `shifts.soll_besetzung`
und `shift_assignments.auto_generiert`. Eingebunden in Livewire `Scheduling\Dienstplan` (Buttons + ✨-Markierung im Raster).
Tests: `tests/Feature/Scheduling/DienstplanGeneratorTest.php` (Belegung, Wünsche, manuelle Dienste, Idempotenz, Unterdeckung).
