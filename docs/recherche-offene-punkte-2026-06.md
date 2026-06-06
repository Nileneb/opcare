# Gesetzes-Recherche zu den offenen Punkten (2026-06-06)

Recherchiert mit dem **Legal Data Hunter**-MCP (Volltexte/Verweise aus `DE/BGBl`, Landesrecht, EU). Ziel:
datengetriebene Weiterentwicklung — pro offenem Punkt die **Rechtsgrundlage** (mit Quelle), die **operative
Pflicht** und die daraus folgende **Datenmodell-Implikation** für opcare. Fachliche Endprüfung durch eine
qualifizierte Person bleibt erforderlich; Landesrecht ist mandantenabhängig.

> Bau-Muster-Wiederverwendung gilt durchgängig (siehe `OFFENE-PUNKTE.md` §F): *Norm-als-Daten*,
> *Nachweis-mit-Frist*, *Dokument-mit-Freigabe*, *Genehmigungs-/Melde-Workflow*, *Beauftragten-Register*.

---

## 1. Bewohner/Angehörige als Nutzer + Betreuungsrecht (gesetzliche Vertretung) — ✅ umgesetzt 2026-06-06

> Umgesetzt: rechtliche Vertretung mit Aufgabenkreisen, Vertreter-Portal (read-only, gegated), Pflicht-mit-Frist
> (§ 1863) und Ereignis-Workflow (§ 1821). Details + Datenmodell: [betreuungsrecht-vertretung.md](betreuungsrecht-vertretung.md).

