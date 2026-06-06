# Recherche: Norm-Erweiterung opcare (Stand 2026-06-06)

Recherche-Grundlage für den weiteren operativen Ausbau. Sechs parallele Recherche-Stränge
(Arbeitsschutz, Gesundheitsförderung/BGM, Personalbemessung, Schichtplan-Ergonomie, Datei-/Foto-Management,
Vollständigkeits-Audit) — jeweils web-recherchiert (2024–2026), quellengestützt, adversarial gegengeprüft.
**Dies ist eine Spezifikations-/Backlog-Quelle, noch keine Implementierung.** Umsetzung inkrementell nach
demselben Muster wie bisher: *Norm als Daten · Domäne isoliert · rollenbasiert sichtbar · im CI grün*.

> Föderalismus-Hinweis (gilt querschnittlich): Heimrecht, Hygienebeauftragte-Qualifikation, Nachtdienst-
> Schlüssel und Fachkraftquoten sind **landesrechtlich** (PfleWoqG BY, WTG NRW, WTPG BW, WTG Berlin …).
> Alle Default-Werte unten müssen mandanten-/bundesland-spezifisch überschreibbar bleiben.

---

## 1. Arbeitsschutz (über Arbeitszeit/Urlaub hinaus)

Bisher abgebildet: nur ArbZG (Arbeitszeit) + Urlaub. Es fehlt der gesamte materielle Arbeitsschutz.
Zuständige BG = **BGW** (Gesundheitsdienst/Wohlfahrtspflege), Pflichtmitgliedschaft.

| Norm | Pflicht | App-Abbildung |
|---|---|---|
| **ArbSchG §5/§6** | Gefährdungsbeurteilung je Arbeitsbereich/Tätigkeit, **inkl. psychischer Belastung** (gleichrangig seit 2013), Doku + Wirksamkeitskontrolle, Review ≥ alle 2 J. | GB-Katalog wie unsere QM-Checkliste; BGW-Vorlagen (7 Gefährdungskategorien stationäre Pflege); Status erstellt/Maßnahme offen/Wirksamkeit geprüft + Wiedervorlage |
| **ArbSchG §12 + DGUV V1 §4** | Unterweisung mind. jährlich (Einstellung/Wechsel/neue Technik), schriftlich mit Unterschrift | Unterweisungs-Nachweis je MA mit Thema/Intervall/Fälligkeit + Erinnerung; Themen-Bibliothek mit einstellbaren Intervallen |
| **ArbMedVV** | Arbeitsmedizinische Vorsorge: Pflicht-/Angebots-/Wunschvorsorge; **Vorsorgekartei** (Anlass/Datum/Bestätigung) | Vorsorgekartei je MA mit Anlass-Kategorie, nächster Fälligkeit, Ampel + Erinnerung; Nachtarbeit aus Dienstplan → Angebotsvorsorge automatisch |
| **BioStoffV + TRBA 250** | Schutzstufen-Einstufung der Tätigkeiten (Grundpflege = Stufe 2), Hygieneplan, PSA, **Impfangebote** (Hep B etc.), Nadelstich-Protokoll | Tätigkeitskatalog mit Schutzstufe; Impfstatus je MA; Nadelstich-Meldeworkflow (D-Arzt) |
| **GefStoffV + TRGS 525/401** | **Gefahrstoffverzeichnis** (Desinfektionsmittel etc.), Betriebsanweisung je Stoff, jährl. Unterweisung; Hautschutzplan (Feuchtarbeit) | Gefahrstoffverzeichnis-Modul (CLP/H-P-Sätze, SDB-Upload); Betriebsanweisungs-Generator; Hautschutzplan |
| **MuSchG §10/§13** | Mutterschutz-Gefährdungsbeurteilung; verbotene Tätigkeiten (Heben > 5 kg, Nachtarbeit, Biostoffe Risikogr. 2/3) | Status in Personalakte + **Dienstplan-Sperre** für verbotene Tätigkeiten; Mutterschutzfristen |
| **ASiG + DGUV V2** | Betriebsarzt + Fachkraft für Arbeitssicherheit (Sifa) bestellen; ab 01.01.2026 Mindestbetreuung 20 % | Stammdaten Betriebsarzt/Sifa + Besuchsprotokoll + Betreuungszeit-Tracking |
| **§167 SGB IX (BEM)** | BEM-Angebot Pflicht bei **> 6 Wochen AU in 12 Monaten**; ohne BEM ist krankheitsbedingte Kündigung i. d. R. unwirksam | **BEM-Trigger** aus AU-Tagen (Dienstplan) → 42-Tage-Schwelle; Einladung + Protokoll; separater Zugriffsbereich (nur HR/Leitung) |
| **PSA-BV + LasthandhabV** | PSA aus GB ableiten + bereitstellen; Lastenhandhabung (Transfer/Lagerung) → technische Hilfsmittel (Lifter), Kinaesthetics | PSA-Checkliste je Tätigkeit; Hilfsmittelregister; Schulungsnachweis rückengerechtes Arbeiten |
| **ASR (A4.2/A4.1/A3.5/A3.4)** | Pausen-/Bereitschaftsräume, Sanitär, Temperatur/Beleuchtung | ASR-Abschnitt der GB; Messprotokolle |
| **ASR A2.2 + DGUV V1 §25** | **Brandschutzhelfer** (5–10 %/Schicht, Pflege erhöht) + **Ersthelfer** (BGW: ~10 %/Schicht) + Verbandbuch | Register Brandschutz-/Ersthelfer je MA mit Auffrischung; **Schicht-Ampel**: ist jede Schicht qualifiziert besetzt? Digitales Verbandbuch |

