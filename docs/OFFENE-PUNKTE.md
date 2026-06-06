# Offene & ungeklärte Punkte (Stand 2026-06-06, Session-Handoff)

Stand: 406 Tests grün, PHPStan 0, Pint clean. Diese Liste sammelt, was vor/nach einem Neustart offen ist.
Querbezug: [Ideen-Backlog](ideen-backlog-2026-06.md), [Norm-Recherche](recherche-normen-erweiterung-2026-06.md).

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
1. **Gremien-Modul** (Aufsichtsrat/Betriebsrat/Gleichstellung) + **Betriebsarzt/Sifa**-Stammdaten (Idee #7;
   Betriebsarzt ist bereits als Beauftragten-Rolle im Register, eigene Stammdaten fehlen).
2. **Fortbildungsplan + Fortbildungswünsche** an die Kompetenzen andocken (Skill-Baum-Erweiterung).
3. **Taschengeld-/Barbetragsverwaltung** (§ 27b SGB XII, Treuhand) — Audit-Lücke #6.
4. **Bewohner/Angehörige als Nutzer** (kein E-Mail-Feld/User-Verknüpfung heute) + **anonyme Feedback-Form**.
5. **Energielevel-Ampel** (freiwillig, Reverb-Push).
6. Audit-Top-10 Restposten: **Qualitätsindikatoren-Export (§113b)**, **MPBetreibV-Bestandsverzeichnis**,
   **Datenschutz-VVT/AVV**, **Beschwerde-/Gewaltschutz**, **Hygiene-Modul/MRE**, **Brandschutz/Evakuierungsklassen**.
7. **Übergangs-/Spitzendienste** + tageszeitabhängige Soll-Besetzung im Generator (Idee #4).
8. **AI-Services** (eigene Session): Ollama + whisperX-mcp als Container mit Build-Pre-Flight-Healthcheck,
   **VLM-Beleg-Capture** (Foto→Analyse→Einsortier-Vorschlag→berechtigte Bestätigung) und **Budget-Setzungen**
   (Taschengeldkasse). Voll spezifiziert in [ai-services-plan.md](ai-services-plan.md).

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
