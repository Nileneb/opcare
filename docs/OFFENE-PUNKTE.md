# Offene & ungeklärte Punkte (Stand 2026-06-06, Session-Handoff)

Stand: 514 Tests grün, PHPStan 0, Pint clean. Diese Liste sammelt, was vor/nach einem Neustart offen ist.
Querbezug: [Ideen-Backlog](ideen-backlog-2026-06.md), [Norm-Recherche](recherche-normen-erweiterung-2026-06.md),
**[Gesetzes-Recherche zu den offenen Punkten](recherche-offene-punkte-2026-06.md)** (Rechtsgrundlage + Datenmodell je Punkt).

## A. Inbetriebnahme-Schalter (gebaut, aber bewusst nicht „scharf"/prod-aktiv)
- **MinIO-Datei-Storage**: Code + Docker-Service vorhanden, aber Default `OPCARE_MEDIA_DISK=media` (lokal). Für Prod
  auf `minio` umstellen + Zugangsdaten/Bucket setzen. Siehe [dokumente-dateien.md](dokumente-dateien.md).
- **Reverb-Push** (Krankmeldungs-Benachrichtigung): `broadcast`-Kanal implementiert, aber `BROADCAST_CONNECTION`
  muss in Prod auf `reverb` + Reverb-Server laufen; In-App-Glocke funktioniert auch ohne (Poll). Echo-JS-Client
  (echte Browser-Push ohne Poll) noch nicht eingebunden.
- Beide gehören ins Schalter-Register [INBETRIEBNAHME.md](INBETRIEBNAHME.md) (dort prüfen/ergänzen).

## B. Berechtigungsmatrix/Delegation — Feinschliff offen
- **Befugnis-Guards** sind nur an SIS, Medikamentengabe und BtM-Gabe gekoppelt. **Noch nicht** an: FEM-Anordnen,
  Behandlungspflege-Einzelmaßnahmen, Wunddoku, Maßnahmenplanung-Abzeichnen — bei Bedarf analog `darfKey()` einhängen.
- **Vereinfachung**: „Fachkraft darf alles außer Spezialqualifikation" ist eine pragmatische Regel. Echte
  Differenzierung (welche ärztliche Tätigkeit zwingend pro-Person-Delegation braucht — z. B. i.v. auch für Fachkräfte)
  ist bewusst vereinfacht. Juristisch zu schärfen, falls gewünscht.
- **Bundesland-Konfiguration**: ✅ Grundgerüst umgesetzt (2026-06-06) — Bundesland automatisch aus der
  Einrichtungs-Adresse, Landesheimgesetz + Link je Land, Drei-Schichten-Defaults (Bund→Land→Träger), siehe
  [bundesland-heimrecht.md](bundesland-heimrecht.md). **Offen**: konkrete quantitative Landeswerte (Nachtdienst-
  Schlüssel je Land) sind noch nicht verifiziert hinterlegt (`HeimrechtRegelwerk::overrides()` leer, kein Raten).
- **Tätigkeits-Abzeichnen ↔ Doku**: Die Tätigkeiten sind ein eigener Katalog; eine echte Verknüpfung „diese
  konkrete dokumentierte Maßnahme = Tätigkeit X" (für lückenlosen Berechtigungsnachweis je Eintrag) fehlt noch.

## C. Rechtlich noch zu verifizieren / im Fluss
- **BEEP-Gesetz** (eigenständige Heilkunde Wunde/Diabetes/Demenz) tritt 1.1.2026 in Kraft — Detailregelungen
  (genaue Qualifikationswege, Verordnungsmuster) bei Veröffentlichung gegenprüfen. Tätigkeiten sind angelegt, aber
  nicht praktisch erprobt.
- **Pflegefachassistenz** wird ab 1.1.2027 bundeseinheitlich (PflAssEinfG) — Katalog dann anpassen.
- **Keine offizielle Berechtigungstabellen-API**: „wer darf was" bleibt kuratiert (kein Behörden-Feed). Pflege bei
  Gesetzesänderung manuell. Quellen: § 4 PflBG, G-BA HKP/§63, § 132a, Landesrecht — siehe
  [pflege-qualifikationen-zustaendigkeit.md](pflege-qualifikationen-zustaendigkeit.md).
- **Landesrecht WTG/Heimrecht** (Meldepflichten, Heimbeirat, Nachtdienst) ist nur teilweise abgebildet.

## D. Dokumentierte, noch nicht gebaute Features (Backlog, priorisiert)
> Rechtsgrundlage + Datenmodell je offenem Punkt sind in [recherche-offene-punkte-2026-06.md](recherche-offene-punkte-2026-06.md) recherchiert.

