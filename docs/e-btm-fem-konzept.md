# BtM-Nachweis (§ 13 BtMVV) + FEM (§ 1831 BGB) — implementiert

Recherche-Strang E, **rechtssicher umgesetzt** (2026-06-06, quellengestützte Recherche + adversarial geprüft).
Beide Bereiche mit dem höchsten Rechtsrisiko (Straf-/Freiheitsentzug).

> Screenshots: Wiki-Seiten **BtM Nachweis** und **FEM**.

## E1 — Betäubungsmittel-Nachweisführung (§ 13 BtMVV)

**Rechtssichere Eckpunkte (umgesetzt):**
- **Bewohnerbezogen**: jedes Konto = genau 1 Bewohner + 1 Substanz (§ 5c BtMVV — kein Stationsvorrat in Pflegeheimen).
- **Append-only**: Buchungen werden nie geändert/gelöscht (`BtmBuchung` ohne `updated_at`); Fehler werden über eine
  **Korrektur**-Buchung mit Bezug auf die Fehlbuchung + Pflichtgrund neutralisiert.
- **Fortlaufende Nummer + fortgeschriebener Bestand** je Buchung; Abgang über den Bestand hinaus wird abgelehnt.
- **Vorgänge**: Lieferung (Zugang, mit Lieferant/Apotheke + verschreibendem Arzt), Gabe an Bewohner (namentlich
  erfasste Pflegekraft), **Vernichtung** (Zwei-Zeugen-Prinzip, BtMG § 16, + Methode), Rücknahme Apotheke, Transfer, Korrektur.
- **Monatsabschluss** (§ 13 Abs. 2): Soll-Bestand (berechnet) vs. Ist-Bestand (Zählung), Prüfung durch den
  verantwortlichen Arzt mit Namenszeichen + Datum; bei Differenz **Begründung Pflicht**; nach dem Abschluss **gesperrt** (read-only).
- **Aufbewahrung 3 Jahre** (§ 13 Abs. 3) — im UI ausgewiesen.

**Bausteine:** `Enums/BtmVorgang`, `Models/BtmKonto`/`BtmBuchung`/`BtmMonatsabschluss`, `Actions/BtmBuchen`
(append-only Fortschreibung in DB-Transaktion mit Lock), Livewire `Medication/BtmNachweis` (Route `/medikation/btm`,
Nav „BtM-Nachweis", Rollen admin/pflegefachkraft). Tests: `tests/Feature/Medication/BtmNachweisTest.php`.

## E2 — Freiheitsentziehende Maßnahmen (§ 1831 BGB, seit 2023)

**Rechtssichere Eckpunkte (umgesetzt):**
- **Mildere Mittel sind Pflicht** (Werdenfelser Weg / Ultima Ratio): Auswahl geprüfter Alternativen + Pflicht-Begründung,
  warum sie nicht ausreichen — vor dem Anlegen erzwungen.
- **Genehmigungsweg**: Status beantragt → genehmigt (mit **Aktenzeichen + Gericht + Beschlussdatum + Befristung**) →
  bei „genehmigt" sind diese Felder Pflicht. Auch: Bewohner-Einwilligung (einwilligungsfähig), Notfall (nachzuholen),
  ohne Genehmigung (Eskalation).
- **Befristungs-Ampel** (FamFG § 329: max. 1–2 Jahre): grün (gültig) → gelb (`Überprüfung fällig`, ≤ 30 Tage) →
  rot (`abgelaufen`, darf nicht fortgeführt werden). Kopf zeigt „X mit Handlungsbedarf".
- **Laufendes Überwachungsprotokoll** (Kontrolle/Vitalzeichen) mit Indikationsprüfung; **Beendigung** mit Grund,
  automatisch als Protokolleintrag.
- **Dokumente** (ärztliches Attest, Gerichtsbeschluss) werden angehängt (spatie media, konfigurierbare Disk → Strang A).

**Bausteine:** `Enums/FemArt`/`FemEinwilligung`, `Models/FemFall` (HasMedia, Status/Ampel) + `FemProtokoll`,
Livewire `Quality/FemUebersicht` (Route `/qualitaet/fem`, Nav „FEM", Rollen admin/pflegefachkraft).
Tests: `tests/Feature/Quality/FemTest.php`.

## Komposition

Wie im Konzept vorgesehen komponiert E2 die bereits gebauten Stränge: **Strang A** (Dokument-Upload an den FEM-Fall)
und das **Ampel-/Fristen-Muster** (analog Strang C). E1 nutzt die append-only/Signatur-/Sperr-Muster (analog SIS-Berichteblatt + § 14).
