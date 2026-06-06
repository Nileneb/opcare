# Offene & ungeklärte Punkte (Stand 2026-06-06, Session-Handoff)

Stand: 417 Tests grün, PHPStan 0, Pint clean. Diese Liste sammelt, was vor/nach einem Neustart offen ist.
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
- **Bundesland-Konfiguration**: Fachkraftquote/Nachtdienst/Heimrecht/Hygienebeauftragten-Qualifikation sind
  föderal verschieden. Aktuell ein Default-Satz; mandanten-/bundesland-spezifische Overrides noch offen.
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

Noch offen:
1. **MPBetreibV-Bestandsverzeichnis** (§ 13/§ 14) — Medizinprodukte-Stammdaten + STK/MTK-Frist-Ampel + Einweisung
   am Skill-Baum (Recherche §2). Höchstes Risiko/höchste Reife → Top-Kandidat.
2. **Beschwerde-/Gewaltschutz** + **anonyme Feedback-Form** (Idee #2) und **Heimbeirat/Gremien-Modul** (Idee #7,
   Recherche §4) — Betriebsarzt/Sifa-Stammdaten inklusive (Recherche §5).
3. **Bewohner/Angehörige/Betreuer als Nutzer** — Vertretung mit Aufgabenkreisen (Betreuungsrecht §§ 1814 ff. BGB),
   Posteingang/**Briefwahl-Benachrichtigung** (Recherche §1, User-Wunsch 2026-06-06).
4. **Datenschutz-VVT/AVV** (Art. 30/28 DSGVO, Recherche §3) · **Hygiene/MRE** (§ 23 IfSG, Recherche §6) ·
   **Fortbildungsplan** (Recherche §7).
5. **Bundesland-Overrides** (föderales Heimrecht, Recherche §8) · **freie Buchung im Hauptbuch** (Recherche §10) ·
   **Energielevel-Ampel** (Recherche §9) · **Übergangs-/Spitzendienste** (Idee #4).
6. **AI-Services** (eigene Session): Ollama + whisperX-mcp als Container mit Build-Pre-Flight-Healthcheck,
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