- ✅ **Taschengeld-/Barbetragsverwaltung** (§ 27b SGB XII, Audit-Lücke #6) — **umgesetzt 2026-06-06**, siehe
  [taschengeldkasse.md](taschengeldkasse.md).
- ✅ **Qualitätsindikatoren-Export (§ 113b)** — über die QDVS-Regel-Engine umgesetzt (57/440 DAS-Regeln scharf,
  ehrlicher Coverage-Report).
- ✅ **MPBetreibV-Bestandsverzeichnis** (§ 13/§ 14) — **umgesetzt 2026-06-06**: Medizinprodukte-Stammdaten +
  Medizinproduktebuch mit STK/MTK-Frist-Ampel + Einweisungen + Vorkommnisse (BfArM), siehe
  [medizinprodukte.md](medizinprodukte.md). MPSB (§ 6) im Beauftragten-Register vorhanden.
- ✅ **Beschwerde-/Gewaltschutz + Heimbeirat/Gremien + Betriebsarzt/Sifa** — **umgesetzt 2026-06-06**:
  Beschwerdemanagement (§ 113 SGB XI) mit **Weiterleitung anonym/namentlich nach Wahl des Melders** +
  Bereichs-Benachrichtigung, Gewaltschutz-Sofortmaßnahme, Gremien/Heimbeirat (HeimmwV/§ 10 WBVG/§ 11 ASiG) und
  Betriebsarzt/Sifa-Stammdaten (ASiG/DGUV V2). Siehe [beschwerden-gremien.md](beschwerden-gremien.md).

- ✅ **Bewohner/Angehörige/Betreuer als Nutzer** — **umgesetzt 2026-06-06**: rechtliche Vertretung mit
  **Aufgabenkreisen** (§§ 1814/1815 BGB), read-only **Vertreter-Portal** (Sicht je Aufgabenkreis gegated +
  serverseitige Portal-Schranke), **Pflicht-mit-Frist** (§ 1863 Bericht) und **Ereignis-Workflow** (§ 1821:
  MD-Begutachtung/Heilbehandlung/Krankenhaus/Heimvertrag/Posteingang → aufgabenkreis-gefilterte Benachrichtigung,
  Pflichterfüllung dokumentiert). Briefwahl = Ereignis-Kategorie *Posteingang*. Siehe
  [betreuungsrecht-vertretung.md](betreuungsrecht-vertretung.md).

- ✅ **Datenschutz-VVT/AVV** (Art. 30/28 DSGVO) · **Hygiene/MRE** (§ 23 IfSG) · **Fortbildungsplan** (QPR QB6) —
  **umgesetzt 2026-06-06**: Datenschutz-Register (VVT mit Prüf-Frist-Ampel + AVV + Art-30-Export, Domain
  `Compliance`), Hygiene & MRE-Surveillance (Hygieneplan mit Revisions-Ampel + Erreger-/Infektions-Liste je
  Bewohner mit Meldepflicht-Verfolgung § 6/7 IfSG, Domain `Hygiene`) und Fortbildungsplan (Pflicht-Themen-Matrix
  mit Auffrischungs-Ampel, `Personnel`). Siehe [datenschutz-vvt-avv.md](datenschutz-vvt-avv.md),
  [hygiene-mre.md](hygiene-mre.md), [fortbildungsplan.md](fortbildungsplan.md).

- ✅ **Bundesland-Overrides** (föderales Heimrecht, Recherche §8) · **freie Hauptbuchung** (Recherche §10) ·
  **Energielevel-Ampel** (Recherche §9) — **umgesetzt 2026-06-06**: Landesheimrecht automatisch aus der
  Einrichtungs-Adresse (PLZ→Bundesland) mit 16 Landesheimgesetzen + amtlichen Links und Drei-Schichten-Defaults
  (Bund→Land→Träger, keine geratenen Landeswerte); freie „Soll an Haben"-Maske im Hauptbuch (GoB/PBV); freiwilliges,
  anonymes Team-Energiebarometer (§ 26 BDSG/§ 87 BetrVG, kein Verlauf, k-Anonymität). Siehe
  [bundesland-heimrecht.md](bundesland-heimrecht.md), [freie-hauptbuchung.md](freie-hauptbuchung.md),
  [energiebarometer.md](energiebarometer.md).

- ✅ **Übergangs-/Spitzendienste** (Idee #4) — **umgesetzt 2026-06-06**: `ShiftKind::Spitzendienst` + editierbarer
  Spitzenzeit-Katalog (Mahlzeiten/Grundpflege mit Soll-Personen), Wochen-Deckungsmatrix je Fenster × Tag gegen die
  geplanten Schichten (minutenbasierte Überlappung inkl. Mitternacht) + Vorschläge bei Unterdeckung. Siehe
  [spitzenzeiten-spitzendienste.md](spitzenzeiten-spitzendienste.md).

Noch offen:
1. **AI-Services** (eigene Session): Ollama + whisperX-mcp als Container mit Build-Pre-Flight-Healthcheck,
   **VLM-Beleg-Capture** und **Budget-Setzungen**. Voll spezifiziert in [ai-services-plan.md](ai-services-plan.md).

## E. Technische/Infra-Notizen
- **Memory-Server (mcp.linn.games)** war in dieser Session durchgängig down (Timeout/500). Prefetch/Feedback nicht
  möglich; bei Neustart Healthcheck `curl https://mcp.linn.games/health`.
- **Demo-Daten**: nur Tenant „Aprath" ist voll geseedet; „Birkenhof" minimal. Screenshots via `scripts/shots.mjs`
  brauchen 2FA-Recovery-Setup (siehe Skript-Kopf).
- **Tätigkeits-/Kompetenz-/Beauftragten-Kataloge** werden per `*Defaults::ensureFor()` idempotent je Tenant
  angelegt (kein Seeder-Zwang), u. a. beim Seiten-Mount.

## F. Architektur-Muster (für konsistente Weiterarbeit)
- *Norm als Daten* (editierbare Kataloge), *Nachweis-mit-Frist* (Ampel), *Dokument-mit-Version+Freigabe* (MinIO/Strang A),
  *Genehmigungs-/Melde-Workflow mit Frist* (FEM/BtM), *datengetriebene Regel-Engines* (ArbZG/Ergonomie/PAW).
- Jede neue Tätigkeit/Kompetenz/Rolle = Katalog-Eintrag, kein Code. Berechtigung immer über `Befugnis`.
