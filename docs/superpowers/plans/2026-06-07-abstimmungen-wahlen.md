# Abstimmungen & Wahlen — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. TDD je Task, volle Suite als Gate. **Bau zurückgestellt** (erst Vision-MCP → XLSX) — dieser Plan hält die Umsetzung fest.

**Goal:** Geheime (anonyme) + namentliche Abstimmungen/Wahlen für Bewohner/Mitarbeitende/Gremien; Einzelstimme bei geheim für niemanden außer der abstimmenden Person nachvollziehbar.

**Architecture:** Neuer Context `app/Domains/Voting`. Drei entkoppelte Modelle (Abstimmung/Option · Wahlteilnahme personenbezogen · Stimme anonym mit UUID-PK ohne Timestamps). Energiebarometer-Anonymitätsdisziplin (`BelongsToTenant`, kein LogsActivity). Geheim erzwungen bei gesetzlichen Wahlen; Online-Wahl bindend hinter Inbetriebnahme-Schalter.

**Tech Stack:** Laravel 13, Livewire 4, Pest, Larastan L5, Pint.

**Spec:** `docs/superpowers/specs/2026-06-07-abstimmungen-wahlen-design.md`.

**Konventionen:** BaseModel=BelongsToTenant+LogsActivity; `Stimme`/`Wahlteilnahme` nur `BelongsToTenant`. tenant-scoped exists via `tenantExists`. Kein stilles Schlucken. ide-helper positional. Gates phpstan/pint/pest. Branch `feat/abstimmungen-wahlen`.

---

## Task 1: Modelle + Migrations + Enums

**Files:**
- Enums `app/Domains/Voting/Enums/`: `Elektorat` (Bewohner/Mitarbeitende/Gremium), `Stimmodus` (Geheim/Namentlich — Naht für späteren `GeheimKrypto`), `Abstimmungsart` (Umfrage/Wahl/Beschluss), `AbstimmungStatus` (Entwurf/Offen/Geschlossen), je `label()`.
- Migrations: `abstimmungen`, `abstimmung_optionen`, `wahlteilnahmen`, `stimmen` (+ ggf. `stimme_option`-Pivot bei Mehrfachauswahl).
- Models `app/Domains/Voting/Models/`: `Abstimmung` (BaseModel), `AbstimmungOption` (BaseModel), `Wahlteilnahme` (nur BelongsToTenant), `Stimme` (nur BelongsToTenant, `$incrementing=false`, `$keyType='string'`, **`public $timestamps = false`**, UUID via `creating`-Hook).

**Schemas:**
- `abstimmungen`: tenant_id, titel, beschreibung nullable, `elektorat`, `gremium_id` nullable FK(gremien) nullOnDelete, `modus`, `art`, `mehrfachauswahl` bool default false, `start_am`/`ende_am` datetime nullable, `status` default 'entwurf', `ergebnis_sichtbar` bool default false, `erstellt_von` nullable FK(users), timestamps.
- `abstimmung_optionen`: tenant_id, abstimmung_id FK cascade, text, sortierung int default 0, timestamps.
- `wahlteilnahmen`: tenant_id, abstimmung_id FK cascade, user_id nullable FK(users) nullOnDelete, resident_id nullable FK(residents) nullOnDelete, `hat_abgestimmt` bool default false, timestamps. **Unique(abstimmung_id,user_id), Unique(abstimmung_id,resident_id)** (one-person-one-vote). KEINE weiteren Metadaten.
- `stimmen`: `id` **uuid primary**, tenant_id, abstimmung_id FK cascade, option_id nullable FK(abstimmung_optionen) nullOnDelete, `beleg_token` string unique, `waehler_user_id`/`waehler_resident_id` nullable FK nullOnDelete (**nur bei Namentlich befüllt**). **KEINE `timestamps()`** (kein created_at/updated_at — Anonymität).

**Tests:** Schema-Test: `stimmen` hat kein `created_at` (`Schema::hasColumn('stimmen','created_at')` === false), PK ist string/uuid. Modelle CRUD + Casts + Relationen; `Wahlteilnahme` Unique greift (Doppeleintrag wirft).

**Commit:** `feat(voting): Abstimmungs-Modelle (Stimme UUID/timestamp-frei für echte Anonymität)`.

---

## Task 2: Services (Anlegen, Stimmabgabe, Auszählung)

**Files:** `app/Domains/Voting/Services/{AbstimmungStarten,StimmeAbgeben,Auszaehlung}.php`, `config/voting.php` (`'online_wahl_aktiv' => env('VOTING_ONLINE_WAHL', false)` — Inbetriebnahme-Schalter), Tests.

