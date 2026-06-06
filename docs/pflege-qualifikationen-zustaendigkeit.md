# Pflege-Qualifikationen: Zuständigkeit, reglementierte Berufe & Skill-Baum-Vervollständigung

Recherche (2026-06-06) als Grundlage für den erweiterten Pflege-Skill-Baum (`KompetenzDefaults`).

## 1. Zuständigkeit — Richtigstellung der IHK-Annahme

Die **IHK ist NICHT zuständig** für Pflege-Qualifikationen. Korrekte Zuständigkeiten:

| Qualifikation | Zuständige Stelle |
|---|---|
| **Pflegefachfrau/-mann** (Grundberuf) | **PflBG + PflAPrV (Bund)**; staatliche Prüfung durch **Landesbehörden** (Bezirksregierungen) — kein IHK-Bezug |
| Pflege(fach)assistenz | Landesrecht (bundeseinheitlich ab 01.01.2027 via PflAssEinfG) |
| PDL / verantw. Pflegefachkraft | § 71 SGB XI (≥ 460 h) + Landes-Weiterbildungs-VOs |
| DKG-Fachweiterbildungen (Intensiv, Onkologie, Psychiatrie, Hygiene …) | DKG-Empfehlungen + anerkannte Weiterbildungsstätten / Landesbehörden |
| Fachgesellschafts-Zertifikate (Wundexperte ICW, Pain Nurse DGS, Palliative Care DGP, Diabetes DDG) | jeweilige **Fachgesellschaft** |
| Berufsregister/Fortbildungspflicht | **Pflegekammern** (RLP, NRW; Bayern: VdPB-Register) — nicht flächendeckend |
| **Fachwirt:in / Betriebswirt:in Gesundheits- und Sozialwesen, AEVO** | **IHK** (BBiG) — kaufmännisch-organisatorische Aufstiegsfortbildung, NICHT Pflegefachkompetenz |

**Fazit:** IHK ist nur für die kaufmännische Leitungs-/Verwaltungsschiene relevant (als optionale Zusatzqualifikation
im Skill-Baum: `fachwirt_gesundheit_ihk`). Die Pflege-Grundberufe und -Fachweiterbildungen liegen bei Bund/Ländern/DKG/Fachgesellschaften.

## 2. „Geschützte Berufe" → korrekt: reglementierte Berufe

Fachterminus ist **„reglementierte Berufe"** (EU-RL 2005/36/EG, BQFG). Pflegefachfrau/-mann ist **berufszulassungs- und
bezeichnungsgeschützt**. Es gibt **keine einzelne, maschinenlesbare „Berechtigungstabelle" Tätigkeit↔Beruf**. Quellen,
aus denen sich „wer darf was" zusammensetzt:
- **§ 4 PflBG** — Vorbehaltsaufgaben (Pflegeprozess) der Pflegefachkraft.
- **G-BA HKP-Richtlinie** (§ 92 SGB V) + Blankoverordnung (seit 7/2024).
- **G-BA Heilkundeübertragungs-RL** (§ 63 Abs. 3c SGB V, Modellvorhaben — kaum umgesetzt).
- **BEEP-Gesetz (ab 01.01.2026)** — eigenständige Heilkunde der Pflege für **Wundversorgung, Diabetes, Demenz**
  (ersetzt das Modellvorbehaltssystem); Qualifikationsweg: Pflegestudium oder Ausbildung + Weiterbildung.
- **§ 132a SGB V** Rahmenempfehlungen (LG1/LG2) · **Landesrecht** (Delegation/Pflegehelfer).

**Offizielle Datenbanken** (keine direkt nutzbare API für Heilberufe): EU Regulated Professions Database (kein API),
BQ-Portal (deckt Heilberufe nicht ab), anabin (Abschluss-Äquivalenz), anerkennung-in-deutschland.de (Recherche).
→ **Konsequenz:** Der Berechtigungs-/Skill-Katalog wird in opcare **kuratiert** gepflegt (datengetriebene Defaults),
nicht aus einer amtlichen API gezogen. Einzige strukturierte Primärquelle: die Gesetzestexte (gesetze-im-internet.de).

## 3. Skill-Baum-Vervollständigung (umgesetzt)

`KompetenzDefaults` wurde um die gängigen anerkannten Qualifikationen erweitert (zusätzlich zu den Basis-Einträgen):
- **B.Sc. Pflege mit heilkundlicher Kompetenz** (Grundberuf, BEEP-relevant).
- **DKG-Fachweiterbildungen**: Geriatrie/Gerontopsychiatrie, Intensiv & Anästhesie, Onkologie, Psychiatrie,
  Hygienefachkraft, Stationsleitung.
- **Fachgesellschaften**: Validation (Feil), Diabetes-Pflegefachkraft + Diabetesberater:in DDG, Ernährungsmanagement
  (DGEM), Bobath (BIKA, Grund-/Aufbaukurs), Case Management (DGCC).
- **DNQP-Beauftragte**: Sturz-, Kontinenz-, Schmerzbeauftragte:r.
- **Leitung/IHK**: Einrichtungsleitung, **Fachwirt:in Gesundheits- und Sozialwesen (IHK)**; BLS-Notfalltraining.

Voraussetzungen sind als DAG verdrahtet (z. B. Diabetesberater:in → Diabetes-Pflegefachkraft; Bobath-Aufbau → -Grundkurs;
die meisten Weiterbildungen → Pflegefachkraft). Der Katalog bleibt je Einrichtung erweiterbar.

**Nächster optionaler Schritt:** die BEEP-Tätigkeiten (eigenständige Wunde/Diabetes/Demenz ab 2026) als eigene
Tätigkeiten in die Berechtigungsmatrix aufnehmen, freigeschaltet durch `bsc_pflege_heilkundlich` bzw. die Fach-Weiterbildung.

Quellen: PflBG/PflAPrV, § 71 SGB XI, DKG-Empfehlung pflegerische Weiterbildung, DDG/DGCC/DGEM/BIKA-Curricula,
G-BA HKP-/§63-RL, BEEP-Gesetz (BMG), EU-RL 2005/36/EG — URLs in den Recherche-Strängen des PRs.
