# Vision: „Ein Altenheim, eine App"

**Stand:** 2026-06-06 · **Zweck:** Langfrist-Architektur, die es erlaubt, **Abteilung für Abteilung** und
**Norm für Norm** anzudocken, ohne das Fundament umzubauen. Kein Big-Bang — ein tragfähiges Gerüst.

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
| **Qualitätsmanagement** | DIN EN 15224, DIN EN ISO 9001, § 113 SGB XI, MD-QPR | Quality | 🟡 QS-Indikatoren da → **DIN/QM-Checkliste nächstes** |
| **Personal & Lohn** | Personalfragebogen, ArbZG, SGB IV (DEÜV/SV-Meldung), ELStAM | Personnel, Scheduling | ✅ Stammakte + Dienstplan |
| **Hauswirtschaft / Küche** | LMHV/HACCP, DIN 10506, LMIV (VO 1169/2011, Allergene), DGE-Qualitätsstandard | (neu) Catering | 🔴 — Allergien existieren schon (Bewohner) |
| **Haustechnik / FM** | DIN 31051 (Instandhaltung), DGUV V3, TrinkwV (Legionellen), Brandschutz | (neu) Facility | 🔴 — Reparatur-Tickets vom Bewohner |
| **Soziale Betreuung** | § 43b SGB XI, Biografiearbeit | (neu) SocialCare | 🔴 |
| **Verwaltung / Heimaufsicht** | Landesheim-/WTG, Heimmitwirkungs-VO, Pflegesatz § 85 SGB XI | (neu) Administration | 🔴 |
| **Geschäftsführung / Controlling** | Wirtschaftlichkeit, Vergütungsvereinbarung, Jahresberichte | Quality/Controlling-Ausbau | 🟡 KPIs da |

## Querschnitts-Fähigkeiten (eine App für alles)

| Fähigkeit | Norm/Treiber | Andockpunkt |
|---|---|---|
| **Arbeitszeit-Ist-Erfassung** | EuGH C-55/18 + BAG (Erfassungspflicht) | Scheduling: Ist neben dem Plan-Soll erfassen |
| **Interne Nachrichten / E-Mail** | DSGVO-konforme interne Kommunikation | (neu) Messaging-Querschnitt |
| **Videochat Kollegen** | — | Messaging (WebRTC) |
| **Steuer-/Lohnexport** | ELSTER / DATEV / DEÜV-Meldungen | Personnel → Export-Adapter (wie FHIR-Export) |
| **Reparatur-Tickets** | Mängelmeldung → Instandhaltung (DIN 31051) | Facility: Bewohner/Personal melden → Technik-Queue |
| **Allergen-Sicht Küche** | LMIV-Allergenkennzeichnung | Catering liest `ResidentAllergy` (rollenbasiert) |
| **Jahresreports GF** | Pflegestatistik, Belegung, Qualität | Controlling-Export (rollenbasiert) |

## Staged Roadmap (jede Stufe voll verdrahtet, kein totes Feature)

1. **DIN/QM-Checkliste im Quality-Modul** *(nächster konkreter Schritt)* — datengetriebene Norm-Checkliste
   (DIN EN 15224 / MD-QPR), wie die ArbZG-Regel-Engine: Anforderung → Nachweis/Status → Audit. **Nebenprodukt:
   die vollständige Abteilungs- + Pflichtdaten-Liste** entsteht aus den Norm-Anforderungen selbst.
2. **Facility-Tickets** — Bewohner/Personal melden Mängel → Haustechnik-Queue (DIN 31051).
3. **Catering** — Speiseplan + Allergen-/Kostform-Sicht (LMIV), liest vorhandene Allergien.
4. **Arbeitszeit-Ist-Erfassung** — Stempeln gegen den Plan (EuGH/BAG), füttert den ArbZG-§3-Schnitt.
5. **Messaging-Querschnitt** — interne Nachrichten/Video.
6. **Lohn-/Steuerexport** — DEÜV/ELSTER-Adapter auf der Personalakte.

> Jede Stufe folgt demselben Muster: **Norm als Daten, Domäne isoliert, rollenbasiert sichtbar, im CI grün.**
> So wächst „Ein Altenheim, eine App" inkrementell — ohne je das Fundament anzufassen.