**Contract:**
- `AbstimmungStarten::handle(array $daten, array $optionen, ?int $userId): Abstimmung`: legt Abstimmung + Optionen an; **erzwingt `modus=Geheim`** wenn `art=Wahl` UND `elektorat ∈ {Bewohner, Mitarbeitende}` (Namentlich → InvalidArgumentException). Beim Status→Offen: `Wahlteilnahme`-Zeilen für alle Berechtigten des Elektorats anlegen (Bewohner: aktive Residents; Mitarbeitende: User mit passender Rolle; Gremium: GremiumMitglieder).
- `StimmeAbgeben::handle(Abstimmung $a, int|string $waehlerId, string $waehlerTyp, array $optionIds): string` (gibt `beleg_token` zurück) in DB::transaction:
  - guard: Abstimmung Offen + in Frist; Wahlteilnahme existiert + `hat_abgestimmt=false` (sonst Exception — kein Doppel).
  - **bindende Online-Wahl-Sperre:** wenn `art=Wahl` und `!config('voting.online_wahl_aktiv')` → InvalidArgumentException („Online-Wahl nicht freigegeben").
  - (a) `Wahlteilnahme.hat_abgestimmt = true` (nur Boolean). (b) `Stimme` (UUID, beleg_token = `bin2hex(random_bytes(16))`, option(s); `waehler_*` NUR wenn `modus=Namentlich`). **Kein** Timestamp.
  - return beleg_token (einmalig dem Aufrufer/UI).
- `Auszaehlung::ergebnis(Abstimmung $a): array`: je Option Stimmenzahl; bei Namentlich zusätzlich Namensliste; Wahlbeteiligung aus Wahlteilnahme.

**Tests:** Geheim → Stimme ohne `waehler_*`, Wahlteilnahme markiert, Doppelabgabe wirft. Namentlich-Umfrage → Stimme trägt Person. Heimbeirat-Wahl Namentlich → AbstimmungStarten wirft. Online-Wahl gesperrt wenn Schalter aus. Auszählung korrekt. Token unique. **Anonymität:** keine Query Person→Stimme möglich (es gibt keine Relation bei Geheim).

**Commit:** `feat(voting): Abstimmungs-Services (Geheim-Erzwingung, Beleg-Token, Auszählung, Online-Wahl-Schalter)`.

---

## Task 3: Livewire-UI + Inbetriebnahme-Schalter

**Files:** `app/Livewire/Voting/Abstimmungen.php` + View, `routes/web.php` (Route `/abstimmungen`), `layouts/app.blade.php` (Nav), `docs/INBETRIEBNAHME.md` (Schalter-Eintrag `voting.online_wahl_aktiv`), Test.

**Contract:** Gate: Anlegen = admin/leitung; Abstimmen = Person des Elektorats (Bewohner-Portal/Mitarbeiter/Gremiumsmitglied). Liste offener/geschlossener Abstimmungen; Anlegen (Optionen, Elektorat, Modus, Art, Frist); Stimmabgabe (identische Stimmzettel-UI, Optionen, danach **einmalige Beleg-Anzeige**); Ergebnis (Auszählung + Token-/Namensliste, rollen-gegated). Prominenter Hinweis-Kasten je Abstimmung: Anonymitäts-Decke (kein Berechtigter/normaler Admin, Root-Admin nur mit Krypto-Pfad) + DSG-EKD/Geheimwahl-Kontext. Bindende Online-Wahl nur sichtbar/aktiv wenn `config('voting.online_wahl_aktiv')`.

**Tests:** Gate 403; Anlegen + Abstimmen + Ergebnis (Rolle); geheime Abgabe zeigt Beleg; tenant-scoped; Online-Wahl-Hinweis wenn Schalter aus.

**Commit:** `feat(voting): Abstimmungen-UI + Inbetriebnahme-Schalter (bindende Online-Wahl stillgelegt)`.

---

## Abschluss (nach Task 3)
- DemoSeeder: eine offene Umfrage (Ausflug) + eine geschlossene geheime Abstimmung mit Stimmen. `migrate:fresh --seed`.
- Löschfrist-Command/Hinweis (Wählerliste + Stimmen nach Amtszeit+Anfechtungsfrist → nur anonymes Protokoll).
- Screenshot, README-Zähler, `docs/abstimmungen.md`, `docs/INBETRIEBNAHME.md`, Wiki, Memory.
- Opus-Final-Review (Anonymität: kein Personenbezug/Timestamp/Sequenz an der Stimme; Geheim-Erzwingung; Doppelabgabe-Schutz; Online-Wahl-Schalter; tenant-scope), Fixes, `--no-ff` Merge + Push.
