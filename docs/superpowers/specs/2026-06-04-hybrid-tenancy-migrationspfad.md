# Hybrid-Tenancy — Migrationspfad zu DB-per-Tenant

**Datum:** 2026-06-04 · **Status:** Entscheidung getroffen (row-level jetzt, DB-per-Tenant später)

## Heute (implementiert, Plan 1 + 4)

Row-level Mehrmandantenfähigkeit in **einer** Datenbank:

- Jede Domänen-Tabelle hat `tenant_id`; `App\Support\Models\BaseModel` (`BelongsToTenant`) setzt ihn beim Anlegen aus `CurrentTenant` und erzwingt einen globalen `TenantScope` bei jeder Query.
- Rollen sind je Mandant isoliert über **spatie-Teams** (`team_foreign_key = tenant_id`); `CurrentTenant::set()` synchronisiert den `PermissionRegistrar`-Team-Kontext.
- `super-admin` ist tenant-übergreifend (`User::isSuperAdmin()` per Roh-Query ohne Team-Scope; `Gate::before`-Bypass).
- **Einzige Naht für „welcher Mandant gilt":** `App\Domains\Identity\Support\TenantResolver::resolveFor()` (aus eingeloggtem User bzw. Super-Admin-Session-Switch). Die Middleware `SetCurrentTenant` ist der einzige Aufrufer im Web-Pfad; Queue-Jobs setzen `CurrentTenant` explizit.

Cross-Tenant-Isolation ist durch `tests/Feature/Tenancy/CrossTenantIsolationTest.php` + `tests/Arch/TenancyTest.php` abgesichert.

## Auslöser für DB-per-Tenant (später)

Ein Wechsel lohnt erst bei einem dieser Trigger:
- Vertragliche/aufsichtsrechtliche Forderung nach **physischer** Datentrennung eines Trägers (DSGVO-Auftragsverarbeitung pro Heim).
- Sehr viele Heime / Datenvolumen, bei dem row-level-Scoping zum Performance- oder Backup-Granularitäts-Problem wird.
- Mandantenindividuelle Backups/Restores/Löschfristen, die in einer geteilten DB zu aufwändig werden.

## Migrationspfad

1. **Nur `TenantResolver` austauschen/erweitern** — er bestimmt zusätzlich die DB-Connection (z. B. via `stancl/tenancy` oder eigener `config(['database.connections.tenant...'])` + `DB::setDefaultConnection('tenant')`). Der Rest der App spricht weiter `CurrentTenant`.
2. **Landlord-DB** behält zentrale Tabellen: `tenants`, `users`, spatie-Rollen/Permissions (oder pro-Tenant gespiegelt — Entscheidung beim Cutover).
3. **Je-Heim-DB** für die Domänen-Tabellen (Masterdata/CarePlanning/Medication/Quality/Qdvs). Dort wird der `TenantScope` überflüssig (DB ist bereits isoliert) — `BelongsToTenant` kann je nach Strategie deaktiviert oder beibehalten werden (defensive Doppelsicherung).
4. **Migrationen** laufen pro Tenant-DB (`tenancy:migrate` o. Ä.).

## Risiko / Konsequenz

- **Heimübergreifende Auswertungen** (Controlling/QMS-Benchmarks über mehrere Heime, zentrale Super-Admin-Sicht) brauchen dann einen Aggregations-Layer: Landlord-Read-Replica, ETL in ein Data-Warehouse, oder pro-Tenant-Abfrage + Merge im Code. Im aktuellen row-level-Modell sind solche Auswertungen trivial (ein `GROUP BY tenant_id`).
- QDVS-Export (Plan 7) ist ohnehin pro Einrichtung/Stichtag — unkritisch.

**Fazit:** Die Entscheidung ist bewusst vertagt. Solange ein Deployment je Träger/Region genügt, bleibt row-level die einfachere, günstigere Wahl. Die `TenantResolver`-Naht hält den Wechsel später lokal begrenzt.
