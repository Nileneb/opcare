# Dienstplan & Arbeitszeit-Compliance (ArbZG)

**Stand:** 2026-06-06 · **Domäne:** `app/Domains/Scheduling`

Der Wochen-Dienstplan prüft jede Zuweisung live gegen ein **editierbares, einrichtungseigenes
Arbeitszeitgesetz-Regelwerk**. Ziel ist Planungs-Unterstützung — **keine Rechtsberatung**.

## Architektur

```
ComplianceRule (DB, tenant-scoped, editierbar)  ──seed──  ArbeitszeitgesetzDefaults (ableitbare ArbZG-Regeln + Gesetzeslinks)
        │
ShiftAssignment + Shift  ──►  WorkingHoursAnalyzer  ──►  ComplianceFinding[]
        │                                                      │
ComplianceJustification (§ 14)  ──►  ComplianceReporter  ──►  annotierte Findings (begründet/offen)
```

- **Regeln sind Daten**, keine Hartkodierung: Schwellwerte (`params`), Schwere und Aktivierung sind im
  Regel-Editor (`/arbeitsrecht`) je Einrichtung anpassbar (Tarif-/Betriebsvereinbarungen). Jede Regel
  verlinkt den **amtlichen Gesetzestext** (gesetze-im-internet.de) + Wortlaut-Zitat.
- **Geprüft** (aktive Default-Regeln): § 3 Tages- (10 h, Hinweis ab 8 h) und Wochenhöchstzeit (48 h-Schnitt),
  § 5 Ruhezeit (11 h; Pflege-Ausnahme 10 h), §§ 9–11 Sonntag (Pflege-Ausnahme + Ersatzruhetag).
- **§ 14 (außergewöhnliche Fälle)** ist die Rechtsgrundlage für dokumentierte Abweichungen: ein offener
  Verstoß lässt sich mit einem **zwingenden Grund** belegen (z. B. „Nachfolgekraft nicht erschienen") —
  der Verstoß **bleibt** ein Verstoß, ist aber nachvollziehbar dokumentiert.

## Ehrliche Grenzen (bewusst, nicht stillgeschaltet)

| Grenze | Verhalten |
|---|---|
| **§ 4 Pausen** | opcare erfasst keine Pausenzeiten → als **„nicht prüfbar"** ausgewiesen statt fälschlich „bestanden". |
| **6-Monats-/24-Wochen-Schnitt** | kein Vertrags-/Pensum-Feld → die Wochenprüfung betrachtet die **angezeigte Woche** (Hinweis, nicht harter Verstoß). |
| **Wochenübergang** | Ruhezeit-Prüfung deckt die angezeigte Woche ab; der exakte Sonntag→Montag-Übergang über die Wochengrenze ist eine Folge-Iteration. |
| **§ 11 freie Sonntage/Jahr** | Sonntagsdienst wird je Tag als Hinweis markiert; die Jahresbilanz „≥ 15 freie Sonntage" ist noch nicht aggregiert. |

## Erweitern

Neue Regel: Eintrag in `ArbeitszeitgesetzDefaults::rules()` (key, paragraph, severity, params, gesetz_url,
gesetz_zitat) → Auswertungs-Zweig im `WorkingHoursAnalyzer` ergänzen → Test in
`tests/Feature/Scheduling/ComplianceEngineTest.php`. Bestehende Einrichtungen erhalten die Regel idempotent
über `ArbeitszeitgesetzDefaults::ensureFor()`.
