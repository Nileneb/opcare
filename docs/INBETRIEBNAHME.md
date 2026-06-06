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
| **SMC-B-Testzertifikat** | TI-2.0/ZETA-Testhub-Gate scharf schalten | `scripts/ti2.0/run-gate.sh`, `.github/workflows/ti2.0-conformance.yml` | gematik-Anfrageportal → ZIP → `.p12` → CI-Secret `TI20_SMCB_P12_BASE64` |
| **HBA (Heilberufsausweis) des Arztes** | E-Rezept-QES (Signatur) | E-Rezept ist Daten-Repräsentation, Signatur ist nicht im FHIR | Arzt-seitig; opcare erzeugt nur den Datensatz |
| **E-Rezept-Fachdienst-Zugang** | echte PrescriptionId, Übertragung | `ErezeptBundleMapper` (Platzhalter-PrescriptionId/Prüfnummer) | TI-Anbindung (Track C) |
| **KIM-Konto + Clientmodul** | KIM-Nachrichten senden/empfangen | _(KIM-Modul, in Arbeit)_ | KIM-Fachdienst-Vertrag + Clientmodul |
| **ePA-Fachdienst-Zugang** | ePA-EML/Statement, Dispense, op-* | ePA-Statement/EML nicht gebaut (E-Rezept-ID-gekoppelt) | TI-Anbindung (Track C) |

## 2. Platzhalter-Daten → durch echte ersetzen (sobald Stammdaten/Anschluss da)

| Platzhalter | Wo | Echte Quelle |
|---|---|---|
| **Bewohner-Postadresse** (E-Rezept Patient) | `ErezeptBundleMapper::address()` | Stammdaten-Feld (s. §3) bzw. Einrichtungsadresse |
| **Praxis-Postadresse** (E-Rezept Organization) | `ErezeptBundleMapper::address()` | Praxis-Stammdaten |
| **PrescriptionId** `160.000.764.737.300.50` | `ErezeptBundleMapper` Bundle.identifier | E-Rezept-Fachdienst |
| **Prüfnummer** `Y/400/1910/36/346` | `ErezeptBundleMapper` Composition.author[Device] | PVS-Zertifizierung |
| **Test-KVNR** `X110411319` | `DemoSeeder` (ResidentInsurance) | eGK/VSDM bzw. Erfassung |
| **Test-LANR/BSNR** `838382202` / `031234567` | `DemoSeeder` (Physician) | Arzt-Stammdaten |
| **Test-PZN** `06313728` | `DemoSeeder` (MedProduct Ramipril) | Arzneimittel-Stammdaten (IFA) |
| **Institutions-Identifier-Systeme** `opcare.local/sid/*` | ISiP/EVP-Mapper | reale NamingSystems der Einrichtung |

## 3. Stammdaten-Felder, die noch fehlen (real anlegbar, kein Zulassungs-Gate)

- **Bewohner-Postadresse** (Straße/Hausnr./PLZ/Ort) — für E-Rezept-Patient + allg. Korrespondenz.
- **Einrichtungs-/Praxis-Adresse** (Tenant bzw. Physician-Praxis).
- Diese sind reine Dateneingabe → Felder können jederzeit ergänzt werden; bis dahin Platzhalter (§2).

## 4. Konfigurations-Schalter (Prod-Härtung, vom Betreiber zu setzen)

| Schalter | Default (Dev) | Prod |
|---|---|---|
| `SESSION_SECURE_COOKIE` | false | **true** (HTTPS) |
| TLS-Terminierung / HSTS | greift nur über HTTPS | Reverse-Proxy/Ingress mit TLS |
| At-Rest-Volume-Verschlüsselung | — | LUKS/TDE (s. `docs/security/sicherheitskonzept.md` §2a) |
| `APP_KEY` | lokal | aus Secrets-Manager/KMS |

## 5. CI-Gates, die extern-gegatet „skippen" (kein Fehler, sichtbar)

- **`ti2.0-conformance`** — überspringt ohne SMC-B-Cert (sichtbares `::warning::`). Wird mit dem Secret scharf.

---

**Verweise:** Konnektoren-/Track-C-Strategie in `docs/ti2.0/ti2.0-konformitaets-gate.md`, Roadmap in
`docs/roadmap-ti-fhir.md`, Security/TOM in `docs/security/sicherheitskonzept.md`.