**Top-Kandidaten (Nutzen × Machbarkeit aus Personalakte/Dienstplan/Facility):**
1. Vorsorgekartei (ArbMedVV) · 2. Unterweisungs-Fristmanagement (§12) · 3. Gefährdungsbeurteilung inkl. psych. Belastung (§5/§6) ·
4. BEM-Trigger (§167 SGB IX) · 5. Mutterschutz-Status im Dienstplan · 6. Brandschutz-/Ersthelfer-Schicht-Check ·
7. Gefahrstoffverzeichnis · 8. DGUV-V3-Geräteprüfung (Facility-Erweiterung).

---

## 2. Gesundheitsförderung / Prävention (zweigeteilt)

### 2a. Mitarbeitende — BGM/BGF
- **§20b SGB V + PrävG**: betriebliche Gesundheitsförderung; Kassen-Soll-Pflicht (≥ 1 €/Versichertem für §71-SGB-XI-Einrichtungen); AG: bis **600 €/MA/Jahr steuerfrei** (§3 Nr. 34 EStG), Maßnahmen seit 2019 zertifizierungspflichtig (Zentrale Prüfstelle Prävention).
- **Psychische Gefährdungsbeurteilung (GDA, §5 ArbSchG)**: **Pflicht**, tätigkeitsbezogen — gehört in das GB-Modul aus §1, **nicht** ins freiwillige BGF.
- **BGW-Angebote** (kostenlos): Online-GB stationäre Pflege, Personalbefragung, GAP-Pflege, „Rückengerecht arbeiten" (04/2024).
- **Kennzahlen** (Controlling): Krankenstand Pflege ~7,4 % (Bund 5,3 %), Fluktuation hoch → AU-Quote/Fluktuation/BEM-Fälle als KPI.

→ App: **BGF-Maßnahmenplan** (analog QM-Checkliste) + Budget-Tracking 600 €/MA + Zertifikats-Archiv; KPIs ins Controlling.

### 2b. Bewohner — **§5 SGB XI Prävention (von der Pflegekasse mitfinanziert!)**
Höchst interessant, weil Erlösquelle. GKV-Leitfaden Prävention stationär (28.09.2023), **5 Handlungsfelder**:
Ernährung · körperliche Aktivität · kognitive Ressourcen · psychosoziale Gesundheit · **Gewaltprävention**.
Soll-Pflicht der Kassen, Einrichtung stellt Antrag + Konzept + Nachweise.

