# Buchhaltung & Warenwirtschaft

Doppelte Buchführung (Soll/Haben) je Einrichtung, verzahnt mit der Lagerwirtschaft der Abteilungen.
Jeder Materialfluss schlägt automatisch als Buchung durch — kein totes Feature, sondern der
operative Andockpunkt für HGB-Buchführung (§§ 238 ff.) und die Pflege-Buchführungsverordnung (PBV).

> Screenshot der Oberfläche: siehe Wiki-Seite **Buchhaltung Warenwirtschaft**.

## Domäne `app/Domains/Accounting`

| Baustein | Aufgabe |
|---|---|
| `Enums/KontoTyp` | Kontoart (Aktiv/Passiv/Aufwand/Ertrag) + Normalsaldo-Seite (`sollSeite()`) |
| `Enums/Abteilung` | Küche/Hauswirtschaft/Medikation/Haustechnik/Pflege/Verwaltung → je ein Aufwandskonto |
| `Models/Konto` | Kontonummer/-name/-typ; `saldo()` rechnet Soll−Haben bzw. Haben−Soll je Kontoart |
| `Models/Buchung` | eine Buchung: Soll-Konto, Haben-Konto, Betrag, Datum, Text, optional Beleg |
| `Models/Artikel` | Lagerartikel je Abteilung, Bestand, Mindestbestand, EK-Preis; `unterbestand()` |
| `Models/Lagerbewegung` | Eingang/Verbrauch, mit der erzeugten Buchung verknüpft |
| `Actions/Buchen` | erzeugt eine Buchung (lehnt Soll==Haben und Betrag≤0 ab) |
| `Actions/Wareneingang` | erhöht Bestand **und** bucht Soll Warenbestand · Haben Verbindlichkeiten |
| `Actions/Warenverbrauch` | mindert Bestand **und** bucht Soll Abteilungs-Aufwand · Haben Warenbestand |
| `Support/AccountingDefaults` | idempotenter Standard-Kontenrahmen je Einrichtung (vereinfachter SKR) |

## Standard-Kontenrahmen (je Einrichtung)

| Nummer | Konto | Typ |
|---|---|---|
| 1000 | Kasse | Aktiv |
| 1200 | Bank | Aktiv |
| 1600 | Verbindlichkeiten aus L+L | Passiv |
| 3980 | Warenbestand | Aktiv |
| 5400–5490 | Wareneinsatz/Material je Abteilung | Aufwand |

Der Kontenrahmen wird per `AccountingDefaults::ensureFor($tenantId)` idempotent angelegt (kein Seeder,
keine Doppelung). Die Aufwandskonten ergeben sich aus `Abteilung::aufwandKonto()`.

## Verknüpfung Warenwirtschaft → Buchhaltung

```
Wareneingang  (Menge × EK-Preis):   Soll 3980 Warenbestand        an  Haben 1600 Verbindlichkeiten
Verbrauch     (Menge × EK-Preis):   Soll 54xx Abteilungs-Aufwand  an  Haben 3980 Warenbestand
```

So fließt jeder Einkauf und jeder Materialverbrauch der Abteilungen (Küche, Pflege, Haustechnik …)
ohne manuelle Nachbuchung in die Finanzbuchhaltung. Beide Aktionen laufen in einer DB-Transaktion;
die `Lagerbewegung` referenziert die erzeugte `Buchung` (`buchung_id`).

## UI & Zugriff

- Route `/buchhaltung` (Livewire `App\Livewire\Accounting\Buchhaltung`), Nav-Eintrag **Buchhaltung**.
- Sichtbar für Rolle `admin` oder `buchhaltung` (sonst HTTP 403) — mandantengetrennt über `BelongsToTenant`.
- Drei Bereiche: **Kontensalden** (gruppiert je Kontoart), **Lagerartikel** (mit Unterbestand-Warnung
  und den Formularen Wareneingang/Verbrauch/Artikel anlegen) sowie das **Journal** der letzten Buchungen.

## Tests

- `tests/Feature/Accounting/BuchhaltungTest.php` — Saldo je Kontoart, Mehrfachbuchungen, ungültige
  Buchungen (Soll==Haben, Betrag≤0), Mandantentrennung.
- `tests/Feature/Accounting/WarenwirtschaftTest.php` — Wareneingang/Verbrauch buchen korrekt, Unterbestand.
- `tests/Feature/Accounting/BuchhaltungUiTest.php` — Livewire-Buchungen über die UI + 403 ohne Rolle.