**Rechtsgrundlage:** Reformiertes Betreuungsrecht (seit 1.1.2023), **§§ 1814 ff. BGB**
([gesetze-im-internet.de/bgb](https://www.gesetze-im-internet.de/bgb/)) + **Betreuungsorganisationsgesetz (BtOG)**
([gesetze-im-internet.de/btog](https://www.gesetze-im-internet.de/btog/)). Schlüssel:
- **§ 1814 / § 1815 BGB** — Betreuung wird nur für die **konkret angeordneten Aufgabenbereiche** bestellt
  (z. B. Gesundheitssorge, Vermögenssorge, Aufenthaltsbestimmung, Wohnungsangelegenheiten,
  **Postangelegenheiten**, Behördenangelegenheiten). Der Betreuer darf **nur** in seinem Aufgabenbereich handeln.
- **§ 1821 BGB** — Vorrang der **Wünsche** des Betreuten; Betreuer als Unterstützer, nicht Bestimmer.
- **§ 1827–1832 BGB** — Einwilligung in Heilbehandlung, Patientenverfügung, ärztliche Zwangsmaßnahmen,
  **§ 1831 FEM** (bei uns bereits gebaut). Vermögenssorge §§ 1835 ff.
- **Vorsorgevollmacht** (§ 1820 BGB) als Alternative zur gerichtlichen Betreuung.
- **Wahlrecht** ist höchstpersönlich (seit BVerfG 2019 / § 13 BWahlG kein Ausschluss Betreuter mehr) — der
  Betreuer wählt **nicht** für den Betreuten, kann aber bei **Postangelegenheiten** Wahlunterlagen
  entgegennehmen/assistieren.

**Datenmodell-Implikation:**
- `Vertretung` am Bewohner: Typ (gesetzl. Betreuer / Vorsorgebevollmächtigter / —), Person (ggf. eigener
  `User`-Account, Rolle `angehoeriger`/`betreuer`), **Aufgabenkreise** als Set (Enum), Nachweis (Betreuerausweis/
  Vollmacht via Strang-A-Upload), `gueltig_bis`, Gericht/Aktenzeichen.
- **Sicht/Aktionen des Vertreter-Logins werden über die Aufgabenkreise gegated** (gleiche Idee wie `Befugnis`):
  Gesundheitssorge → Sicht auf Pflege/Medikation; Vermögenssorge → Sicht auf [[Taschengeldkasse]]; Post →
  Benachrichtigungen über eingehende Post/Behörden-/**Briefwahlunterlagen**.
- **Posteingang-/Benachrichtigungs-Feature** (User-Wunsch Briefwahl): kleines `Posteingang`-Modell je Bewohner
  (Art z. B. „Wahlunterlagen", eingegangen_am) → Notification an Bewohner-User + (falls Aufgabenkreis Post)
  an den Betreuer. Nutzt den vorhandenen Notification-/`NotificationBell`-Kanal (Reverb).
- Recht: DSGVO-Zugriffskonzept, strikte Tenant-/Bewohner-Scopes (Bezug Ideen-Backlog #1).

## 2. Medizinprodukte-Betrieb (MPBetreibV 2025)

**Rechtsgrundlage:** **Verordnung über das Betreiben und Benutzen von Medizinprodukten (MPBetreibV)**, Fassung
2025 ([gesetze-im-internet.de/mpbetreibv_2025](https://www.gesetze-im-internet.de/mpbetreibv_2025/)). Gilt
ausdrücklich auch für **Pflegeeinrichtungen** (§ 2 Abs. 4). Pflichten:
- **§ 14 Bestandsverzeichnis** für **alle aktiven nichtimplantierbaren Produkte**: Bezeichnung/Art/Typ,
  Los-/Seriennummer, Anschaffungsjahr, Hersteller/Bevollmächtigter, betriebl. Identifikationsnummer, **Standort
  + betriebliche Zuordnung**.
- **§ 13 Medizinproduktebuch** für Produkte der Anlagen 1+2: Identifikation, Funktionsprüfung/Einweisung,
  eingewiesene Personen, **Fristen/Datum/Ergebnis von STK/MTK/IT-Prüfungen**, Funktionsstörungen, Vorkommnis-
  meldungen. Aufbewahrung 5 Jahre nach Außerbetriebnahme.
- **§ 4 / § 11 Einweisung**: nur eingewiesene Personen dürfen benutzen; Einweisung **dokumentieren** (Geräte
  Anlage 1 + aktive nichtimplantierbare). → **Berechtigungs-Bezug**: passt zum [[Skill-Baum]]-/`Befugnis`-Muster.
- **§ 12 sicherheitstechnische Kontrollen (STK)** alle 2 Jahre (Anlage 1); **§ 15 messtechnische Kontrollen (MTK)**
  nach Anlage-2-Fristen → **Nachweis-mit-Frist-Muster (Ampel)**, wie Wartung/Prüffristen in der [[Haustechnik Instandhaltung]].
- **§ 6 Beauftragter für Medizinproduktesicherheit** ab >20 Beschäftigten → **Beauftragten-Register** (existiert).
- **§ 19** Verstöße = Ordnungswidrigkeit.

**Datenmodell-Implikation:** `Medizinprodukt`-Stammdaten (die §-14-Felder) + Verknüpfung zur vorhandenen
`Facility`-Domäne (Wartung/Prüffrist-Ampel wiederverwenden); `MedizinproduktEinweisung` (Person/Datum) an den
Skill-Baum; STK/MTK als Nachweis-mit-Frist. MPSB als Beauftragten-Rolle (ergänzen). **Reine Foto-Archivierung
ist kein Medizinprodukt** (MDR) — Grenze siehe [[Vektor-DB-Grenze]]/Recherche.

## 3. Datenschutz — Verzeichnis von Verarbeitungstätigkeiten (VVT) + Auftragsverarbeitung (AVV) — ✅ umgesetzt 2026-06-06

> Umgesetzt: Datenschutz-Register (Domain `Compliance`) — VVT-Katalog mit Prüf-Frist-Ampel, AVV-Register und
> vorlagefähiger Art-30-Export. Details: [datenschutz-vvt-avv.md](datenschutz-vvt-avv.md).

**Rechtsgrundlage:** **Art. 30 DSGVO** (Verzeichnis von Verarbeitungstätigkeiten des Verantwortlichen) +
**Art. 28 DSGVO** (Auftragsverarbeitung, schriftlicher AVV mit Mindestinhalt) + **§ 22 BDSG** (Gesundheitsdaten
Art. 9) + behördliche Auslegung des [BfDI](https://www.bfdi.bund.de/). Pflicht: dokumentiertes VVT je
Verarbeitung (Zweck, Kategorien Betroffener/Daten, Empfänger, Löschfristen, TOM-Verweis) + AVV-Register je
Dienstleister.

**Datenmodell-Implikation:** `Verarbeitungstaetigkeit` (Norm-als-Daten-Katalog je Tenant: Zweck, Rechtsgrundlage,
Datenkategorien, Empfänger, Löschfrist, TOM) + `Auftragsverarbeitung` (Dienstleister, Vertrag via Strang-A-Upload,
geprüft_am). Beides als editierbarer Katalog wie die QM-Checkliste; Export für Aufsichtsbehörde. Verzahnt mit dem
vorhandenen `sicherheitskonzept.md`/TOM.

## 4. Beschwerdemanagement / Gewaltschutz + Heimmitwirkung (Heimbeirat)

**Rechtsgrundlage:**
- **Beschwerde/Gewaltschutz:** Qualitätsmanagement-Pflicht **§ 112/§ 113 SGB XI** + QPR-Qualitätsbereich
  „Beschwerdemanagement"; Landesheimrecht verlangt eine **Beschwerdestelle** (z. B. Bremisches Wohn- und
  Betreuungsgesetz 2022,
  [transparenz.bremen.de](https://www.transparenz.bremen.de/metainformationen/bremisches-wohn-und-betreuungsgesetz-vom-13-dezember-2022-296726)).
  Bezug Audit-Lücke #7. Deckt sich mit Ideen-Backlog #2 (anonyme Feedback-Form).
- **Heimmitwirkung:** **Heimmitwirkungsverordnung (HeimmwV)**
  ([gesetze-im-internet.de/heimmitwirkungsv](https://www.gesetze-im-internet.de/heimmitwirkungsv/)) bzw. die
  **landesrechtlichen Pendants** (z. B. Brandenburg EMitwV
  [bravors.brandenburg.de](https://bravors.brandenburg.de/de/verordnungen-212617)): **Heimbeirat** —
  Wahlberechtigung/Wählbarkeit, Mitgliederzahl, Wahlverfahren, **Aufgaben/Mitwirkungsrechte**, Amtszeit. Audit #10.

**Datenmodell-Implikation:**
- `Beschwerde` (Kategorie, Freitext, **anonym ja/nein** → bei anonym nur tenant_id, kein User-Bezug), Status
  (offen/in Arbeit/erledigt), Bezug Gewaltschutz-Vorfall. Genehmigungs-/Bearbeitungs-Workflow-Muster.
- `Gremium` generisch (Typ = Heimbeirat/Betriebsrat/…), Mitglieder mit Rolle/**Amtszeit**, Sitzungen mit
  Protokoll-Upload (Strang A) — deckt zugleich Ideen-Backlog #7 (Gremien-Modul).

## 5. Arbeitsschutz-Organisation: Betriebsarzt + Sifa (ASiG) + Gremien

**Rechtsgrundlage:** **Arbeitssicherheitsgesetz (ASiG)**
([gesetze-im-internet.de/asig](https://www.gesetze-im-internet.de/asig/)) — **§ 1**: der Arbeitgeber **muss**
Betriebsärzte (§ 2 ff.) und Fachkräfte für Arbeitssicherheit (§ 5 ff.) **bestellen**, die ihn bei Arbeitsschutz/
Unfallverhütung unterstützen; Einsatzzeiten/Betreuungsformen nach DGUV V2. Betriebsrat: **BetrVG**; Gleichstellung
**§ 13 SGB IX/BGleiG-Kontext** (Schwerbehindertenvertretung § 177 SGB IX).

**Datenmodell-Implikation:** Betriebsarzt/Sifa als **Einrichtungs-Stammdaten** (Person/Anbieter, Bestellung,
Betreuungszeiten, Besuchsprotokolle) — schon als **Beauftragten-Rolle** im Register vorhanden; eigene Stammdaten
+ Verknüpfung mit den [[Arbeitsschutz Nachweise]] (Strang C) ergänzen (Ideen-Backlog #7).

## 6. Hygiene / MRE-Surveillance — ✅ umgesetzt 2026-06-06

> Umgesetzt: Hygieneplan (Dokument-mit-Freigabe + Revisions-Ampel) und MRE-/Infektions-Surveillance je Bewohner
> mit Meldepflicht-Verfolgung (Domain `Hygiene`). Details: [hygiene-mre.md](hygiene-mre.md).

**Rechtsgrundlage:** **§ 23 IfSG** ([gesetze-im-internet.de/ifsg](https://www.gesetze-im-internet.de/ifsg/)) —
Pflicht zu **Hygieneplan**, Aufzeichnung **nosokomialer Infektionen + Erreger mit Resistenzen** und Bewertung
(KRINKO-Empfehlungen, RKI). Konkretisiert durch **Landes-Hygieneverordnungen (MedHygV/HygInfVO)** auf Basis § 23
Abs. 5/8 IfSG (z. B. Brandenburg MedHygV 2016
[bravors.brandenburg.de](https://bravors.brandenburg.de/verordnungen/medhygv_2016)).

**Datenmodell-Implikation:** `Hygieneplan` (Dokument-mit-Freigabe), `InfektionsEreignis`/`MRE-Befund` je Bewohner
(append-only Surveillance-Liste), Hygienebeauftragte/-kommission (Beauftragten-Register). Frist-/Schulungs-
Nachweise über Nachweis-mit-Frist. Audit-Lücke „Hygiene/MRE".

## 7. Fortbildungspflicht (Skill-Baum-Erweiterung) — ✅ umgesetzt 2026-06-06

> Umgesetzt: Fortbildungsplan mit Pflicht-Themen-Matrix und Auffrischungs-Ampel (Domain `Personnel`). Details:
> [fortbildungsplan.md](fortbildungsplan.md).

**Rechtsgrundlage:** Fortbildungspflicht aus **Landesheimrecht/WTG** + **§ 132a SGB V**-Rahmenverträge + QPR;
Pflegefachkräfte: regelmäßige Fortbildung als Qualitätskriterium. **Datenmodell-Implikation:** `Fortbildung`
(geplant/absolviert je Person, Pflicht mit Frist) an die vorhandenen `Kompetenz`/`MitarbeiterKompetenz` andocken
(Nachweis-mit-Frist) + Fortbildungswünsche. Ideen-Backlog #8/Doku.

## 8. Bundesland-Overrides (föderales Heimrecht)

**Rechtsgrundlage:** Seit der Föderalismusreform 2006 ist das Heimrecht **Landesrecht** (WTG NRW, BbgPBWoG,
Bremisches WBG u. a.) — **Nachtdienst-Schlüssel, Fachkraftquote, Heimbeirat, Meldepflichten** variieren je Land.
**Datenmodell-Implikation:** die vorhandenen Default-Kataloge (PAW/Fachkraftquote/Schichtregeln/Beauftragte) um
eine **Bundesland-/Träger-Override-Ebene** ergänzen (Norm-als-Daten: Default → Landes-Override). Offen aus
`OFFENE-PUNKTE.md` §B.

## 9. Energielevel-Ampel (Beschäftigtendaten)

**Rechtsgrundlage:** **§ 87 Abs. 1 BetrVG** (Mitbestimmung des Betriebsrats bei techn. Überwachungseinrichtungen)
+ **§ 26 BDSG** (Beschäftigtendatenschutz, Freiwilligkeit). **Datenmodell-Implikation:** nur aktueller Wert +
aggregierter Hausschnitt, **kein** personenbezogenes Verlaufstracking; Freiwilligkeit + BR-Mitbestimmung
dokumentieren. Ideen-Backlog #6.

## 10. Freie Buchung im Hauptbuch (Buchhaltungs-Lücke)

**Befund (User 2026-06-06):** `/buchhaltung` kann heute nur Wareneingang/-verbrauch — **keine generische
Einzahlung/Zahlung** im Hauptbuch. (In der [[Taschengeldkasse]] existiert Einzahlung bereits.) **Rechtsgrundlage:**
GoB / **PBV** ([gesetze-im-internet.de/pbv](https://www.gesetze-im-internet.de/pbv/)). **Datenmodell-Implikation:**
die vorhandene `Buchen`-Action hat bereits alles; nur eine freie Buchungsmaske (Soll/Haben/Betrag/Beleg) im
Buchhaltungs-Livewire ergänzen — kleiner Aufwand, hoher Nutzen.

---

## Empfohlene Reihenfolge (Rechtsrisiko × Reife)

1. **MPBetreibV-Bestandsverzeichnis** (§ 13/§ 14) — hohes Risiko, volle Datenklarheit, reine Stammdaten + Frist-Ampel.
2. **Beschwerde/Gewaltschutz + Heimbeirat** — QPR-relevant, Workflow-Muster vorhanden.
3. **Bewohner/Betreuer als Nutzer** (Aufgabenkreise + Posteingang/Briefwahl-Benachrichtigung) — User-Wunsch, baut auf RBAC.
4. **Datenschutz VVT/AVV** — Katalog-Muster, mittlerer Aufwand.
5. **Hygiene/MRE**, **Betriebsarzt/Sifa-Stammdaten + Gremien**, **Fortbildungsplan** — je eigenes Muster vorhanden.
6. **Bundesland-Overrides**, **freie Hauptbuchung**, **Energielevel** — Querverbesserungen.

Querbezug: [OFFENE-PUNKTE.md](OFFENE-PUNKTE.md), [Ideen-Backlog](ideen-backlog-2026-06.md),
[Norm-Recherche 2026-06](recherche-normen-erweiterung-2026-06.md), [Taschengeldkasse](taschengeldkasse.md).
