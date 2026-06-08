# HACCP-Gefahrenanalyse-Register

Modul `app/Domains/Catering` (Gefahrenanalyse) für die **systematische Gefahrenanalyse je Prozessschritt** —
HACCP-Prinzip 1–3 (und 6: Verifizierung). Route `/haccp/gefahrenanalyse`.

## Warum (Abgrenzung zum HACCP-Tagesblatt)

Das bestehende **HACCP-Tagesblatt** (`/haccp`, `HaccpMesspunkt`/`Temperaturmessung`) überwacht die Grenzwerte
kritischer Kontrollpunkte (HACCP-Prinzip 4/5). Es beantwortete bisher aber nicht die vorgelagerte Frage:
**warum** ist ein Punkt ein CCP, welche Gefahr wird dort beherrscht, und gibt es signifikante Gefahren ohne
Lenkung? Genau diese Gefahrenanalyse (HACCP-Prinzip 1–3) war die Lücke. Das Register schließt sie und **verknüpft
jede als CCP eingestufte Gefahr mit dem zugehörigen Überwachungs-Messpunkt** des Tagesblatts.

## Struktur (gespiegelt vom GBU-Register)

- **`Gefahrenanalyse`** — ein Prozessschritt (z. B. „Wareneingang Kühlware", „Speisenausgabe"), Bereich,
  Verifizierungsintervall, Status (Entwurf/Freigegeben/Überarbeitung), Frist-Ampel.
- **`LebensmittelGefahr`** — eine identifizierte Gefahr je Prozessschritt: **Gefahrenart B/C/P/A**
  (biologisch/chemisch/physikalisch/allergen), **Risiko = Wahrscheinlichkeit × Schwere** (Nohl-light 3×3,
  `gering`/`mittel`/`hoch`), **CCP-Entscheidung** (`ist_ccp`) + optionale **Verknüpfung zum `HaccpMesspunkt`**
  (`haccp_messpunkt_id`) + CCP-Begründung.
- **`Lenkungsmassnahme`** — Lenkungsmaßnahme je Gefahr: Art (CCP / operative Prozesslenkung / Basishygiene-PRP),
  Frist, umgesetzt, verifiziert (Prinzip 6).

### Risiko & Signifikanz

`risikowert()` = W × S (Werte {1,2,3,4,6,9}; 5 tritt nie auf). `risikostufe()`: ≤2 gering, ≤4 mittel, ≥6 hoch.
Eine Gefahr mit Stufe mittel/hoch ist **signifikant** (`signifikant()`) und erfordert eine Lenkungsmaßnahme.

### Lücken — SSOT, kein stilles Kappen

Das Register weist Lücken **rot** aus statt sie zu verschweigen (Projektregel: offen/erledigt-Module brauchen
eine offene-RECORDS-Methode als single source of truth):

- `signifikanteGefahrenOhneLenkung()` — signifikante Gefahr ohne jede Lenkungsmaßnahme.
- `ccpOhneUeberwachung()` — als CCP eingestuft, aber kein Überwachungs-Messpunkt verknüpft (Prinzip 4 nicht erfüllt).
- `hatLuecke()` — Aggregat; `GefahrenanalyseMonitor::mitLueckenAnzahl()` zählt betroffene Analysen.

### Frist-Ampel & Verifizierung

`faelligkeitsStatus()` (rot/gelb/grün) ist nur für **freigegebene** Analysen scharf (Entwürfe haben keine
Verifizierungs-Uhr). `GefahrenanalyseVerifizieren` setzt das Verifizierungsdatum mit **Max-Semantik** (ein
nachgetragenes älteres Datum setzt die Frist nicht zurück) — VO 852/2004 Art. 5.

## Zugriff

Anlegen/Bearbeiten: `admin`, `pflegefachkraft`, `kueche` (+ Super-Admin). Tenant-isoliert; Methoden-Parameter-IDs
werden tenant-scoped per `findOrFail` geprüft (IDOR-Schutz).

## Rechtsrahmen

- **VO (EG) 852/2004 Art. 5** — HACCP-System (Gefahrenanalyse, CCP, Überwachung, Verifizierung, Dokumentation).
- **Codex Alimentarius CAC/RCP 1-1969** — die 7 HACCP-Grundsätze.
- **VO (EU) 1169/2011 (LMIV)** — Allergene als eigene Gefahrenkategorie.

## Architektur

`Gefahrenanalyse` → `LebensmittelGefahr` (→ `HaccpMesspunkt`) → `Lenkungsmassnahme`. Services:
`GefahrenanalyseMonitor` (tenant-scoped Übersicht + Zähler), `GefahrenanalyseVerifizieren` (Max-Semantik).
Livewire `app/Livewire/Catering/Gefahrenanalyse.php`. Tabellen: `gefahrenanalysen`, `lebensmittel_gefahren`,
`lenkungsmassnahmen`.