→ App (hohe Prio, Kasse zahlt): **Präventionsprogramm-Modul** je Bewohner (analog soziale Betreuung): Handlungsfeld,
Maßnahme, Frequenz, Teilnahme-Doku; einrichtungsweites Jahresprogramm; **Verwendungsnachweis-Export** für die Pflegekasse;
Evaluations-Messung (Sturzrate/Gewicht/Mobilität vorher–nachher). Brücke: Assessment-Ergebnis (z. B. Sturzrisiko) → Programm-Empfehlung.

> Abgrenzung: DNQP-Expertenstandards (Sturz/Dekubitus/Kontinenz/Ernährung/Mobilität/Hautintegrität) sind
> individuums-bezogene Pflegequalität (MDK-bindend via §113b) — ein qualifiziert konzipiertes Programm kann
> **gleichzeitig** Expertenstandard-konform UND §5-SGB-XI-abrechenbar sein.

---

## 3. Personalbemessung & Betreuungsschlüssel (§113c SGB XI / PeBeM)

Seit 01.07.2023 gestuft, **ab 01.01.2026 verpflichtend**. Ersetzt pauschale Landesschlüssel durch
bewohnerspezifischen Bedarf aus Pflegegrad-Mix × Qualifikationsmix (Basis: Rothgang-Gutachten 2020).

**Qualifikationsstufen:** QN1+2 Hilfskraft (o./m. Basisqualifikation) · QN3 Assistenz (1–2 J.) · QN4 Pflegefachkraft (3 J.).
Ziel-Mix ~ **40 % QN4 / 30 % QN3 / 30 % QN1+2**.

**Personalanhaltswerte (VZÄ je Bewohner, §113c Abs. 1, bundeseinheitlich, Stand 01.07.2023 — Obergrenze, BMG-Review alle 2 J.):**

| Stufe | PG1 | PG2 | PG3 | PG4 | PG5 |
|---|---|---|---|---|---|
| QN1+2 | 0,0872 | 0,1202 | 0,1449 | 0,1627 | 0,1758 |
| QN3 | 0,0564 | 0,0675 | 0,1074 | 0,1413 | 0,1102 |
| QN4 | 0,0770 | 0,1037 | 0,1551 | 0,2463 | 0,3842 |
| **Σ** | 0,2206 | 0,2914 | 0,4074 | 0,5503 | 0,6702 |

**Rechenkette (für Dienstplan-Soll):**
```
VZÄ_QN(k) = Σ_PG  Bewohner(PG) × PAW(k, PG)
1 VZÄ      = 1.560 Nettojahresstunden (38,5 h/Woche)
Stunden/Tag = VZÄ_gesamt × 1.560 / 365
Schichtbedarf = Stunden/Tag × Schichtanteil (Default Früh/Spät/Nacht = 50/35/15 %) / Schichtlänge
```
**Vergütungskreislauf:** PG-Mix × PAW → VZÄ → × Tariflohn → Personalkosten → Pflegesatz je PG (§84/§85 SGB XI verhandelt).
Höherer PG-Anteil ⇒ mehr verhandelbares Personal **und** höherer Tagessatz. Private Häuser können via §84 Abs. 2 mehr verhandeln (Multiplikator > 1,0).

**Nachtdienst** bleibt **landesrechtlich** (nicht §113c): NRW/BW 1 FK : 50 Bew., Bayern 1 : 30–40, viele Länder „mind. 1 FK/Haus".
**Fachkraftquote**: §113c ≈ 40 % rechnerisch; Landesheimrecht oft 50 % (BY abgesenkt ~43 %).

