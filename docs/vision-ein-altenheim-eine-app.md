# Vision: „Ein Altenheim, eine App"

**Stand:** 2026-06-06 · **Zweck:** Langfrist-Architektur, die es erlaubt, **Abteilung für Abteilung** und
**Norm für Norm** anzudocken, ohne das Fundament umzubauen. Kein Big-Bang — ein tragfähiges Gerüst.

## Zielstatus: „Alle DIN-Normen & Abteilungen abbildbar"

**Strukturell erreicht.** Jede Abteilung und jede Norm lässt sich in opcare abbilden — über zwei Wege, die
beide stehen:

1. **Universeller Mechanismus — die [QM-Norm-Checkliste](#).** Sie deckt **alle** Bereiche ab (12 `QmBereich`:
   QPR QB1–6 + Hygiene/IfSG, Datenschutz/DSGVO, Arbeits-/Brandschutz, Hauswirtschaft/LMIV, Haustechnik/DIN 31051,
   Verwaltung/Heimrecht). **Jede weitere Norm = ein Katalog-Eintrag** (`QmKatalogDefaults`) mit Gesetzeslink,
   Nachweis-Status und Zuständigkeit. Damit ist *jede* DIN/Vorschrift sofort als nachweisbare Anforderung darstellbar.
2. **Operative Tiefe — eigene Module**, wo es klares Outcome gibt: Pflege/SIS, FHIR/ÜLB, QDVS/DAS,
   Dienstplan + ArbZG, Arbeitszeiterfassung (BAG/EuGH), Personalakte, **Haustechnik (DIN 31051)**, **Küche (LMIV)**.

Neue Normen/Abteilungen kosten dadurch **einen Katalog-Eintrag** (Nachweis) bzw. **eine Domäne + Kataloge +
Rollen-Sichtbarkeit** (operativ) — nie ein Re-Design. Der Ausbau läuft inkrementell weiter (s. Roadmap unten).

## Leitprinzip: Bürokratie ist die Spezifikation

Deutschland hat jede Abteilung, jede Pflicht und jedes Formular bis ins Detail reguliert. **Wir erfinden
nichts — wir adoptieren die Norm als Daten.** Dasselbe Muster, das opcare schon trägt:

- **FHIR / ÜLB-MIO** → Datenkonformität (Pflegeüberleitung)
- **ArbZG** → editierbares Regelwerk + Gesetzeslinks (Dienstplan)
- **DAS-Pflege / QDVS** → datengetriebene Plausibilitäts-Engine
- **Personalfragebogen** → Personalakte-Felder

Jede neue Abteilung = (1) Norm finden, (2) als Katalog/Regelwerk in eine Domäne gießen, (3) rollenbasiert
sichtbar machen. Immer gleich. „Abschreiben" ist hier Best Practice.

## Architektur-Fundament (steht bereits)

| Baustein | Status | Trägt künftig |
|---|---|---|
| **Bounded Contexts** (`app/Domains/*`) | ✅ | jede Abteilung = eigene Domäne, isoliert + wiederverwendbar |
| **Rollen je Mandant** (spatie) + **Personalakte am User** | ✅ | jede Abteilung sieht ihren Ausschnitt (Küche↔Allergien, Technik↔Tickets, GF↔Reports) |
| **Mandantentrennung** (`tenant_id` + Global Scope) | ✅ | jede neue Tabelle ist automatisch mandantensicher |
| **At-Rest-Feldcrypto + Audit-Log** | ✅ | sensible Daten (Lohn, Gesundheit) by default geschützt |
| **Datengetriebene Kataloge + editierbare Regel-Engines** | ✅ | Norm-Checklisten/Prüfregeln als Daten statt Code |
| **Standard-Adoption als CI-Gate** (HL7-Validator) | ✅ | jede Konformität wird messbar grün gehalten |

→ Eine neue Abteilung kostet **eine Domäne + ein paar Kataloge + Rollen-Sichtbarkeit** — nicht ein Re-Design.

