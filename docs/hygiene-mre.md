# Hygiene & MRE-Surveillance (§ 23 IfSG)

Umgesetzt 2026-06-06. Bildet die infektionshygienischen Aufzeichnungspflichten als App-Logik ab —
*Dokument-mit-Freigabe* (Hygieneplan) + fortlaufende *Surveillance-Liste mit Meldepflicht-Verfolgung*.

## Rechtsgrundlage

| Norm | Pflicht | App-Logik |
|---|---|---|
| **§ 23 Abs. 5 IfSG** | einrichtungsspezifischer Hygieneplan | `Hygieneplan` (versioniert, Freigabe, Revisions-Ampel) |
| **§ 23 Abs. 4 IfSG** | Aufzeichnung nosokomialer Infektionen + Erreger mit Resistenzen, Bewertung | `InfektionsBefund` je Bewohner (Surveillance-Liste) |
| **§§ 6/7 IfSG** | Meldung melde-/häufungspflichtiger Erreger ans Gesundheitsamt | `meldepflichtig` + `gemeldet_am`; offene Meldung → rot |
| **§ 23 IfSG · DGKH** | Hygienebeauftragte:r benannt/qualifiziert | [Beauftragten-Register](beauftragte.md) (Rolle `hygiene`) |

KRINKO/RKI-Empfehlungen konkretisieren die Maßnahmen; Landes-Hygieneverordnungen (MedHygV/HygInfVO) ergänzen
mandantenabhängig. Recherche: [recherche-offene-punkte-2026-06.md §6](recherche-offene-punkte-2026-06.md).

## Hygieneplan (Dokument-mit-Freigabe)

`Hygieneplan` (Titel, Version, Inhalt/Verweis, `freigegeben_am`/`freigegeben_von`, `revision_intervall_monate`).
Ein Entwurf (nie freigegeben) ist **rot**; nach Freigabe ergibt sich aus dem Freigabedatum + Intervall die
**Revisions-Ampel** (`ueberfaellig` → rot, `faellig` → amber, sonst grün).

## MRE-/Infektions-Surveillance

`InfektionsBefund` je Bewohner:in: `Erreger` (Enum mit `istMre()` für die multiresistenten Erreger und
`meldeRelevant()` als Meldepflicht-Vorschlag), `BefundArt` (Besiedlung/Infektion/**nosokomiale Infektion** —
`bewertungspflichtig()` nach § 23 Abs. 4), Feststellungs-/Aufhebungsdatum, Maßnahmen. Die Liste wird
änderungsarm geführt: ein Befund wird erfasst und später aufgehoben (Sanierung/Genesung).

**Meldepflicht wird nie stillschweigend übergangen:** beim Erfassen schlägt der Erreger die Meldepflicht vor
(durch die Fachkraft je Fall korrigierbar); ein meldepflichtiger Befund ohne `gemeldet_am` bleibt **rot**, bis
die Meldung ans Gesundheitsamt dokumentiert ist. Ampel: aktiv & Meldung offen → rot · aktiv → amber · aufgehoben
→ grün.

## Datenmodell & Zugriff

- `hygieneplaene`, `infektions_befunde` (Domain `Hygiene`), tenant-gescopt über `BaseModel`.
- Oberfläche `app/Livewire/Hygiene/Hygiene.php` (Route `hygiene`, Nav „Hygiene/MRE") — nur Leitung/Pflegefachkraft.
  Bewohner-`exists:`-Validierung tenant-gescopt (`tenantExists`, IDOR).

Screenshot: `storage/app/shots/hygiene.png`. Tests: `tests/Feature/Hygiene/HygieneTest.php`.
