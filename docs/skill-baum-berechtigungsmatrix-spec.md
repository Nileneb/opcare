# Spezifikation: Skill-Baum, Berechtigungsmatrix, Delegation & Beauftragten-Register

Recherche + Design (2026-06-06), noch **nicht implementiert**. „Wer darf was" ist in Deutschland exakt geregelt
(Vorbehaltsaufgaben, Behandlungspflege LG1/LG2, Delegation ärztlicher Tätigkeiten) und überall gibt es benannte,
qualifizierte, regelmäßig geschulte „Beauftragte"/„befähigte Personen" (vom Hygiene- bis zum Leiterbeauftragten).
Diese Spec führt die drei Stränge zusammen und verankert sie in vorhandenen opcare-Bausteinen:
**Nachweis-mit-Frist** (Arbeitsschutz, Strang C → Fälligkeits-Ampel) und **Datei-Upload/Freigabe** (Strang A → Qualifikationsnachweise).

## 1. Rechtliche Leitplanken (recherchiert, quellengestützt)

- **Vorbehaltsaufgaben (§ 4 PflBG)**: Pflegebedarfs-Erhebung, Pflegeprozess-Steuerung und Qualitätssicherung dürfen
  **ausschließlich Pflegefachkräfte** (3-jährige Ausbildung). Folge: **SIS/Pflegeplanung/Assessments nur von einer
  Pflegefachkraft abzeichenbar** — Hilfskräfte dürfen befüllen, nicht verantwortlich signieren.
- **Behandlungspflege LG1/LG2 (§ 132a SGB V)**: LG1 (z. B. orale Medikation, BZ-Messung, Kompression) und LG2
  (s. c.-Injektion/Insulin, einfache Wundversorgung, Katheter-/PEG-Pflege) durch Hilfskräfte nur mit **LG1/LG2-Schein**
  (~160–186 UE) + Delegation. Stationär restriktiver als ambulant, aber materielle Qualifikation (Schulung/Einweisung) gilt analog.
- **Delegation ärztlicher Tätigkeiten**: **Anordnungsverantwortung (Arzt)** vs. **Durchführungsverantwortung (Pflege)**
  + **Übernahmeverschulden**. Delegierbar (mit Nachweis): Injektionen, Blutentnahme, Verbandwechsel, Katheter, PEG;
  nicht delegierbar: Diagnose, Aufklärung, Erstpunktion Port, Erstanlage suprapubischer Katheter.
- **Vier Verb-Rollen** je Tätigkeit: **anordnen** (Arzt bzw. PF bei Pflegeprozess) · **delegieren** (Arzt→PF, PF→PA/HK)
  · **durchführen** (qualifikationsabhängig + materielle Eignung) · **abzeichnen**.
- **Beauftragte/befähigte Personen**: jede Pflicht hat eine benannte, qualifizierte Person mit Auffrischungsintervall —
  Hygiene (§ 35 IfSG, 80 UE), Brandschutz (vfdb 12-09/01, 16 UE/3 J.), Sicherheitsbeauftragte (§ 22 SGB VII ab 20 MA),
  Ersthelfer (DGUV V1, 10 %/2 J.), Datenschutz (Art. 37 DSGVO ab Tag 1), Medizinproduktesicherheit (§ 6 MPBetreibV),
  Praxisanleiter (§ 4 PflAPrV, 300 UE + 24 UE/Jahr), HACCP/§ 43 IfSG (Küche), **Elektrofachkraft + Leiterbeauftragte:r
  (DGUV 208-016) + befähigte Person (TRBS 1203)** (Haustechnik). Detail-Katalog siehe Recherche-Anhang/Wiki.

## 2. Datenmodell (Vorschlag)

### 2.1 Skill-Baum: Kompetenz-Katalog
`kompetenzen` (Katalog je Einrichtung, datengetrieben — wie ArbZG/QM):
`key, name, typ (grundberuf|weiterbildung|interne_schulung), rechtsbasis, anbieter_norm, umfang_stunden,
gueltigkeit_monate (null=unbefristet), auffrischung_monate (null=keine), aktiv`
+ `kompetenz_voraussetzungen` (DAG, zyklenfrei): `kompetenz_id`, `voraussetzung_id` (z. B. „Wundexperte ICW" setzt „Pflegefachkraft" voraus).

### 2.2 Mitarbeiter-Kompetenz (mit Frist → Ampel)
`mitarbeiter_kompetenzen`: `tenant_id, user_id, kompetenz_id, erworben_am, gueltig_bis (berechnet), nachweis_media_id
(Strang A), verifiziert_von, status (aktiv|faellig|abgelaufen)`. **Fälligkeit/Ampel = derselbe Mechanismus wie
`Schutznachweis` (Strang C)** — grün/gelb(≤30/60 T.)/rot. Abgelaufene Voraussetzung sperrt abgeleitete Tätigkeiten automatisch.

