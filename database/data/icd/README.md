# ICD-10-GM Katalog

`icd10gm_2017_kodes.csv` — der amtliche deutsche ICD-10-Katalog (German Modification),
Format `code;bezeichnung`, 15.930 Codes (Schlüsselnummern + Klassentitel der Systematik).

## Quelle & Lizenz

- **Herausgeber:** BfArM (im Auftrag des BMG), vormals DIMDI.
- **Version:** ICD-10-GM **2017** (Systematik, Datei `icd10gm2017syst_kodes.txt`,
  Spalte 7 = Schlüsselnummer, Spalte 9 = Klassentitel).
- **Lizenz:** Die ICD-10-GM ist **gemeinfrei** (öffentlich, BfArM) — Einbettung und
  Weitergabe zulässig.
- **Bezug der Rohdaten:** offizieller DIMDI-Systematik-Mirror
  (`edonnachie/ICD10gm`, `data-raw/dimdi/systematik/x1gmt2017.zip`).

## Aktualisieren auf eine neuere Jahresversion

Die aktuelle ICD-10-GM kostenlos beim BfArM laden (Klassifikationsdateien,
`icd10gm<JAHR>syst_kodes.txt`) und importieren — der Importer erkennt das
amtliche 30-Spalten-Format automatisch:

```bash
php artisan icd:import /pfad/zu/icd10gm2026syst_kodes.txt
```

Ohne Argument wird die hier gebündelte 2017er-Baseline geladen
(`php artisan icd:import`). Der Import ist idempotent (Upsert je Code).
