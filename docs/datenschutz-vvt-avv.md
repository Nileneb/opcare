# Datenschutz-Register: VVT + AVV (Art. 30/28 DSGVO)

Umgesetzt 2026-06-06. Macht zwei DSGVO-Pflichten als editierbaren Katalog operativ — analog zur
[QM-Checkliste](qm-checkliste.md): *Norm als Daten* + *Nachweis-mit-Frist (Ampel)*.

## Rechtsgrundlage

| Norm | Pflicht | App-Logik |
|---|---|---|
| **Art. 30 Abs. 1 DSGVO** | Verzeichnis von Verarbeitungstätigkeiten des Verantwortlichen | `Verarbeitungstaetigkeit`-Katalog je Mandant |
| **Art. 9 Abs. 2 lit. h DSGVO · § 22 BDSG** | besondere Kategorien (Gesundheitsdaten) | `Rechtsgrundlage::Gesundheitsdaten` mit `besondereKategorie()` |
| **Art. 28 DSGVO** | schriftlicher AV-Vertrag mit Mindestinhalt je Dienstleister | `Auftragsverarbeitung`; ohne `vertrag_geschlossen_am` → rot |
| **Art. 32 DSGVO** | TOM dokumentieren | `tom`-Feld je Verarbeitung (Verweis aufs Sicherheitskonzept) |

Recherche: [recherche-offene-punkte-2026-06.md §3](recherche-offene-punkte-2026-06.md). Datenschutzbeauftragte:r
ist im [Beauftragten-Register](beauftragte.md) hinterlegt (Art. 37 DSGVO / § 38 BDSG).

## Verzeichnis von Verarbeitungstätigkeiten (Art. 30)

`Verarbeitungstaetigkeit` trägt die Pflichtangaben: Zweck, **Rechtsgrundlage** (Enum mit `artikel()`),
Kategorien Betroffener/Daten, Empfänger, Drittlandtransfer, Löschfrist, TOM-Verweis. Aus `geprueft_am` +
`pruef_intervall_monate` ergibt sich die **Aktualitäts-Ampel** (`ungeprueft`/`ueberfaellig` → rot, `faellig` →
amber, sonst grün). `VvtDefaults::ensureFor()` seedet die typischen Verarbeitungen einer Pflegeeinrichtung
(Pflegedokumentation, Medikation/BtM, Abrechnung, Personalverwaltung, Belegung/Vertretung, Hygiene-Surveillance,
optional Videoüberwachung) — editierbar, nicht abschließend.

## Auftragsverarbeitungen (Art. 28)

`Auftragsverarbeitung` (Dienstleister, Zweck, Datenkategorien, Drittland, Unterauftragnehmer, optional an eine
Verarbeitungstätigkeit gekoppelt). Fehlt das Vertragsdatum, ist der Eintrag **rot** (kein AVV nachgewiesen);
mit Vertrag greift die Prüf-Frist-Ampel.

## Art-30-Export

`Art30Export::render()` erzeugt das vorlagefähige Verzeichnis als Klartext (Verantwortlicher, je Verarbeitung
alle Art-30-Felder, anschließend die Art-28-Dienstleister) — Download über die Leitungs-Oberfläche, vorlegbar
gegenüber der Aufsichtsbehörde. Als reine Textfunktion unabhängig vom Download-Kanal testbar.

## Datenmodell & Zugriff

- `verarbeitungstaetigkeiten`, `auftragsverarbeitungen` (Domain `Compliance`), tenant-gescopt über `BaseModel`.
- Oberfläche `app/Livewire/Compliance/Datenschutz.php` (Route `datenschutz`, Nav „Datenschutz") — nur Leitung
  (`admin`/`pflegefachkraft`/super-admin). AVV-`exists:`-Validierung tenant-gescopt (`tenantExists`, IDOR).

Screenshot: `storage/app/shots/datenschutz.png`. Tests: `tests/Feature/Compliance/DatenschutzTest.php`.
