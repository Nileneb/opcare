# Budget-Setzungen (generisch)

Ein **Budget** ist ein monatliches Limit mit Warn-Schwelle und optionaler harter Sperre. opcare nutzt **ein**
Muster für alle Budget-Töpfe (AI-Services-Plan §3) — „Budgets braucht man öfter mal irgendwo".

## Ein Muster, zwei Anwendungen

Beide Budget-Arten teilen das Auswertungs-Verhalten über das Contract `BudgetGrenze` (`limitBetrag`/`warnProzent`/
`sperreAktiv`) und das Wertobjekt `BudgetStatus` (Rest, Auslastung %, Ampel, `wuerdeUeberschreiten`, `istGesperrt`):

| Topf | Modell | Bezug | Verbrauch |
|---|---|---|---|
| **Taschengeldkasse** | `Treuhandbudget` | je Treuhandkonto + Barbetrags-Kategorie (§ 27b SGB XII) | Netto-Auszahlungen/Monat (`BudgetMonitor`) |
| **Hauptbuch** | `Budget` | je Sachkonto (z. B. Abteilungs-Aufwand) | Netto in Kontorichtung/Monat (`KontoBudgetMonitor`) |

So ist dasselbe Ampel-/Sperr-Verhalten überall konsistent, und ein weiterer Topf (z. B. Projekt-/Sachkostenbudget)
implementiert nur `BudgetGrenze` + einen Monitor.

## Verzahnung mit der Buchhaltung

Konto-Budgets werden direkt im Buchhaltungs-Livewire gepflegt (Limit/Warn-%/Sperre je Konto) und mit Ist-Verbrauch +
Ampel des laufenden Monats angezeigt. Der `BudgetGuard` greift an **jeder buchenden Stelle**:

- **Freie Hauptbuchung** und **Beleg-Capture-Bestätigung** prüfen vor dem Buchen das Budget des **Soll-Kontos**.
- **Harte Sperre** → die Buchung wird blockiert (Fehlermeldung, nichts wird gebucht).
- **Ohne Sperre** → bei Überschreitung wird **weich gewarnt** (Buchung wird erfasst, Hinweis erscheint).

Ampel: bis Warn-Schwelle grün, ab Warn-Schwelle gelb, ab 100 % rot. Ohne hinterlegtes Budget bleibt die Ampel „kein".

## Datenmodell

`budgets` (tenant-gescopt, ein Budget je Konto), Contract `App\Domains\Accounting\Contracts\BudgetGrenze`,
`KontoBudgetMonitor` + `BudgetGuard` (Support). Plan: `docs/ai-services-plan.md` §3. Siehe auch
[taschengeldkasse.md](taschengeldkasse.md) und [buchhaltung-warenwirtschaft.md](buchhaltung-warenwirtschaft.md).