→ App: **Betreuungsschlüssel-Modul** im Dienstplan. Datengetriebener PAW-Katalog (versioniert, wie ArbZG-Engine),
einstellbare Stellschrauben: Wochenarbeitszeit/Nettojahresstunden, Fachkraftquote, Nachtdienst-Schlüssel (Default je Bundesland),
Qualifikationsmix-Ziel, Schichtverteilung, PAW-Multiplikator. **Soll-vs-Ist-Ampel** je Schicht: geplante Besetzung (Kopf × Qualifikation)
gegen rechnerisches Soll aus aktuellem PG-Mix (Empfehlung: rollender 30-Tage-PG-Mix). Konkretes Datenmodell siehe Recherche-Anhang.

---

## 4. „Positive" / ergonomische Dienstplan-Regeln (zusätzlich zur ArbZG-Hartgrenze)

Rechtlicher Anker: **§6 ArbZG** verweist auf „gesicherte arbeitswissenschaftliche Erkenntnisse" (BAuA, BGHM, DGAUM-S2k-Leitlinie).
Zweite Engine **nach** der ArbZG-Hartprüfung: liefert Qualitäts-Warnungen, einrichtungsweit konfigurierbar (an/aus + Parameter).

**Regel-Schema** (analog ArbZG-Engine): `id · name · category · enabled · severity(error|warning|info) · params · source[] · legal_basis · tariflich`.

**12 Default-Regeln:**

| ID | Regel | Severity | Key-Parameter (Default) | Quelle |
|---|---|---|---|---|
| `forward-rotation` | Vorwärtsrotation F→S→N, keine Rückwärtsfolge | warning | enforce_forward_only | BAuA/BGHM/DGAUM |
| `quick-return-gap` | Mindestabstand Spät→Früh | error<11h / warning<16h | hard 11 h, ergonomisch 16 h | §5 ArbZG/BAuA |
| `max-consecutive-nights` | Max. Nachtschichten in Folge | warning | 3 | BAuA/BGHM/AWMF |
| `post-night-free-block` | Freiblock nach Nachtphase | warning | min 2 Tage, optimal 4 | schichtplanfibel |
| `max-consecutive-workdays` | Max. Arbeitstage in Folge | warn>7 / error>12 | 7 / 12, Fenster 14 | schichtplanfibel/BGHM |
| `weekend-equity` | Wochenend-Gerechtigkeit | warning | Fenster 8 Wo, max Abw. 2, ≥2 freie WE/Monat | TVöD/AVR/§11 ArbZG |
| `weekend-block-free` | Sa+So zusammenhängend frei | warning | require_sa_so, optimal Fr–Mo | BGHM/BAuA |
| `early-shift-start` | Frühschicht nicht zu früh | warning | ≥ 06:30 | BGHM/schichtplanfibel |
| `late-shift-end` | Spätschicht-Ende | warning (aus default) | ≤ 23:00 (optimal 22:00) | sichere-pflegeeinrichtung |
| `schedule-notice` | Dienstplan-Vorlauf | warning | 28 Tage (gesetzl. Min. 4) | BAuA/carepros |
| `min-free-block` | Zusammenhängende Freizeit | warning | ≥ 2 Tage je 14-Tage-Fenster | BGHM/BAuA |
| `no-isolated-workdays` | Keine „Sandwich"-Einzeldienste | info (aus default) | — | sichere-pflegeeinrichtung |

Deckt die User-Beispiele ab: Freiblöcke (`min-free-block`, `post-night-free-block`), zusammenhängende freie Tage im Fenster
(`min-free-block` parametrisiert), Wochenend-Stückelung (`weekend-block-free` + `weekend-equity`), Früh+Spät-Wechsel (`quick-return-gap` + `forward-rotation`).
Tarifliche Defaults schärfer bei TVöD-P/AVR (z. B. ≥ 2 freie WE/Monat). Optional `age_threshold` für strengere Nacht-Limits ab 50 J.

---

## 5. Datei-/Foto-Upload + Freigabe (MinIO) — Recht + Architektur

