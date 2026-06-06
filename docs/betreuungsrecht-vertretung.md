# Betreuungsrecht: Vertretung mit Aufgabenkreisen + Stakeholder-Benachrichtigung

Recherchiert mit dem **Legal Data Hunter**-MCP (DE/BGBl, BtOG, Bundestags-Drucksachen) + dem bestehenden
§-1-Block in [recherche-offene-punkte-2026-06.md](recherche-offene-punkte-2026-06.md). Ziel ist nicht eine
Vertreter-Stammdatenliste, sondern **App-Logik, die Rechte und Pflichten der Vertretung operativ abbildet**, damit
der Träger seine Informations-/Beteiligungspflichten *ohne Mehraufwand* korrekt erfüllt.

> Fachliche Endprüfung durch eine qualifizierte Person bleibt erforderlich. Landesheimrecht ist mandantenabhängig.

## 1. Rechtsgrundlage (reformiertes Betreuungsrecht seit 1.1.2023)

| Norm | Inhalt | App-Logik-Konsequenz |
|---|---|---|
| **§ 1814 BGB** | Bestellung eines rechtlichen Betreuers | Vertretungs-Typ *gesetzlicher Betreuer* |
| **§ 1815 BGB** | Betreuung **nur für konkret angeordnete Aufgabenbereiche** (Aufgabenkreise) | `aufgabenkreise[]` als Set; **jede Sicht/Aktion der Vertretung wird über die Aufgabenkreise gegated** (gleiche Idee wie `Befugnis`) |
| **§ 1820 BGB** | Vorsorgevollmacht als Alternative zur gerichtlichen Betreuung | Vertretungs-Typ *Vorsorgebevollmächtigter* / *Bevollmächtigter* |
| **§ 1821 BGB** | **Vorrang der Wünsche** des Betreuten; Betreuer unterstützt, bestimmt nicht; Besprechungspflicht | informativ; Portal ist **read-only** — keine stellvertretende Aktion in der App |
| **§§ 1827–1832 BGB** | Einwilligung Heilbehandlung, Patientenverfügung (§ 1827), genehmigungspflichtige ärztliche Maßnahmen (§ 1829), **FEM § 1831** (bereits gebaut), ärztliche Zwangsmaßnahme (§ 1832) | Ereignis-Kategorien *Heilbehandlung-Einwilligung* / *ärztliche Maßnahme* → Benachrichtigung an Aufgabenkreis *Gesundheitssorge* |
| **§ 1833 BGB** | Wohnungsauflösung nur mit Genehmigung des Betreuungsgerichts | Ereignis *Heimvertrag/Wohnung* → Aufgabenkreis *Wohnungs-/Vermögenssorge* |
| **§§ 1835 ff. BGB** | **Vermögenssorge**: Vermögensverzeichnis, genehmigungspflichtige Geschäfte | Aufgabenkreis *Vermögenssorge* → Sicht auf Taschengeldkasse/Barbetrag |
| **§ 1863 BGB** | Betreuer schuldet **Anfangsbericht + jährlichen Bericht** über persönliche Verhältnisse ans Betreuungsgericht | **Pflicht-mit-Frist (Ampel)** je Vertretung: `bericht_intervall_monate` (12) + `letzter_bericht_am` → Erinnerung |
| **§ 1865 BGB** | Rechnungslegung des Betreuers | dieselbe Pflicht-Ampel (Vermögenssorge) |
| **BtOG** (BJNR091700021) | ehrenamtl. (§ 21) / berufl. (§ 23 ff. registriert) Betreuer, Betreuungsverein (§ 14 ff.); Betreuungsbehörde | Datenfeld *beruflich/ehrenamtlich*, Gericht + Aktenzeichen |
| **Wahlrecht** (BVerfG 2019, § 13 BWahlG) | höchstpersönlich — Betreuer wählt **nicht** für den Betreuten; bei *Postangelegenheiten* darf er Wahlunterlagen entgegennehmen | Posteingang-Ereignis *Wahlunterlagen* → Aufgabenkreis *Post*, **niemals** stellvertretende Stimmabgabe |

## 2. Das eigentliche Feature: Rechte und Pflichten als App-Logik

Der Träger ist verpflichtet, die Vertretung bei wesentlichen Ereignissen zu **beteiligen oder zumindest zu
informieren** (Ausfluss aus § 1821 Besprechungspflicht + den o. g. Einwilligungs-/Genehmigungsrechten). Beispiel
des Users: bei einer **MD-Begutachtung** (Pflegegrad, § 18 SGB XI) hat der Betreuer mit Gesundheits-/
Aufenthaltssorge das Recht, dabei zu sein oder informiert zu werden. opcare bildet das als **ereignisgetriebene,
nach Aufgabenkreis gefilterte Benachrichtigung** ab und dokumentiert die Pflichterfüllung (`informiert_am`).