## Abteilungs-Landkarte (Norm → Domäne → Daten)

| Abteilung | Maßgebliche Normen/Quellen | opcare-Domäne | Status |
|---|---|---|---|
| **Pflege** | SGB XI, DNQP-Expertenstandards, Strukturmodell/SIS®, MD-QPR | CarePlanning, Assessment, Masterdata | ✅ Kern |
| **Pflege-Datenaustausch** | FHIR R4, KBV-MIO ÜLB | Fhir | ✅ |
| **Qualitätsmanagement** | DIN EN 15224, § 113 SGB XI, MD-QPR | Quality | ✅ QS-Indikatoren + **QM-Norm-Checkliste** (QB1–6 + Querschnittsnormen) |
| **Personal & Lohn** | Personalfragebogen, ArbZG, SGB IV (DEÜV/SV-Meldung), ELStAM | Personnel, Scheduling | ✅ Stammakte + Dienstplan |
| **Hauswirtschaft / Küche** | LMHV/HACCP, DIN 10506, LMIV (VO 1169/2011, Allergene), DGE-Qualitätsstandard | Catering | ✅ Diät-/Allergen-Sicht + Speiseplan mit Allergenwarnung |
| **Haustechnik / FM** | DIN 31051 (Instandhaltung), DGUV V3, TrinkwV (Legionellen), Brandschutz | Facility | ✅ Mängelmeldungen + Wartungsplan (Prüffristen) |
| **Soziale Betreuung** | § 43b SGB XI, Biografiearbeit | SocialCare | ✅ Angebote + Teilnahme-Nachweis je Bewohner |
| **Verwaltung / Finanzen** | HGB §§ 238 ff. (doppelte Buchführung), PBV (Pflege-Buchführungsverordnung), § 85 SGB XI | Accounting | ✅ Soll-Haben-Buchführung + Warenwirtschaft (Lager→Aufwand je Abteilung) |
| **Verwaltung / Heimaufsicht** | Landesheim-/WTG, Heimmitwirkungs-VO, Pflegesatz § 85 SGB XI | (neu) Administration | 🔴 |
| **Geschäftsführung / Controlling** | Wirtschaftlichkeit, Vergütungsvereinbarung, Jahresberichte | Quality/Controlling-Ausbau | 🟡 KPIs da |

## Querschnitts-Fähigkeiten (eine App für alles)

| Fähigkeit | Norm/Treiber | Andockpunkt |
|---|---|---|
| **Arbeitszeit-Ist-Erfassung** ✅ | EuGH C-55/18 + BAG (Erfassungspflicht) | Scheduling: Kommen/Gehen + Ist-vs-Soll |
| **Interne Nachrichten / E-Mail** | DSGVO-konforme interne Kommunikation | (neu) Messaging-Querschnitt |
| **Videochat Kollegen** | — | Messaging (WebRTC) |
| **Steuer-/Lohnexport** | ELSTER / DATEV / DEÜV-Meldungen | Personnel → Export-Adapter (wie FHIR-Export) |
| **Reparatur-Tickets** | Mängelmeldung → Instandhaltung (DIN 31051) | Facility: Bewohner/Personal melden → Technik-Queue |
| **Allergen-Sicht Küche** | LMIV-Allergenkennzeichnung | Catering liest `ResidentAllergy` (rollenbasiert) |
| **Jahresreports GF** | Pflegestatistik, Belegung, Qualität | Controlling-Export (rollenbasiert) |
| **Warenwirtschaft ↔ Buchhaltung** ✅ | HGB-Buchführung, PBV | Accounting: Wareneingang/Verbrauch je Abteilung bucht automatisch Soll/Haben |
| **Dienstwunsch-Abgabe** ✅ | Mitbestimmung/Planung (Vorschlagscharakter) | Scheduling: Mitarbeiterwünsche werden dem PDL beim Dienstplan eingeblendet |
| **Essenswünsche + Menüwahl** ✅ | Wahlrecht Bewohner, DGE-Verpflegung | Catering: Bewohnerwünsche jederzeit sichtbar, Menüwahl je Mahlzeit |