### Rechtliche Pflicht-Checkliste
- **Gesundheitsdaten = Art. 9 DSGVO** (Verbot mit Erlaubnisvorbehalt). Berechtigtes Interesse ist gesperrt.
  Wundfoto/Behandlungsdoku: Art. 9 Abs. 2 lit. h + §630f BGB. Profil-/Eventfoto: **ausdrückliche Einwilligung** (Art. 9 Abs. 2 lit. a + **§22 KUG**).
- **Einwilligung**: schriftlich, zweckgebunden, widerrufbar. Bei Demenz/Einwilligungsunfähigkeit: **gesetzlicher Betreuer** (Aufgabenkreis).
- **Wundfoto**: Maßstab, Datum, Identität, standardisiert; ergänzt Schriftdoku, ersetzt sie nicht. Reine Archivierung ist **kein** Medizinprodukt (MDR) — Zweckbestimmung strikt auf Doku/Archiv beschränken (keine Auto-Warnungen/Empfehlungen).
- **Aufbewahrung**: Pflegedoku **10 Jahre** (§630f Abs. 3 BGB), faktisch **30 J.** bei Haftungsrisiko (§199 BGB). Verwaltung 6/10 J. (HGB/AO). Bewohnerfotos ohne Behandlungsbezug nach Zweckwegfall löschen (Art. 5/17 DSGVO).
- **Löschkonzept** (Zwei-Phasen: Aktiv → Archiv mit Rechtsgrundlagenwechsel → autom. Löschung nach Frist), **Zugriffskonzept** (RBAC, Need-to-know), **Zugriffs-/Freigabe-Protokoll** (Audit-Pflicht).
- **Verschlüsselung**: at-rest (MinIO SSE-KMS) + in-transit (TLS 1.2+). Self-Hosted MinIO ⇒ kein AVV (eigener Server); externes Hosting ⇒ AVV Art. 28 + EU-Standort.

### Architektur-Empfehlung (Bewertung: MinIO **ja** — passt zu „kein Cloud-Zwang", self-hosted, S3-kompatibel)
- Docker-Service MinIO im bestehenden Compose-Stack (Ports hinter internem Reverse-Proxy, KMS-Key in Docker-Secret), Init-Container für Buckets.
- **Mandantentrennung: Bucket-pro-Tenant** (`opcare-tenant-{id}`, eigene SSE, sauberes Backup/Audit) — für < ~500 Mandanten besser als Prefix.
- Laravel Flysystem S3-Driver, `use_path_style_endpoint`; **Zwei-Disk-Workaround** für `temporaryUrl()` (interner Docker-Host vs. öffentliche URL).
- **Presigned URLs** mit kurzer Ablaufzeit statt öffentlicher Buckets; jede Freigabe + jeder Abruf protokolliert. Optional ClamAV-Service für Virenscan.
- **DB-Schema**: `attachments` (tenant_id, polymorph attachable, storage_key, bucket, mime, checksum, category, is_medical, consent_id, retention_until, archived_at, uploaded_by) + `attachment_shares` (share_type physician/relative/authority/internal, recipient, expires_at, accessed_at, revoked_at) + `consents`. tenant_id erbt den vorhandenen Global Scope; Metadaten nutzen die vorhandene At-Rest-Feldverschlüsselung.

---

## 6. Vollständigkeits-Audit — was fehlt/ist nur Checkliste (Top-10-Roadmap)

Kriterien: Rechtspflicht (Bußgeld/Straf) × MD-Prüfrelevanz × Machbarkeit aus vorhandenen Daten.

