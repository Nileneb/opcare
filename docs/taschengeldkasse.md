# Taschengeldkasse / Barbetragsverwaltung (§ 27b SGB XII)

Treuhänderische Verwaltung des Bewohner-Bargelds durch die Einrichtung — bewohnerbezogen, getrennt vom
Einrichtungsvermögen, mit lückenlosem Buchungsjournal, Budget-Setzungen und monatlicher Rechnungslegung.
Schließt **Audit-Lücke #6** (Barbetragsverwaltung).

![Taschengeldkasse](screenshots/taschengeldkasse.png)

## Rechtsgrundlagen (per Legal Data Hunter belegt)

- **§ 27b SGB XII** — Anspruch auf einen angemessenen **Barbetrag** für Leistungsberechtigte in Einrichtungen
  (das „Taschengeld"); die Geldquelle, die hier treuhänderisch verwaltet wird.
- **Heimsicherungsverordnung (HeimSiV)** — [gesetze-im-internet.de/heimsicherungsv](https://www.gesetze-im-internet.de/heimsicherungsv/),
  Bundes-Blaupause für getrennt verwaltete Bewohnergelder:
  - **§ 8** — Verwaltung „getrennt von seinem Vermögen … für Rechnung des einzelnen Bewerbers oder Bewohners"
    auf einem **Sonderkonto** bei einem Kreditinstitut; Pflicht zur Pfändungs-/Insolvenzbenachrichtigung und
    jederzeitigen Kontoauskunft.
  - **§ 15** — **Rechnungslegung** gegenüber dem Bewohner.
  - **§ 17** — **prüfungsfähige Aufzeichnungen**, aus denen *Art und Höhe je Bewohner* und die getrennte
    Verwaltung ersichtlich sind (Einzelbelegpflicht).
  - **§ 16/§ 19** — jährliche Prüfung durch geeignete Prüfer + Prüfungsbericht an die zuständige Behörde
    (Heimaufsicht); **§ 20** — Verstoß ist Ordnungswidrigkeit.
- **Pflege-Buchführungsverordnung (PBV)** — [gesetze-im-internet.de/pbv](https://www.gesetze-im-internet.de/pbv/),
  Rechnungs- und Buchführungspflichten der Pflegeeinrichtungen (GoB).
- Landesheimrecht (z. B. **BbgPBWoG** Brandenburg) verankert Verwaltung von Bewohnergeldern + Heimaufsicht.

> Quellen ermittelt mit dem Legal-Data-Hunter-MCP (Volltexte aus `DE/BGBl`). Fachliche Prüfung durch eine
> qualifizierte Person bleibt erforderlich.

## Datenmodell (`app/Domains/Accounting`)

| Tabelle | Zweck | Schlüssel-Eigenschaften |
|---|---|---|
| `treuhand_konten` | ein Treuhandkonto **je Bewohner** | `unique(tenant_id, resident_id)`, optionale `iban` (Sonderkonto § 8), offen/geschlossen |
| `treuhand_buchungen` | **append-only** Einzelbuchungen | `const UPDATED_AT = null`, fortlaufende `lfd_nr`, vorzeichenbehafteter `betrag`, fortgeschriebenes `saldo_nach`, `kategorie`, Pflicht-`zweck`, `beleg_nr`, `erfasst_von` |
| `treuhand_budgets` | Budget je Kategorie/Gesamt | `unique(treuhand_konto_id, kategorie)`, `limit_betrag`, `warn_prozent`, `sperre` |
| `treuhand_monatsabschluesse` | Rechnungslegung (§ 15) | `unique(konto, monat)`, Anfangs-/Endbestand + Summen, `gesperrt_am` |

Enums: `TreuhandVorgang` (Einzahlung/Auszahlung/Korrektur) · `BarbetragKategorie` (Friseur/Körperpflege/
Kleidung/Freizeit/Barauszahlung/Sonstiges).

## Buchungslogik — `Actions\TreuhandBuchen` (BtM-Muster)

- **Append-only** (§ 17): Buchungen werden nie geändert; eine Korrektur ist eine neue, vorzeichenbehaftete
  Buchung mit Bezug auf die Fehlbuchung **+ Pflichtgrund**.
- Der **Saldo darf nicht negativ** werden — fremdes Geld wird nicht überzogen.
- **Verwendungszweck ist Pflicht** (Einzelbelegpflicht).
- **Budget-Sperre:** eine Auszahlung, die ein Kategorie- **oder** Gesamtbudget mit aktiver Sperre reißt, wird
  abgewiesen (`InvalidArgumentException`) — kein stilles Schlucken.

## Budget-Setzungen (Warn-/Sperr-Ampel)

`Support\BudgetMonitor` + `Support\BudgetStatus`: der **Verbrauch** ist der Netto-Abfluss des Topfes im Monat
(Auszahlungen − rückbuchende Korrekturen). Ampel: `grün` < Warn-% ≤ `gelb` < 100 % ≤ `rot`. Bei `sperre=true`
blockiert die Action die Überschreitung; ohne Sperre wird nur gewarnt. Das Wertobjekt ist generisch — später
auch für Wirtschaftsbudgets der Buchhaltung nutzbar.

## Berechtigung

Zugriff nur für Verwaltung/Buchhaltung: `isSuperAdmin()` **oder** Rolle `admin`/`buchhaltung` (Spatie-Role) —
treuhänderische Verwaltung gehört nicht in die Pflege-Rollen.

## Anbindung VLM-Beleg-Capture

Das `beleg_nr`-Feld ist die Naht zur dokumentierten [VLM-Beleg-Capture](ai-services-plan.md): Foto → Vorschlag
„Auszahlung" → Budget-Prüfung → berechtigte Bestätigung bucht. Foto-Upload (AttachmentService) folgt dort.

## Tests

- `tests/Feature/Accounting/TreuhandBuchenTest.php` — Saldo-/lfd_nr-Fortschreibung, Überziehungs-Schutz,
  Zweck-Pflicht, Budget-Sperre blockt vs. Warn-Budget lässt zu, Korrektur.
- `tests/Feature/Accounting/TaschengeldkasseTest.php` — UI: 403 ohne Rolle, ein Konto je Bewohner,
  Ein-/Auszahlung, Sperr-Budget blockt, Monatsabschluss-Summen + Sperre.

## Bedienung

Finanzen → **Taschengeldkasse**: Konto je Bewohner anlegen, buchen, Budgets je Kategorie setzen, monatlich
abschließen. Demo-Daten im `DemoSeeder` (Maria Schneider: Einzahlung + zwei Auszahlungen, Friseur-Warnbudget,
Gesamt-Sperrbudget).