## Staged Roadmap (jede Stufe voll verdrahtet, kein totes Feature)

1. ✅ **DIN/QM-Checkliste im Quality-Modul** *(erledigt 2026-06-06)* — datengetriebene Norm-Checkliste
   (QPR-Qualitätsbereiche QB1–6 + Hygiene/IfSG, Datenschutz, Arbeitsschutz, Hauswirtschaft/LMIV,
   Haustechnik/DIN 31051, Heimrecht), wie die ArbZG-Regel-Engine: Anforderung → Nachweis/Status → Erfüllungsgrad,
   Gesetzeslink je Anforderung. **Nebenprodukt erreicht:** die `QmBereich`-Enum **ist** die Abteilungs-Landkarte,
   die Anforderungen benennen die zu führenden Daten/Nachweise.
2. ✅ **Facility-Tickets** *(erledigt 2026-06-06)* — Personal meldet Mängel → Haustechnik-Queue
   (offen → in Arbeit → erledigt) + Wartungsplan mit Prüffristen (DIN 31051). Macht den QM-Punkt
   `ht_instand` operativ; das Meldung/Ticket-Muster ist für weitere Module wiederverwendbar.
3. ✅ **Catering** *(erledigt 2026-06-06)* — Küche sieht Lebensmittelallergien + Kostformen (aus vorhandenen
   Daten) + Speiseplan mit LMIV-Allergenkennzeichnung; je Gericht Warnung betroffener Bewohner. Macht den
   QM-Punkt `hw_allergene` operativ.
4. ✅ **Arbeitszeit-Ist-Erfassung** *(erledigt 2026-06-06)* — Kommen/Gehen stempeln + manuelle Erfassung;
   Wochen-Ist gegen das geplante Dienstplan-Soll, Team-Übersicht für die Leitung (EuGH/BAG-Erfassungspflicht).
5. ✅ **Wunschdienstplan** *(erledigt 2026-06-06)* — Mitarbeitende geben je Woche Dienstwünsche ab
   (Frei/Arbeiten/Nicht verfügbar, reiner Vorschlagscharakter); der PDL sieht sie als Badge direkt im
   Dienstplan-Raster beim Erstellen. Kein Genehmigungs-Workflow — bewusst nur Entscheidungshilfe.
6. ✅ **Essenswünsche + Menüwahl** *(erledigt 2026-06-06)* — die Küche sieht allgemeine Bewohnerwünsche
   (Vorliebe/Abneigung) jederzeit und stellt einen Speiseplan mit mehreren Gerichten je Mahlzeit vor;
   je Bewohner wird eine Menüwahl pro Mahlzeit festgehalten. Ergänzt den Catering-Speiseplan.
7. ✅ **Buchhaltung + Warenwirtschaft** *(erledigt 2026-06-06)* — doppelte Buchführung (Soll/Haben, Saldo
   je Kontoart) mit Standard-Kontenrahmen je Einrichtung; die Lagerwirtschaft der Abteilungen ist verdrahtet:
   **Wareneingang** bucht Soll Warenbestand an Haben Verbindlichkeiten, **Verbrauch** Soll Abteilungs-Aufwand
   an Haben Warenbestand — so schlägt jeder Materialfluss automatisch in der Finanzbuchhaltung durch.
   Unterbestand wird je Artikel markiert. Siehe [Buchhaltung & Warenwirtschaft](buchhaltung-warenwirtschaft.md).
8. **Messaging-Querschnitt** — interne Nachrichten/Video.
9. **Lohn-/Steuerexport** — DEÜV/ELSTER-Adapter auf der Personalakte.

> Jede Stufe folgt demselben Muster: **Norm als Daten, Domäne isoliert, rollenbasiert sichtbar, im CI grün.**
> So wächst „Ein Altenheim, eine App" inkrementell — ohne je das Fundament anzufassen.

