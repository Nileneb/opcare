# Tauschbörse, Krankmeldung & Mindest-Fachkraftquote

Drei zusammenhängende Erweiterungen der Dienstplanung.

> Screenshot: Wiki-Seite **Tauschboerse**.

## 1. Mindest-Fachkraftquote (hart im Auto-Generator)

Der Auto-Dienstplan-Generator erzwingt jetzt je Schicht eine **Mindest-Fachkraftzahl**:
`floor(soll_besetzung × fachkraftquote_min)` — und für den **Nachtdienst immer mindestens 1 Fachkraft**
(Landesheimrecht). Ist sonst die Quote nicht mehr erreichbar (verbleibende Plätze ≤ fehlende Fachkräfte),
werden für die Pflichtplätze **nur noch Fachkräfte** zugelassen; fehlt eine, bleibt der Platz offen und wird
als „… (Fachkraft nötig)" gemeldet. `fachkraftquote_min` ist je Einrichtung unter „Arbeitsrecht-Regeln" einstellbar.

## 2. Krankmeldung / Abwesenheit

`Abwesenheit` (Krank/Urlaub/Sonstiges) über einen Zeitraum. Eine Krankmeldung **öffnet die betroffenen Dienste
automatisch als Vertretungs-Anfrage** in der Tauschbörse und macht die Person für den Auto-Generator an diesen
Tagen **nicht planbar**. Mitarbeitende melden sich selbst krank; die PDL (Planungsrecht) kann für andere melden.

## 3. Tauschbörse (Dienste tauschen / Vertretung übernehmen)

- **Meine Dienste** → „Abgeben / Tauschen" öffnet eine Tausch-Anfrage.
- **Offene Vertretungen & Tausche** (Board) → „Übernehmen". Beim Übernehmen geht die Zuweisung auf die
  übernehmende Person über — **nach harter Prüfung**: kein Doppeldienst am selben Tag, **§ 3 ArbZG**
  (Wochenhöchstarbeitszeit ≤ 48 h). Verstößt es, wird die Übernahme abgelehnt (Meldung statt stiller Fehler).
- Eigene offene Anfragen lassen sich **zurückziehen**.

Krankheits-Vertretungen und freiwillige Tausche teilen sich denselben Mechanismus (`ShiftSwapRequest`, typ
`krankheit`/`tausch`).

## Architektur

Domäne `App\Domains\Scheduling`: `AbwesenheitTyp`-Enum, Modelle `Abwesenheit`/`ShiftSwapRequest`,
Service `ShiftCoverageService` (krankmelden/tauschAnbieten/uebernehmen/zurueckziehen), Livewire `Tauschboerse`
(Route `/tauschboerse`, Nav „Tauschbörse", für alle Mitarbeitenden). Generator-Erweiterung in
`DienstplanGenerator` (Fachkraftquote hart + Abwesenheiten überspringen). Tests:
`tests/Feature/Scheduling/{DienstplanGeneratorTest,TauschboerseTest}.php`.
