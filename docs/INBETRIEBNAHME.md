# Inbetriebnahme & Aktivierung — das zentrale „Schalter-Register"

**Zweck (verbindlich):** opcare wird bis zur offiziellen Zulassung/Testung NICHT produktiv genutzt. In
dieser Phase ist „gebaut, aber noch nicht aktiv" **bewusst erlaubt** — ABER **jede** stillgelegte, mit
Platzhalter versehene oder extern-gegatete Stelle steht **hier**, an einer Stelle. Wenn die Anträge durch
sind, wird diese Liste von oben nach unten abgearbeitet („Schalter umlegen") — kein Suchen im Code.

> Pflege-Regel: Wer etwas baut, das jetzt noch nicht echt laufen kann, trägt es **sofort hier** ein
> (Was · Wo im Code · Wie aktivieren · Woran es hängt).

---

## 1. Externe Credentials / Anschlüsse (nur Organisation kann beschaffen)

| Was | Wofür | Wo im Code | Aktivieren |
|---|---|---|---|
| **SMC-B-Testzertifikat + Member-ID** | TI-2.0/ZETA-RU-Auth scharf schalten (`mock_auth`-Schalter) | `config/ti20.php` (`TI20_ZETA_MOCK_AUTH`, `TI20_SMCB_P12_BASE64`, `TI20_MEMBER_ID`), `scripts/ti2.0/run-gate.sh`, `.github/workflows/ti2.0-conformance.yml` | Runbook: `docs/ti2.0/ti2.0-auth-inbetriebnahme.md` — Schritt 1: gematik-Onlineshop (~35 €) → P12 → `TI20_SMCB_P12_BASE64`; Schritt 2: `idp-registrierung@gematik.de` → Member-ID → `TI20_MEMBER_ID`; dann `TI20_ZETA_MOCK_AUTH=false`. Diagnose: `bash scripts/ti2.0/check-credentials.sh` |
| **HBA (Heilberufsausweis) des Arztes** | E-Rezept-QES (Signatur) | E-Rezept ist Daten-Repräsentation, Signatur ist nicht im FHIR | Arzt-seitig; opcare erzeugt nur den Datensatz |
| **E-Rezept-Fachdienst-Zugang** | echte PrescriptionId, Übertragung | `ErezeptBundleMapper` (Platzhalter-PrescriptionId/Prüfnummer) | TI-Anbindung (Track C) |
| **KIM-Konto + Clientmodul** | KIM-Nachrichten real senden (S/MIME) | `app/Domains/Kim/` (Composer fertig, Transport dormant) | KIM-Fachdienst-Vertrag + Clientmodul → `KIM_TRANSPORT=smime` + S/MIME-Transport implementieren/einhängen |
| **ePA-Fachdienst-Zugang** | ePA-EML/Statement, Dispense, op-* | ePA-Statement/EML nicht gebaut (E-Rezept-ID-gekoppelt) | TI-Anbindung (Track C) |

## 2. Platzhalter-Daten → durch echte ersetzen (sobald Stammdaten/Anschluss da)

| Platzhalter | Wo | Echte Quelle |
|---|---|---|
| ~~Bewohner-Postadresse~~ ✅ gelöst | jetzt echtes Feld (`residents.strasse/hausnummer/plz/ort`, im Bewohner-Formular) | Platzhalter greift nur noch als Fallback bei leerem Feld |
| ~~Praxis-Postadresse~~ ✅ gelöst | jetzt echtes Feld (`physicians.strasse/…`) | Platzhalter nur Fallback |
| **PrescriptionId** `160.000.764.737.300.50` | `ErezeptBundleMapper` Bundle.identifier | E-Rezept-Fachdienst |
| **Prüfnummer** `Y/400/1910/36/346` | `ErezeptBundleMapper` Composition.author[Device] | PVS-Zertifizierung |
| **Test-KVNR** `X110411319` | `DemoSeeder` (ResidentInsurance) | eGK/VSDM bzw. Erfassung |
| **Test-LANR/BSNR** `838382202` / `031234567` | `DemoSeeder` (Physician) | Arzt-Stammdaten |
| **Test-PZN** `06313728` | `DemoSeeder` (MedProduct Ramipril) | Arzneimittel-Stammdaten (IFA) |
| **Institutions-Identifier-Systeme** `opcare.local/sid/*` | ISiP/EVP-Mapper | reale NamingSystems der Einrichtung |

## 3. Stammdaten-Felder (real anlegbar, kein Zulassungs-Gate)

- ✅ **Bewohner-Postadresse** — `residents.strasse/hausnummer/plz/ort`, im Bewohner-Anlageformular erfassbar.
- ✅ **Praxis-Adresse** — `physicians.strasse/hausnummer/plz/ort`.
- ✅ **Einrichtungs-Adresse** — `tenants.strasse/hausnummer/plz/ort`; fließt in `IsipOrganization.address` **und** in die ÜLB-`Organization` der Pflegeüberleitung (`DocumentingEntityMapper`, IK + Adresse, gegen das KBV-ÜLB-IG validiert: 0 errors). Institutions-Postadresse für die ZETA-Schicht (TI 2.0) / KIM-Absender.
- ✅ **Status-Beobachtungen** — `residents.statusObservations` (Bewusstsein, Harn-/Stuhlkontinenz, Atmung,
  Kostform/Ernährungsform); fließen in die ÜLB-Sektionen orientierungPsyche, harn-/stuhlkontinenz­Differenzierte­Einschaetzung,
  qualitativeBeschreibungAtmung und ernaehrung (`StatusObservationMapper`, gegen ÜLB-IG validiert: 0 errors).
- ✅ **Medizinprodukte/Hilfsmittel** — `residents.devices`; ÜLB-Sektion medizinprodukte (`MedicalDeviceMapper`,
  Presence → DeviceUseStatement → Device, 0 errors).
- ✅ **An-/Zugehörige** — `residents.contacts`; ÜLB-Sektion patientenAdressbuch als RelatedPerson_Contact_Person
  (`RelatedPersonMapper`, 0 errors).
- Reale Dateneingabe → bis befüllt greift der Platzhalter-Fallback (§2) nur bei leeren Feldern.

### 3a. Bewusst zurückgestellte ÜLB-Detailtiefe (gebaut: konform & ehrlich; offen: codierte Tiefe)

| Lücke | Heutiger Stand | Aktivieren / nötig |
|---|---|---|
| **Device `type.coding` (SNOMED)** | Basis-Variante: `Device.type.text` = Bezeichnung (konform, untypisiert) | Kuratierte Geräte-Codeliste (SNOMED-Nachfahren von `49062001`) im UI → `type.coding` setzen |
| **Ernährungs-Detail** | nur Presence „Ernährungsbefund vorhanden" + Detail im Narrativ | ÜLB-Nutrition-VS trägt nur present/absent — Kostform/Form hat keinen codierten Slot (Modell-Grenze) |
| **`Observation_Relatives_Notified`** | NICHT gebaut — `contacts.benachrichtigen` ist eine **Präferenz**, kein „wurde benachrichtigt"-Ereignis | Echtes Überleitungs-Benachrichtigungs-Event erfassen, dann Sektion benachrichtigungVonAn-undZugehoerigen |
| **`Observation_Orientation` (4 Komponenten)** | NICHT gebaut — Bewusstsein deckt orientierungPsyche via Cognitive_Awareness ab | Erhebung von Orientierung zu Zeit/Ort/Person/Situation im UI |

## 4. Konfigurations-Schalter (Prod-Härtung, vom Betreiber zu setzen)

| Schalter | Default (Dev) | Prod |
|---|---|---|
| `SESSION_SECURE_COOKIE` | false | **true** (HTTPS) |
| TLS-Terminierung / HSTS | greift nur über HTTPS | Reverse-Proxy/Ingress mit TLS |
| At-Rest-Volume-Verschlüsselung | — | LUKS/TDE (s. `docs/security/sicherheitskonzept.md` §2a) |
| `APP_KEY` | lokal | aus Secrets-Manager/KMS |

## 5. Stillgelegte Bausteine (gebaut, per Schalter aktivierbar)

| Baustein | Status | Aktivieren |
|---|---|---|
| **KIM-Transport** | dormant — `DormantKimTransport` komponiert die Nachricht, sendet aber NICHT (loggt sichtbar) | `KIM_TRANSPORT=smime` + S/MIME-Transport-Klasse implementieren + Binding in `AppServiceProvider` |
| **ZETA-Sidecar (C1)** | Seam + Discovery gebaut, Mock-Auth aktiv (`TI20_ZETA_MOCK_AUTH=true`) | SMC-B-Cert + Member-ID beschaffen (Runbook: `docs/ti2.0/ti2.0-auth-inbetriebnahme.md`) → `TI20_ZETA_MOCK_AUTH=false` → `bash scripts/ti2.0/run-gate.sh` |
| **Ollama-/whisperX-Container (Dev)** | gebaut — `docker/ai-services/` + `scripts/ai-services.sh` (Erreichbarkeit-zuerst, baut nur fehlende Dienste, GPU via CDI / CPU-Fallback). Endpoints in `config/speech.php` (Ollama `localhost:11434`, whisperX `localhost:8000`) | `scripts/ai-services.sh check` (prüft), `… up` (baut fehlende). **Prod**: App zeigt per `OLLAMA_URL`/`WHISPER_URL` auf externe Endpoints (Prod-Ollama `192.168.178.11:11434`) — **kein** AI-Container auf dem GPU-losen Server. Offen: `OLLAMA_MODEL`-env → ModelRouter. Doku: `docker/ai-services/README.md`, Plan `docs/ai-services-plan.md` |
| **VLM-Beleg-Capture** | gebaut — Domäne `Capture` (Foto→VLM-Analyse→Vorschlag→berechtigte Bestätigung bucht), Fake-Adapter (`SPEECH_FAKE`) für dev/test, Livewire `belegerfassung` | Ollama-VLM-Modell (`CAPTURE_VLM_MODEL`, Default `qwen2.5vl`) auf der `OLLAMA_URL` verfügbar machen (per `scripts/ai-services.sh`). Schreibt nur nach Bestätigung (Finanzrolle). Doku: `docs/vlm-beleg-capture.md` |
| **Vision-Regalzählung / YOLO-Training** | gebaut — Livewire `regalzaehlung`, YOLO-Erkennung (Fake-Adapter für dev/test), Zählmenge → `Inventurposition.ist_menge` (nur in offene Inventur, HITL). Training-Schaltfläche ist per Schalter deaktiviert. **Dataset-/ZIP-Pipeline ist ein Folge-Inkrement** — `trainingStarten()` zeigt bis dahin eine ehrliche Fehlermeldung statt einen leeren ZIP zu senden. | **Reale Zählung sinnvoll erst mit trainiertem Modell** (sonst Basis-Erkennung `yolo11n.pt`). Aktivieren: 1) Vision-MCP-Service starten (`VISION_MCP_URL`, `VISION_MCP_TOKEN`), `VISION_FAKE=false` → echter `HttpVisionClient` aktiv. 2) Modell trainieren (extern) → `yolo_modelle`-Eintrag anlegen + `aktiv=true`. 3) Training-UI: `VISION_TRAINING_AKTIV=true` (config `vision.training_aktiv`) freischalten — erst wenn Dataset-/ZIP-Pipeline implementiert (gelabelte `RegalAufnahmen` → ZIP-Export → MCP `train`-Call). |

## 6. CI-Gates, die extern-gegatet „skippen" (kein Fehler, sichtbar)

- **`ti2.0-conformance`** — überspringt ohne SMC-B-Cert (sichtbares `::warning::`). Wird mit dem Secret scharf.

---

**Verweise:** Konnektoren-/Track-C-Strategie in `docs/ti2.0/ti2.0-konformitaets-gate.md`, Roadmap in
`docs/roadmap-ti-fhir.md`, Security/TOM in `docs/security/sicherheitskonzept.md`.