## Recherche-Backlog (2026-06-06): vertiefte Norm-Erweiterung

Tiefenrecherche zu den noch unvollständigen Bereichen → [recherche-normen-erweiterung-2026-06.md](recherche-normen-erweiterung-2026-06.md)
(quellengestützt, adversarial geprüft). Sechs Stränge mit konkreten Spezifikationen für die spätere Umsetzung:

1. **Arbeitsschutz** (materiell, über ArbZG hinaus) — Gefährdungsbeurteilung inkl. psych. Belastung, Vorsorgekartei
   (ArbMedVV), Unterweisungs-Fristen, BEM (§167 SGB IX), Mutterschutz im Dienstplan, Gefahrstoffverzeichnis, Brandschutz-/Ersthelfer-Schicht-Check.
2. **Gesundheitsförderung** — BGM/BGF (§20b SGB V, steuerfrei) + **§5 SGB XI Bewohner-Prävention** (von der Pflegekasse mitfinanziert → Erlösquelle).
3. **Personalbemessung §113c (PeBeM)** — Betreuungsschlüssel im Dienstplan: PAW-Tabelle (PG-Mix × Qualifikation → VZÄ),
   Soll-vs-Ist-Ampel je Schicht; alles bundesland-/trägerspezifisch überschreibbar.
4. **Positive Schichtregeln** — zweite Engine nach ArbZG: Vorwärtsrotation, Freiblöcke, Wochenend-Gerechtigkeit,
   Quick-Return-Vermeidung (12 datengetriebene Default-Regeln, §6 ArbZG / BAuA / BGHM).
5. **Datei-/Foto-Upload (MinIO)** — Enabler für viele Audit-Punkte: Bucket-pro-Tenant, Presigned URLs, Einwilligungs-/Löschkonzept
   (Art. 9 DSGVO, §22 KUG, §630f BGB-Fristen).
6. **Vollständigkeits-Audit** — Top-10-Lücken: BtM-Nachweis (BtMVV), FEM-Genehmigung (§1831 BGB), Qualitätsindikatoren-Export (§113b),
   Medizinprodukte (MPBetreibV), Datenschutz operativ (VVT/AVV), Barbetragsverwaltung (§27b SGB XII), Beschwerde/Gewaltschutz, Hygiene/MRE, Evakuierungsklassen, Heimbeirat/WBVG.

**Vier wiederkehrende Bau-Muster** (einmal bauen, vielfach nutzen): *Nachweis-mit-Frist* · *Dokument-mit-Version+Freigabe* ·
*Genehmigungs-/Melde-Workflow mit Behörden-Frist* · *datengetriebene Wert-/Regel-Kataloge*.

### Umsetzungsstand der Recherche-Stränge (2026-06-06)

- ✅ **A — Datei-/Foto-Upload (MinIO-fähig)** — Bewohner-Dokumente/Fotos hochladen + protokolliert freigeben
  ([dokumente-dateien.md](dokumente-dateien.md)).
- ✅ **B — Betreuungsschlüssel (§113c) + ergonomische Schichtregeln** — Soll-vs-Ist-Ampel + zweite Schicht-Engine im Dienstplan
  ([betreuungsschluessel-schichtregeln.md](betreuungsschluessel-schichtregeln.md)).
- ✅ **C — Nachweis-mit-Frist (Arbeitsschutz)** — Unterweisung/Vorsorge/Erste Hilfe/Brandschutz/BEM mit Fälligkeits-Ampel
  ([arbeitsschutz-nachweise.md](arbeitsschutz-nachweise.md)).
- ✅ **D — §5 SGB XI Bewohner-Prävention** — kassenfinanzierte Programme + Verwendungsnachweis ([praevention-sgb-xi.md](praevention-sgb-xi.md)).
- 📋 **E — BtM-Nachweis (BtMVV) + FEM-Genehmigung (§1831 BGB)** — als Konzept dokumentiert, Umsetzung folgt
  ([e-btm-fem-konzept.md](e-btm-fem-konzept.md)).