| Prio | Lücke | Norm | Basis in opcare | Kern |
|---|---|---|---|---|
| 1 | **BtM-Nachweisführung** | BtMVV §13 | Medikation | BtM-Konto je Bewohner, Monatsprüfung + Ausdruck, 3-J.-Archiv (Strafrecht!) |
| 2 | **FEM-Genehmigungsworkflow** | §1831 BGB | CareEvent | Antrag→Gericht→Genehmigung-Upload, Review-Reminder, Beendigung, Alternativen-Doku |
| 3 ✅ | **Qualitätsindikatoren-Export** | §113b SGB XI | QDVS/DAS | 15 Indikatoren autom. aus Pflegedaten berechnen, halbjährl. DAS-Export + Plausibilitätscheck — **umgesetzt** (QDVS-Regel-Engine) |
| 4 ✅ | **Medizinprodukte** | MPBetreibV 2025/MDR | Facility | Bestandsverzeichnis, Medizinproduktebuch, STK/MTK-Fristen, Einweisungsnachweis — **umgesetzt 2026-06-06** (medizinprodukte.md) |
| 5 | **Datenschutz operativ** | DSGVO 30/28/33 | Security | VVT, AVV-Inventar, Datenpannen-Register (72-h-Timer), Betroffenenrechte-Workflow |
| 6 ✅ | **Barbetragsverwaltung** | §27b SGB XII | Buchhaltung | Treuhand-Bewohnerkonto, Einzelbelege, Monats-/Jahresbericht fürs Betreuungsgericht, Pfändungsschutz — **umgesetzt 2026-06-06** (taschengeldkasse.md) |
| 7 | **Beschwerde + Gewaltschutz** | §114 SGB XI, QPR 2024, QA 2026 | (neu) | Beschwerde-Ticket; Gewaltschutzkonzept + Vorfall-Meldung (vertraulich); Auswertungs-Dashboard |
| 8 | **Hygiene operativ + MRE** | §23/§36 IfSG, KRINKO | QM-Checkliste→Modul | Hygieneplan versioniert, MRE-Status je Bewohner, Ausbruchs-Meldekette, Hygiene-Unterweisung |
| 9 | **Brandschutz + Evakuierungsklassen** | DIN 14096, LBO | Personal+Bewohner | Doku/Schulung/Übungsprotokoll; **Evakuierungsklasse je Bewohner** (selbstständig/Hilfe/Trage) |
| 10 | **Heimbeirat + WBVG-Vertrag** | HeimmwV, WBVG | (neu)/Stammdaten | Heimbeirat-Protokolle; WBVG-Vertrag mit Mindestinhalten + Entgeltänderungs-/Kündigungs-Workflow |

Auf dem Radar (11–13): Trinkwasser/Legionellen (TrinkwV 2023, Facility-Erweiterung), Heimaufsicht-Meldeworkflow (länderspezifisch),
Pflegecharta-Aufnahme-Workflow (BMFSFJ, minimal aber MD-relevant).

---

## Querschnitts-Muster, die mehrfach auftauchen (Wiederverwendung)

1. **Nachweis-mit-Frist** (Unterweisung, Vorsorge, STK/MTK, Legionellen, Räumungsübung): generisches „Pflicht-Ereignis je Subjekt
   mit Wiederholungsintervall + Ampel + Erinnerung" — ein Mechanismus, viele Normen (wie die QM-Checkliste universell wurde).
2. **Dokument-mit-Version + Freigabe** (Hygieneplan, Brandschutzordnung, Gefahrstoff-Betriebsanweisung, Konzepte): deckt sich mit dem
   Datei-/Upload-Feature aus §5 → MinIO-Modul ist Enabler für viele Audit-Punkte.
3. **Genehmigungs-/Melde-Workflow mit Behörden-Frist** (FEM, Datenpanne 72 h, Heimaufsicht, BtM-Monatsabschluss): Antrag→Status→Frist→Nachweis.
4. **Datengetriebene Regel-/Wert-Kataloge** (PAW-Tabelle, Schichtregeln, ArbZG): versioniert, mandanten-/bundesland-überschreibbar.

**Empfohlene nächste Umsetzungsstränge** (Reihenfolge zur Diskussion, nicht final):
A) MinIO-Datei-/Foto-Modul (Enabler) → B) Betreuungsschlüssel im Dienstplan (§113c) + positive Schichtregeln →
C) Nachweis-mit-Frist-Mechanismus (Arbeitsschutz: Vorsorge/Unterweisung + BEM) → D) §5-SGB-XI-Bewohner-Prävention (Erlösquelle) →
E) BtM + FEM (höchstes Rechtsrisiko).