### 2.3 Tätigkeitskatalog + Berechtigungsmatrix
`taetigkeiten`: `key, label, bereich (pflege|medikation|haustechnik|kueche|…), mindest_qualifikation
(hilfskraft|assistenz|fachkraft), erforderliche_kompetenz_id (nullable), delegierbar (bool), delegierbar_an
(level), vorbehaltsaufgabe (bool, §4 PflBG), arzt_anordnung_noetig (bool)`.
Service `Befugnis::darfDurchfuehren(User, Taetigkeit): bool|Grund` prüft: Grundqualifikation ≥ Mindest **und** (falls
gefordert) aktive Zusatzkompetenz **und** (falls `arzt_anordnung_noetig`/delegationspflichtig) eine gültige Delegation.
Beispielzeilen (aus der Recherche-Matrix, 25 Tätigkeiten): SIS abzeichnen → Vorbehalt/Fachkraft; s. c.-Insulin → Assistenz
+ Kompetenz „SC-Injektion" + Delegation; i. v.-Injektion → nur Fachkraft + Arzt-Anordnung; komplexe Wunde → Fachkraft (+ICW).

### 2.4 Delegation (wiederverwendbar, domänenübergreifend)
`delegationen`: `tenant_id, anordner_id (Arzt/Betreiber), nehmer_id (Mitarbeiter), taetigkeit_id,
bezug_type/bezug_id (polymorph: Bewohner ODER Anlage/Gerät), delegiert_am, gueltig_bis, qualifikationsnachweis_media_id,
einweisung_von, widerruf_am, widerruf_grund`.
Genau dasselbe Modell trägt **Pflege** (Arzt delegiert Blutabnahme an PF, bewohnerbezogen) **und Haustechnik**
(Betreiber benennt EFK/„befähigte Person" für eine Anlage) **und Küche** (HACCP-Verantwortung). Status-Ampel an `gueltig_bis`.
ePA: später als FHIR `ServiceRequest` (requester/performer) exportierbar — die ePA selbst hat kein Delegations-Objekt.

### 2.5 Beauftragten-Register (befähigte/benannte Personen)
`beauftragten_rollen` (Katalog): `key, name, rechtsbasis, pflicht (bool), schwelle (Freitext), bereich,
qualifikation_text, auffrischung_monate`.
`beauftragten_bestellungen`: `tenant_id, rolle_id, user_id, bestellt_am, gueltig_bis (berechnet),
nachweis_media_id, abbestellt_am`. **Fälligkeits-Ampel + Compliance-Gate** (z. B. Ersthelfer-Quote 10 % unterschritten →
Warnung; DSB/BfMS fehlt → Warnung). Export als „Beauftragten-Register"-PDF für Heimaufsicht/MD.

## 3. Integration in vorhandene Module

- **SIS/Pflegeplanung/Assessment**: Abzeichnen-Guard `vorbehaltsaufgabe ⇒ nur Pflegefachkraft` (§ 4 PflBG).
- **Medikation/BtM**: Gabe/Abzeichnen prüft Berechtigung; BtM behält Arzt-Monatsprüfung.
- **Auto-Dienstplan + Tauschbörse**: heutiges grobes „Fachkraft ja/nein" wird durch echte Kompetenzen scharf — eine
  Schicht/Aufgabe kann eine Kompetenz fordern (z. B. „Wundvisite") → nur passende Kräfte planbar/übernahmeberechtigt.
- **Arbeitsschutz-Nachweise (Strang C)** + **Beauftragten-Register**: teilen die Fälligkeits-Ampel; ggf. später vereinheitlichen.
- **Fortbildung**: `mitarbeiter_kompetenzen` + geplante/gewünschte Fortbildungen (Fortbildungsplan/-wünsche) docken hier an.

## 4. Wiederverwendbares Muster

Dieselbe Struktur „**Katalog (Kompetenz/Rolle/Tätigkeit) → Person-Zuordnung mit Frist → Berechtigungs-/Delegations-Prüfung**"
deckt Pflege, Haustechnik (EFK/Leiter), Küche (HACCP) und Verwaltung (DSB) ab — ein Modell, viele Domänen. Es nutzt die
bereits vorhandenen Querschnitts-Bausteine: **Nachweis-mit-Frist** (Ampel) und **Datei-Upload** (Nachweis-Dokumente).

## 5. Umsetzungs-Reihenfolge (Vorschlag)

1. **Kompetenz-Katalog + Mitarbeiter-Kompetenz** (Skill-Baum, Voraussetzungen, Fristen-Ampel) — Personalakte-Erweiterung.
2. **Tätigkeitskatalog + Berechtigungsmatrix** + `Befugnis`-Service; Guard zuerst dort, wo es rechtlich hart ist (SIS-Vorbehalt, Behandlungspflege/BtM).
3. **Delegationsverwaltung** (generisch, Pflege zuerst, dann Haustechnik EFK/Leiter).
4. **Beauftragten-Register** + Compliance-Gate + MD-Export.
5. **Fortbildungsplan/-wünsche** an die Kompetenzen angedockt.

Quellen: siehe Recherche-Stränge (PflBG § 4, § 132a SGB V, BtMVV § 13, MPBetreibV § 6, IfSG § 35/43, DGUV V1/V3,
TRBS 1203, DGUV 208-016, § 4 PflAPrV, ICW/DGS/DGP-Curricula). Vollständige Quellen-URLs im Commit/PR der Recherche.
