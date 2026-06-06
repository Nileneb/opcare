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
| **Soziale Betreuung** | § 43b SGB XI, Biografiearbeit | (neu) SocialCare | 🔴 |
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
5. **Messaging-Querschnitt** — interne Nachrichten/Video.
6. **Lohn-/Steuerexport** — DEÜV/ELSTER-Adapter auf der Personalakte.

> Jede Stufe folgt demselben Muster: **Norm als Daten, Domäne isoliert, rollenbasiert sichtbar, im CI grün.**
> So wächst „Ein Altenheim, eine App" inkrementell — ohne je das Fundament anzufassen.
