# Pflegemaßnahmen-Katalog

`pflege_massnahmen.csv` — 229 standardisierte Pflegemaßnahmen (eine Bezeichnung je Zeile),
als Vorschlagskatalog für die SIS-Maßnahmenplanung (`CareMeasure`).

## Quelle

Übernommen aus dem Interventions-Katalog des OPDE-Projekts (Offene-Pflege.de,
`dbscripts/content-base-21.sql`, Tabelle `intervention`) — bereinigt (nur Bezeichnung,
dedupliziert). Deckt Grundpflege, Prophylaxen, Behandlungspflege und Aktivierung ab.

## Import

```bash
php artisan measures:import            # gebündelter Katalog
php artisan measures:import eigene.txt # eigene Liste (eine Bezeichnung je Zeile)
```

Idempotent (Upsert je Bezeichnung). Der Katalog ist tenant-übergreifend; die Maßnahmen-UI
nutzt ihn als Such-Picker, Freitext bleibt weiter möglich.
