# Gefährdungsbeurteilung (§ 5 / § 6 ArbSchG)

Das gesetzliche Fundament des betrieblichen Arbeitsschutzes: die Beurteilung der Arbeitsbedingungen, aus der
sich ergibt, *welche* Schutzmaßnahmen erforderlich sind. Eigene Domäne `app/Domains/Arbeitsschutz`, Route
`/arbeitsschutz/gefaehrdungsbeurteilung`.

> **Abgrenzung:** Die GBU *begründet* die Maßnahmen. Ihre personenbezogene Umsetzung (Unterweisung,
> arbeitsmed. Vorsorge, Erste Hilfe, BEM, ASiG/DGUV-V2-Betreuung) belegt der separate Screen
> [Arbeitsschutz-Nachweise](../app/Livewire/Personnel/Arbeitsschutz.php) (Route `arbeitsschutz.nachweise`).

## Norm-Anker

- **§ 5 ArbSchG** „Beurteilung der Arbeitsbedingungen" — Pflicht jedes Arbeitgebers; **6 Gefährdungsfaktoren**
  (Abs. 3): Arbeitsstätte, physikalische/chemische/biologische Einwirkungen, Arbeitsmittel, Arbeitsverfahren/
  -abläufe/-zeit, unzureichende Qualifikation/Unterweisung, **psychische Belastungen**.
- **§ 6 ArbSchG** „Dokumentation" — Ergebnis der GBU, festgelegte Maßnahmen und Ergebnis ihrer Überprüfung
  müssen dokumentiert sein.
- **§ 3 Abs. 1 ArbSchG** — Maßnahmen auf Wirksamkeit prüfen und erforderlichenfalls anpassen (Fortschreibung).
- **§ 4 ArbSchG** — Maßnahmen-Rangfolge **TOP**: Technisch vor Organisatorisch vor Personenbezogen.

> **Ehrlich:** opcare führt GBU-Register, Gefährdungen mit Risiko-Einstufung, TOP-Maßnahmen, Fälligkeits-Ampel
> zur Fortschreibung und Wirksamkeitskontrolle — die Dokumentationsgrundlage nach § 6. Die fachliche Beurteilung
> (welche Gefährdung, welches Risiko, welche Maßnahme) bleibt Aufgabe der verantwortlichen Person / Fachkraft
> für Arbeitssicherheit.

## Modell

- **`Gefaehrdungsbeurteilung`** — eine GBU je Arbeitsbereich/Tätigkeit: `arbeitsbereich`, `taetigkeit`,
  `ueberpruefungsintervall_monate`, `letzte_ueberpruefung_am`, `status` (Entwurf/Freigegeben/Überarbeitung),
  `verantwortlich`. **Frist-Ampel** `faelligkeitsStatus()` → rot (Fortschreibung überfällig), gelb (≤ 30 Tage),
  grün — **nur scharf wenn freigegeben** (Entwürfe haben keine Fortschreibungs-Frist). SSOT
  `offeneMassnahmen()` aggregiert offene Maßnahmen über **alle** Gefährdungen der GBU; `hatOffeneMassnahmen()`
  delegiert.
- **`Gefaehrdung`** — eine Gefährdung innerhalb der GBU: `faktor` (einer der 6 § 5 Abs. 3-Faktoren),
  `beschreibung`, **Risiko (Nohl-light 3×3)** `wahrscheinlichkeit` × `schwere` → `risikowert()` →
  `risikostufe()` (gering ≤ 2 / mittel 3–4 / hoch ≥ 6).
- **`Schutzmassnahme`** — eine Maßnahme zur Gefährdung: `typ` (TOP), `beschreibung`, `verantwortlich`, `frist`,
  `umgesetzt_am`, `wirksam_geprueft_am`. `istOffen()` = noch nicht umgesetzt.

## Service

- **`GbuFortschreiben`** — dokumentiert die Überprüfung: setzt `letzte_ueberpruefung_am` als **Max** (ein
  nachgetragenes älteres Datum setzt die Frist nicht zurück), Status → Freigegeben. Datum nicht in der Zukunft
  (`before_or_equal:today`).
- **`GbuMonitor`** — tenant-scoped Übersicht (Frist-Status aus den Model-Methoden, keine divergente Query),
  `ueberfaelligeAnzahl()`.

## Workflow

1. **GBU anlegen** (Arbeitsbereich, Tätigkeit, Überprüfungsintervall, Verantwortliche) — Status Entwurf.
2. **Gefährdungen erfassen** je Faktor (§ 5 Abs. 3) + Risiko-Einstufung (Wahrscheinlichkeit × Schwere).
3. **Maßnahmen festlegen** nach TOP-Hierarchie, mit Verantwortlichem und Frist.
4. **Freigeben** — startet die Fortschreibungs-Uhr (Frist-Ampel wird scharf).
5. **Umsetzen** (`umgesetzt_am`) und **Wirksamkeit prüfen** (`wirksam_geprueft_am`, § 3).
6. **Fortschreiben** bei Änderungen/Ablauf der Frist (Button → `GbuFortschreiben`).

## Spec

`docs/superpowers/specs/2026-06-08-gefaehrdungsbeurteilung-arbschg-design.md`
