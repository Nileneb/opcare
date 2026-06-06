# Ideen-Backlog (2026-06-06)

Vom User eingebrachte Feature-Ideen — hier festgehalten, noch **nicht** umgesetzt (Umsetzung nach Priorisierung).

## 1. Bewohner (und Angehörige) als echte Nutzer

**Heute:** Bewohner haben **kein** E-Mail-Feld und sind nicht mit einem `User` verknüpft (Tabelle `residents`:
name, geburtsdatum, geschlecht, pflegegrad, aufnahme/entlassung, status).

**Idee:** Optionaler Self-Service-Zugang für Bewohner und/oder Angehörige.
- E-Mail-Feld + optionaler verknüpfter `User`-Account je Bewohner (Rolle `bewohner` / `angehoeriger`).
- Eingeschränkte Sicht: eigene Stammdaten, Dokumente (die für sie freigegeben sind — nutzt Strang A), Speiseplan/Menüwahl,
  Betreuungs-/Präventionsangebote, Taschengeldkonto (s. u.).
- Angehörige: Kontaktperson → eigener Login mit Sicht auf den zugeordneten Bewohner (Read + definierte Aktionen).
**Recht:** DSGVO-Zugriffskonzept, Einwilligung/Vollmacht (bei dementen Bewohnern über Betreuer), strikte Tenant-/Bewohner-Scopes.
**Aufwand:** mittel. Baut auf vorhandenem RBAC + Tenant-Scope + Strang-A-Freigabe auf.

## 2. Anonymisierbare Feedback-Form

