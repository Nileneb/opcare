# Konzept (noch nicht implementiert): BtM-Nachweis + FEM-Genehmigung

Recherche-Strang E — die beiden Bereiche mit dem höchsten **Rechtsrisiko** (Straf-/Freiheitsentzug). Hier
bewusst **nur als Umsetzungskonzept** festgehalten; Implementierung folgt separat (Auftrag: „E erstmal nur
dokumentieren").

## E1 — Betäubungsmittel-Nachweisführung (BtMVV § 13)

**Pflicht:** Lückenlose Dokumentation von Zugang (BtM-Rezept) und Abgang (Verabreichung) je BtM-pflichtiger
Substanz und Bewohner; monatlicher Abschluss mit Prüfung + Gegenzeichnung; Aufbewahrung 3 Jahre. Stationäre
Altenpflege: **kein** Notfallvorrat (nur Hospiz/SAPV), BtM sind bewohnerbezogen und rezeptpflichtig.

**Geplante Abbildung (Erweiterung Medikationsmodul):**
- `btm`-Flag je `MedProduct` ist bereits vorhanden → BtM-pflichtige Produkte sind erkennbar.
- Neues **BtM-Konto je Bewohner + Substanz**: Transaktionen (Zugang/Abgang, Menge, Datum, durchführende Person,
  Restbestand), lückenlos und unveränderbar (append-only, wie das SIS-Berichteblatt).
- **Monatsabschluss-Workflow**: Soll-/Ist-Bestand, Prüfung + Signatur (analog § 14-Begründung im Dienstplan),
  Druck-/Export-Funktion für die Behörde; 3-Jahres-Archiv.
- Verknüpfung mit der vorhandenen Gabe-Dokumentation (jede BtM-Gabe erzeugt automatisch einen Abgang).

**Wiederverwendbare Bausteine:** append-only-Journal (wie Berichteblatt), Prüf-/Signatur-Workflow (wie § 14),
Behörden-Export (wie QDVS/FHIR).

## E2 — Freiheitsentziehende Maßnahmen (FEM, § 1831 BGB)

**Pflicht:** Richterliche Genehmigung (Betreuungsgericht) vor jeder FEM (außer ärztlich attestierte
Bewegungsunfähigkeit); Antrag mit ärztlichem Attest; laufende Verhältnismäßigkeits-Überprüfung; sofortige,
dokumentierte Beendigung bei Wegfall der Indikation; Nachweis geprüfter Alternativen.

**Geplante Abbildung (Erweiterung CareEvent/Quality):**
- FEM wird heute schon als `CareEvent` erfasst → Ausbau zu einem **Genehmigungs-/Fristen-Workflow**:
  Antrag anlegen → ärztliches Attest als Dokument anhängen (nutzt **Strang A**, MinIO-Datei-Upload) →
  Gerichtsbeschluss + Geltungsdauer hinterlegen → **Review-Reminder** (nutzt **Strang C**, Nachweis-mit-Frist:
  Genehmigung als fristgebundener „Nachweis" mit Ampel) → Beendigungsprotokoll → Alternativen-Dokumentation.
- Status-Ampel: genehmigt-gültig / Review fällig / ohne Genehmigung (rot, Eskalation).

**Synergie:** E2 ist im Wesentlichen die Komposition aus den bereits gebauten Strängen A (Dokument-Upload) +
C (Nachweis-mit-Frist) + dem bestehenden CareEvent — daher mit überschaubarem Aufwand umsetzbar.

## Reihenfolge-Empfehlung für die Umsetzung

1. **E1 BtM** zuerst (höchstes Straf-/Prüfrisiko, klar abgegrenzt, Medikationsmodul als Basis).
2. **E2 FEM** danach (komponiert A+C+CareEvent; profitiert vom fertigen Datei-Upload + Fristen-Mechanismus).