### Bewohner-Ereignis → erforderliche Aufgabenkreise (Rechtewahrung)

| Ereignis-Kategorie | Recht / Norm | benachrichtigte Aufgabenkreise |
|---|---|---|
| **MD-Begutachtung** (Pflegegrad) | dabei sein / informiert, § 18 SGB XI + § 1821 | Gesundheitssorge, Aufenthaltsbestimmung |
| **Heilbehandlung — Einwilligung nötig** | § 1827 BGB | Gesundheitssorge |
| **Ärztliche Maßnahme / FEM / Zwang** | §§ 1829/1831/1832 BGB | Gesundheitssorge |
| **Krankenhaus-Verlegung / Notfall** | informiert, § 1821 | Gesundheitssorge, Aufenthaltsbestimmung |
| **Heimvertrag / Entgelterhöhung / Wohnung** | §§ 1833/1835 BGB, WBVG | Wohnungsangelegenheiten, Vermögenssorge |
| **Posteingang** (Behörde, **Wahlunterlagen**) | Postangelegenheiten | Postangelegenheiten |
| **Sterbefall** | Angehörigen-/Betreuer-Info | *alle aktiven Vertretungen* + benachrichtigte Angehörige |

Empfänger sind (a) Vertretungen, deren `aufgabenkreise` mindestens einen der erforderlichen Kreise enthält, und
(b) Angehörige (`ResidentContact.benachrichtigen = true`). Hat die Vertretung ein **Nutzerkonto**, läuft die
Benachrichtigung in-app über den vorhandenen `NotificationBell`/Reverb-Kanal; ohne Konto bleibt das Ereignis als
**offen** stehen, bis der Träger `informiert_am` setzt — so wird die Pflichterfüllung nie stillschweigend
übersprungen.

### Vertreter-Pflicht-Erinnerung (an die Vertretung)

`bericht_intervall_monate` + `letzter_bericht_am` → `naechsterBericht()` → **Ampel** (überfällig rot, ≤ 30 Tage
amber). Bildet den jährlichen Bericht (§ 1863) bzw. die Rechnungslegung (§ 1865) ab. Erscheint im Träger-Dashboard
*und* im Vertreter-Portal.

## 3. Datenmodell-Implikation (Wiederverwendung bestehender Muster)

- **`Custodian` (rechtliche Vertretung) wird ausgebaut** — abgegrenzt von `ResidentContact` (Angehörige, FHIR
  RelatedPerson). Neue Felder: `typ` (Enum `VertretungTyp`), `aufgabenkreise` (Enum-Set `Aufgabenkreis`),
  `user_id` (optionales Login-Konto), `email`, `gueltig_bis`, `gericht`, `aktenzeichen`, `beruflich`,
  `bericht_intervall_monate`, `letzter_bericht_am`. Verhalten: `hatAufgabenkreis()`, `naechsterBericht()`,
  `berichtAmpel()`, `vertretungAmpel()` (Bestellung läuft ab).
- **`BewohnerEreignis`** (neu): `kategorie` (Enum `EreignisKategorie`), `titel`, `datum`, `status`
  (offen/informiert/erledigt), `informiert_am` — der **Genehmigungs-/Melde-Workflow-mit-Frist**-Muster auf den
  Bewohner angewandt. Bei Anlage → Benachrichtigung an die passenden Vertretungen/Angehörigen.
- **Rollen** `betreuer`, `angehoeriger` (read-only Portal). Tenant-Scope + Bewohner-Scope strikt: ein Vertreter
  sieht **nur** die ihm zugeordneten Bewohner und davon **nur** die Daten seiner Aufgabenkreise (Gating wie
  `Befugnis`).

Muster-Wiederverwendung (siehe [OFFENE-PUNKTE.md](OFFENE-PUNKTE.md) §F): *Nachweis-mit-Frist* (Bericht/Bestellung
→ Ampel), *Melde-Workflow-mit-Frist* (Ereignis → informiert_am), *Benachrichtigungs-Kanal* (`NotificationBell`),
*Aufgabenkreis-Gating* (= `Befugnis`-Idee), *Norm-als-Daten* (Aufgabenkreise/Typen als Enum-Katalog).
</invoke>