**Idee:** Bewohner/Angehörige/Mitarbeitende geben Feedback; Absender wahlweise **anonym**. Auswertung als
QM-Instrument (Beschwerdemanagement § 114 SGB XI — deckt sich mit Audit-Lücke „Beschwerdemanagement").
- Felder: Kategorie, Freitext, optional Bewertung, „anonym ja/nein".
- Bei anonym: kein User-Bezug gespeichert, nur tenant_id + Inhalt. Leitung sieht Eingänge + Status (offen/in Arbeit/erledigt).
**Aufwand:** klein–mittel.

## 3. Taschengeldkonto / Barbetragsverwaltung (§ 27b SGB XII)

**Idee (User):** Angehörige (z. B. von Demenzkranken) hinterlegen Geld (Friseur etc.), die Pflege zahlt treuhänderisch aus.
**Deckt sich mit Audit-Lücke #6** (Barbetragsverwaltung). 
- Bewohner-Treuhandkonto mit lückenlosem Transaktionsjournal (Einzahlung/Auszahlung, Beleg, Saldo).
- Pfändungsschutz (§ 27b SGB XII), Einzelbelegpflicht, Monats-/Jahresbericht für Betreuungsgericht.
- Verknüpfung mit Buchhaltung (eigenes Treuhand-Konto, getrennt vom Einrichtungsvermögen).
- Mit Idee #1: Angehörige sehen den Kontostand + können Einzahlungen avisieren.
**Recht:** GoB, Treuhand getrennt führen, prüfbar durch Heimaufsicht. **Aufwand:** mittel (Buchhaltung als Basis).

## 4. Dienstplan: Übergangs-/Spitzendienste

**Heute:** `ShiftKind` hat bereits `Zwischendienst`; Schichten sind frei mit Beginn/Ende definierbar.
**Idee:** Kurze, gezielte Dienste für **Bedarfsspitzen** (z. B. Frühstück, Mittag, Abendversorgung) als eigenes Konzept:
- Schicht-Vorlagen mit Spitzenzeit-Markierung; im Betreuungsschlüssel-Soll (Strang B) tageszeitabhängig gewichten.
- Vorschläge, wo zusätzliche kurze Dienste den Soll-Ist-Deckungsgrad zur Spitzenzeit verbessern.
**Aufwand:** klein (Erweiterung Schicht-Stammdaten) bis mittel (Spitzenzeit-Gewichtung im Schlüssel).

## 5. Automatischer Dienstplan-Generator ✅ (umgesetzt 2026-06-06)

**Idee (User):** Aus Soll-Dienstplan (Betreuungsschlüssel, Strang B), Negativ-Regeln (ArbZG), Positiv-Regeln
(Ergonomie, Strang B) und Wunschdienstplan automatisch einen Dienstplan erzeugen, den die PDL nur prüft/freigibt.
- **Constraint-Solver**: harte Constraints (ArbZG, Qualifikation, Mindestbesetzung je Schicht/Pflegegrad-Soll) +
  weiche Constraints (Wünsche, Ergonomie-Regeln, Wochenend-Gerechtigkeit) als Zielfunktion.
- Ansatz: regelbasierter Greedy + lokale Optimierung, oder ein CP/ILP-Solver. Ergebnis = Entwurf → PDL bestätigt/ändert.
- Alle vier vorhandenen Bausteine sind die Eingaben — das Feature ist die Krönung von Strang B + Wunschdienstplan.
**Aufwand:** groß (Kern-Algorithmus + Tuning), aber hoher Nutzen. Eigene Iteration wert.
**Umgesetzt:** Greedy-Constraint-Generator (`DienstplanGenerator`) — füllt offene Slots als Vorschlag (PDL prüft/gibt frei),
harte ArbZG-Filter + Ergonomie-/Fairness-/Wunsch-Scoring, Unterdeckung transparent gemeldet. Siehe [auto-dienstplan.md](auto-dienstplan.md).

## 6. Energielevel-Ampel (Mitarbeiter-Wohlbefinden, freiwillig)

**Idee (User):** Mitarbeitende stellen ihr **aktuelles Energielevel** auf einer Skala `|--------|` ein (kein Zahlenwert,
Farbverlauf rot→grün), dynamisch änderbar. Anzeige der **Gesamtenergie des Hauses in Echtzeit**. Stellt jemand ganz
auf Rot (links), bekommt die vorgesetzte Person eine Push-Nachricht.
- **Kein Überwachungsinstrument** — Nutzen für den Arbeitnehmer + hilft der Führung, Aufgaben in „Energie-hoch-Zeiten" zu legen.
- Datenschutz: freiwillig, kein Verlaufstracking je Person (nur aktueller Wert + aggregierter Hausschnitt), Push nur bei Rot.
- Technik: Slider-Wert (0..1, intern), Aggregat als Durchschnitt; Push via Reverb/WebSocket (Stack hat `reverb`) oder Notification.
- Langfristig (opt-in, aggregiert): Tageszeit-Energieprofil als Planungshilfe (Bezug zu Idee #5).
**Recht:** Mitbestimmung (Betriebsrat), Freiwilligkeit, Datensparsamkeit. **Aufwand:** mittel.

## 7. Gremien-Modul + Betriebsarzt (Stammdaten)

**Idee (User):** Ein **Gremien-Modul** für Aufsichtsrat, Betriebsrat, Gleichstellungsbeauftragte (und Heimbeirat,
s. Audit-Lücke #10) — Mitglieder, Amtszeiten, Sitzungsprotokolle, Beschlüsse. Außerdem ist aktuell **kein Betriebsarzt
hinterlegbar** (ASiG-Pflicht, s. Arbeitsschutz-Recherche): Betriebsarzt + Fachkraft für Arbeitssicherheit (Sifa) als
Einrichtungs-Stammdaten mit Beauftragung, Betreuungszeiten, Besuchsprotokollen.
- Gremium = generisches Modell (Typ, Mitglieder mit Rolle/Amtszeit, Sitzungen mit Protokoll/Upload via Strang A).
- Betriebsarzt/Sifa als Einrichtungs-Stammdaten (eigene kleine Tabelle), verlinkt mit den Arbeitsschutz-Nachweisen (Strang C).
**Bezug:** Heimbeirat (Audit #10), ASiG (Arbeitsschutz-Recherche §11). **Aufwand:** mittel.

## 8. Fortbildung, Skill-Baum & Delegations-Berechtigungen

**Idee (User):** Beim Personal fehlen **Fortbildungsplan**, **Fortbildungswünsche** und ein **„Skill-Baum"** —
Zusatzqualifikationen über die Grundqualifikation hinaus, z. B. **Wundmanager:in**, **Insulin-Gabe-Berechtigung**,
Behandlungspflege LG1/LG2, Praxisanleiter:in, Hygienebeauftragte:r. Manche Pflegehelfer:innen dürfen z. B. Insulin
spritzen, andere nicht — das soll hinterlegt sein.
- **Skill-/Kompetenz-Katalog** je Einrichtung (Kompetenz → wer darf sie erteilen/nachweisen, Gültigkeit/Auffrischung
  → nutzt das **Nachweis-mit-Frist-Muster** aus Strang C).
- **Mitarbeiter-Kompetenzen**: welche Zusatzkompetenzen hat eine Person (mit Nachweis/Datum).
- **Berechtigungsmatrix**: welche Tätigkeit darf mit welcher (Zusatz-)Qualifikation **durchgeführt/abgehakt** werden
  (z. B. „Insulingabe" nur mit Kompetenz „Insulin-Berechtigung"). Greift in Medikation/Pflegedoku (Abzeichnen).
- **Fortbildungsplan + -wünsche**: geplante/absolvierte Fortbildungen je Person, Pflichtfortbildungen mit Frist.

### Delegation ärztlicher Tätigkeiten (Arzt → Pflege)
Der User fragt zur **Delegation** (z. B. Arzt delegiert Blutabnahme an eine Pflegekraft) und ob das über die **ePA**
läuft. Fachliche Einordnung (zu verifizieren bei Umsetzung):
- Delegation ärztlicher Tätigkeiten an Pflegefachkräfte ist etabliert (haftungsrechtlich: Anordnungs-/Durchführungs-
  verantwortung; BÄK/DBfK-Empfehlungen). Voraussetzung: Qualifikation + dokumentierte ärztliche Anordnung.
- **ePA**: Die ePA (§ 341 ff. SGB V, ePA „für alle" 2025) ist primär eine **Dokumenten-/Befund-/Medikationsakte**;
  ein standardisiertes, maschinenlesbares **Delegations-Objekt** ist dort derzeit **nicht** als eigene Struktur
  vorgesehen. Delegation/Anordnung lebt in der Pflege-/Verordnungsdoku (in opcare: Verordnung + Abzeichnen) und ließe
  sich als **FHIR `ServiceRequest`** (Anordnung mit `performer`/`requester`) modellieren — ggf. als ePA-Dokument ablegbar.
  Realistischer erster Schritt: **interne Delegations-/Anordnungsverwaltung** (Arzt ordnet an → berechtigte Pflegekraft
  führt durch + zeichnet ab), Berechtigung über den Skill-Baum geprüft. ePA-Anbindung später (Track C / Konnektoren).
**Aufwand:** groß (Katalog + Matrix + Doku-Integration), hoher fachlicher Wert. Eigene Iteration.

---

**Bezug zur Norm-Recherche:** #3 ist Audit-Lücke #6 (Barbetragsverwaltung), #2 berührt Audit-Lücke #7
(Beschwerde/Gewaltschutz), #7 berührt Audit-Lücke #10 (Heimbeirat) + ASiG. #1/#6/#8 sind neue Querschnittsfunktionen.
Siehe [recherche-normen-erweiterung-2026-06.md](recherche-normen-erweiterung-2026-06.md).
