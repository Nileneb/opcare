# Übergangs-/Spitzendienste

Der wochenbezogene § 113c-Betreuungsschlüssel sagt nichts über die **Tageszeit** aus — der Personalbedarf ist
aber zu Mahlzeiten und in der Morgen-Grundpflege deutlich höher. opcare ergänzt deshalb eine **Spitzenzeit-Sicht**:
tageszeitliche Bedarfs-Fenster mit Soll-Personenzahl, ausgewertet gegen die geplanten Schichten, plus konkrete
Vorschläge für kurze **Spitzendienste**.

## Konzept

| Baustein | App-Logik |
|---|---|
| kurzer, gezielter Dienst für Bedarfsspitzen | `ShiftKind::Spitzendienst` — eigene Schicht-Art, im Dienstplan wie jede Schicht zuweisbar |
| tageszeitliches Bedarfs-Fenster | `Spitzenzeit` (Domain `Scheduling`): Name, Beginn/Ende, Soll-Personen, „nur werktags", aktiv — editierbarer Katalog je Einrichtung (`SpitzenzeitDefaults::ensureFor`) |
| Deckungsgrad zur Spitzenzeit | `SpitzenzeitAnalyzer`: je Fenster × Tag die Mitarbeitenden mit überlappender Schicht (Ist) vs. Soll → Ampel |
| Vorschläge bei Unterdeckung | der Analyzer nennt je unterbesetztem Fenster die fehlende Personenzahl + Zeitfenster |

## Deckungslogik

Ein Dienst „deckt" ein Fenster, wenn seine Zeitspanne das Fenster überlappt (`Spitzenzeit::ueberlappt`,
minutenbasiert, mit korrektem Mitternachts-Umbruch für Nachtdienste). Je Fenster und Tag zählen die **verschiedenen**
anwesenden Mitarbeitenden. Ampel aus dem Personen-Defizit: gedeckt → grün, eine Person fehlt → gelb, ≥ 2 fehlen → rot.
Standard-Fenster (vorbefüllt, anpassbar): Morgendliche Grundpflege, Frühstück, Mittagessen, Abendversorgung.

## Verhältnis zum § 113c-Schlüssel

Die Spitzenzeit-Soll ist eine **tageszeitabhängige** Ergänzung (Strang B), keine Änderung der bundeseinheitlichen
§ 113c-VZÄ-Berechnung. Beide Sichten stehen nebeneinander: der Wochen-Schlüssel im
[Betreuungsschlüssel](betreuungsschluessel-schichtregeln.md) (Arbeitsrecht), die Tageszeit-Sicht hier.

## Datenmodell

`spitzenzeiten` (tenant-gescopt, editierbar) + `ShiftKind::Spitzendienst`. Livewire
`App\Livewire\Scheduling\Spitzenzeiten` (Route `spitzenzeiten`, Dienstplan-Nav, nur Leitung — `manage`-Policy auf
`Shift`). Idee #4 aus [ideen-backlog-2026-06.md](ideen-backlog-2026-06.md).
