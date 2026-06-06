# Dokumente & Fotos (Datei-Upload + Freigabe)

Bewohner-Dokumente und -Fotos hochladen, kategorisieren und **bei Bedarf protokolliert freigeben** —
Recherche-Strang A. Storage-Backend ist konfigurierbar: lokal (Default) oder **MinIO** (self-hosted, S3-kompatibel).

## Storage (lokal ↔ MinIO)

Die Dateien liegen in der spatie-`documents`-Collection des Bewohners auf einer **konfigurierbaren Disk**
(`config/opcare.php` → `media_disk`, env `OPCARE_MEDIA_DISK`):
- **`media`** (Default): lokales Dateisystem, privat — funktioniert ohne Zusatzdienst, in Dev/Tests/CI.
- **`minio`**: S3-kompatibler Objektspeicher (Docker-Service `minio` + `minio-init` im Compose-Stack,
  Bucket privat). Aktivierung allein über `OPCARE_MEDIA_DISK=minio` + MinIO-Zugangsdaten — kein Code-Eingriff.

Kein Cloud-Zwang: MinIO läuft self-hosted im selben Stack. AVV entfällt (eigener Server).

## Rechtliche Leitplanken (umgesetzt)

- **Kategorie** steuert Pflichten (`DokumentKategorie`): Wundfoto/Befund = medizinisch → **10-Jahres-Aufbewahrung**
  (`retention_until`, § 630f Abs. 3 BGB; bei Haftung faktisch 30 J.). Profilfoto → **ausdrückliche Einwilligung**
  Pflicht (§ 22 KUG + Art. 9 lit. a DSGVO), Einwilligungsgeber wird festgehalten.
- **Freigabe „bei Bedarf"** über eine **signierte, ablaufende Route** (kein öffentlicher Link) — jede Freigabe
  wird in `media_shares` protokolliert (Empfängertyp Arzt/Angehörige/Behörde/intern, Empfänger, Ablauf,
  Zugriffszeitpunkt) → DSGVO-Auditpflicht erfüllt.
- **Tenant-Scope/IDOR**: Download erzwingt, dass das Dokument zu einem Bewohner der eigenen Einrichtung gehört.
- Reine Datei-Archivierung ohne Auto-Auswertung ist **kein Medizinprodukt** (MDR) — Zweckbestimmung bewusst so.

> Screenshot der Oberfläche: siehe Wiki-Seite **Dokumente Dateien**.

## Architektur

| Baustein | Aufgabe |
|---|---|
| `Resident` (HasMedia) | `documents`-Collection auf konfigurierbarer Disk (spatie media-library) |
| `Enums/DokumentKategorie` | Wundfoto/Befund/Vertrag/Profilfoto/Sonstiges + `istMedizinisch()`/`brauchtEinwilligung()` |
| `Services/AttachmentService` | Upload (Kategorie/Frist/Einwilligung in custom_properties) + Freigabe-Link + Löschen |
| `Models/MediaShare` | Freigabe-Protokoll (tenant-scoped) |
| `Http/Controllers/MediaDownloadController` | signierter, tenant-geprüfter, protokollierter Download (disk-agnostisch) |
| `Livewire/Masterdata/ResidentMedia` | UI-Panel, eingebettet auf der Bewohner-Detailseite (Abschnitt „Dokumente") |

Operativer Einstiegspunkt: Bewohner-Detailseite → Abschnitt **Dokumente & Fotos** (Rollen `admin`/`pflegefachkraft`
dürfen hochladen/freigeben/löschen, `leserecht` nur ansehen). Tests: `tests/Feature/Masterdata/ResidentMediaTest.php`.
