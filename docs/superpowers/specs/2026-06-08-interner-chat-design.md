# Interner Chat (Team-Kommunikation) — Design

**Goal:** Schnelle interne Nachrichten im System, damit Mitarbeitende nicht auf WhatsApp ausweichen —
1:1, Gruppen, Stations-Kanäle und ein Ankündigungs-Kanal, tenant-isoliert, DSGVO-sauber.

## Scope-Entscheidungen (mit User abgestimmt)

- **4 Konversationsarten:** Direkt (1:1), Gruppe (ad-hoc), Station/Wohnbereich (offener Beitritt),
  Ankündigung (Leitung→alle, nur Lesen für die meisten).
- **Kein Bewohner-Bezug, nur Text** (v1). Bewohner-Bezogenes bleibt in SIS/Ereignis/Beschwerde; Fotos in den
  Bewohner-Medien. Verhindert Schatten-Doku + hält DSGVO sauber.
- **Kein E-Mail/Mailserver** (verworfen). Kein KIM hier.

## Realtime — ehrlich

Laravel **Reverb läuft serverseitig**, aber **Laravel Echo ist im Frontend NICHT verdrahtet** (die bestehende
`NotificationBell` nutzt `wire:poll.30s`, keinen Echo-Push). Daher liefert v1 neue Nachrichten **per
Livewire-Polling** aus (`wire:poll` auf der offenen Konversation + auf dem Header-Indikator) — konsistent mit dem
Bestand. Das Broadcast-Event `NachrichtGesendet` wird **trotzdem serverseitig gefeuert** (privater Kanal
`konversation.{id}`, Mitglieder-Auth), damit eine spätere Echo-Schicht ohne Umbau andockt. Kein halb-verdrahtetes
Echo, kein Overclaiming von „Echtzeit-Push".

## Architektur

Neue Domäne **`app/Domains/Communication`**. **Kein `LogsActivity`** auf Chat-Modellen (privat — Logging wäre
falsch): Modelle `extends Model` + `use BelongsToTenant` (Muster wie Voting/Energiebarometer). Drei Tabellen, ein
Enum, Services, ein Broadcast-Event.

### Enum (`app/Domains/Communication/Enums/`)

- **`KonversationTyp`** (string): `Direkt`, `Gruppe`, `Station`, `Ankuendigung`. `label()`.

### Modelle (`app/Domains/Communication/Models/`, je `extends Model` + `use BelongsToTenant`, KEIN LogsActivity)

- **`Konversation`**: `tenant_id`, `typ` (KonversationTyp), `titel` (nullable; bei Direkt null), `station_id`
  (nullable FK stations nullOnDelete, nur bei typ Station), `erstellt_von` (nullable FK users nullOnDelete).
  - `teilnehmer(): HasMany<KonversationTeilnehmer>`, `nachrichten(): HasMany<Nachricht>`, `station(): BelongsTo`.
  - `istMitglied(int $userId): bool`, `letzteNachricht(): ?Nachricht`.
  - `darfSchreiben(User $u): bool` — bei `Ankuendigung`: nur `admin`/`super-admin`; sonst: Mitglied UND
    `teilnehmer.darf_schreiben`.
- **`KonversationTeilnehmer`** (Mitgliedschaft): `tenant_id`, `konversation_id`, `user_id`,
  `zuletzt_gelesen_am` (timestamp nullable), `darf_schreiben` (bool default true). Unique(konversation_id,user_id).
  `user(): BelongsTo`.
- **`Nachricht`**: `tenant_id`, `konversation_id`, `user_id` (Absender), `inhalt` (text), `geloescht_am`
  (timestamp nullable — Zurückziehen), timestamps. `absender(): BelongsTo<User>`,
  `istZurueckgezogen(): bool` (`geloescht_am !== null`).

### Services (`app/Domains/Communication/Services/`)

- **`DirektnachrichtOeffnen::handle(User $ich, int $partnerUserId): Konversation`** — find-or-create der 1:1-
  Konversation (typ Direkt) deren Teilnehmer **exakt** {ich, partner} sind (Dedupe — A↔B existiert genau einmal).
  Partner muss selber Tenant + Mitarbeitende:r sein.
- **`GruppeErstellen::handle(User $ersteller, string $titel, array $userIds): Konversation`** — Ersteller +
  gewählte Mitglieder (alle tenant-geprüft), typ Gruppe.
- **`StationskanalBeitreten::handle(User $u, int $stationId): Konversation`** — find-or-create der Konversation
  (typ Station, station_id) und fügt `$u` als Teilnehmer hinzu (offener Beitritt). Titel = Stationsname.
- **`AnkuendigungskanalHolen::handle(int $tenantId): Konversation`** — find-or-create die EINE Ankündigungs-
  Konversation des Tenants; stellt sicher, dass alle aktiven Mitarbeitenden (Teilnehmer, `darf_schreiben=false`)
  Mitglied sind. (Schreibrecht regelt `darfSchreiben()` per Rolle, nicht das Flag.)
- **`NachrichtSenden::handle(Konversation $k, User $u, string $inhalt): Nachricht`** — prüft `k->darfSchreiben($u)`
  (sonst 403), erstellt `Nachricht` (inhalt required, max 2000), feuert `NachrichtGesendet`-Broadcast. Text only.
- **`NachrichtZurueckziehen::handle(Nachricht $n, User $u): void`** — nur eigene Nachricht, nur innerhalb **15 min**
  (sonst Ausnahme/Fehlermeldung), setzt `geloescht_am`.
- **`KonversationGelesen::handle(Konversation $k, User $u): void`** — `zuletzt_gelesen_am = now()` für den Teilnehmer.
- **`UngeleseneZaehler::fuer(User $u): int`** — Summe ungelesener Nachrichten über alle Konversationen des Users
  (Nachrichten mit `created_at > teilnehmer.zuletzt_gelesen_am`, **ohne eigene**, ohne zurückgezogene).

### Broadcast (`app/Domains/Communication/Events/NachrichtGesendet.php` + `routes/channels.php`)

- `NachrichtGesendet implements ShouldBroadcast` auf `PrivateChannel('konversation.{id}')`, Payload schlank
  (nachricht_id, konversation_id). Channel-Auth in `channels.php`: nur Mitglieder der Konversation desselben
  Tenants (Muster `transcription.{jobId}`).

## UI

- **`app/Livewire/Communication/Chat.php`** (Route `/chat`, Name `chat`): Zwei-Spalten-Screen. Links
  Konversationsliste (sortiert nach letzter Nachricht, Ungelesen-Badge je Konversation); „Neu"-Aktionen:
  Direktnachricht (Kollegin wählen), Gruppe (Mitglieder + Titel), Stations-Kanal beitreten, Ankündigung öffnen.
  Rechts: gewählter Thread + Eingabe (`NachrichtSenden`), eigene Nachricht zurückziehen, `wire:poll.10s` für neue
  Nachrichten; Auswahl markiert gelesen (`KonversationGelesen`). Bei Ankündigung ohne Schreibrecht ist die Eingabe
  ausgeblendet.
  **Gate:** authentifizierte:r Mitarbeitende:r des Tenants — **Portal-/Betreuer-Nutzer ausgeschlossen** (prüfe im
  Code, wie Portal-User markiert sind, z. B. fehlendes `employeeProfile`/eigene Rolle; schließe sie aus).
- **`app/Livewire/Communication/ChatGlocke.php`** (Header-Indikator, Muster `NotificationBell`): Ungelesen-Zähler
  (`UngeleseneZaehler`), `wire:poll.30s`, Klick → `/chat`. In `layouts/app.blade.php` neben `notification-bell`
  einbinden (`@auth` + Mitarbeiter-Check).
- **Nav:** Eintrag „Chat" (oder Header-Icon) — gut sichtbar, da Querschnitts-Funktion.

## Datenschutz/Compliance

- Tenant-isoliert (alle Modelle `BelongsToTenant`, Channel-Auth tenant-geprüft).
- Kein Bewohner-Bezug, nur Text. Kein Activity-Log (privat).
- Aufbewahrung: Nachrichten bleiben mit Absender; Austritt eines MA → Nachrichten bleiben (Konfiguration der
  Aufbewahrung/Löschung als spätere Iteration). Zurückziehen nur eigener Nachrichten, 15-min-Fenster.

## Pflicht-Lektionen (Review-Blocker)

- **Tenant/IDOR:** jede Aktion lädt Konversation/Nachricht tenant-scoped + Mitgliedschaft prüfen
  (`istMitglied`/`darfSchreiben`); kein blankes `find()`. Channel-Auth nur Mitglieder.
- **Direkt-Dedupe:** A↔B exakt eine Konversation (keine Duplikate bei wiederholtem Öffnen).
- **Ankündigung-Schreibgate:** nur admin/super-admin dürfen posten — in `darfSchreiben()` UND in `NachrichtSenden`.
- **Zurückziehen:** nur eigene Nachricht, nur ≤ 15 min; fremde/abgelaufene → 403/Fehler, kein stilles Schlucken.
- **Ungelesen-Zähler:** ohne eigene + ohne zurückgezogene Nachrichten; konsistent zwischen Liste, Thread, Glocke.
- Niemals Errors stummschalten.
- Vorlagen: `app/Livewire/NotificationBell.php` + Blade (Header-Indikator + wire:poll), `routes/channels.php`
  (`transcription.{jobId}` Tenant-Auth), `app/Domains/Voting/**` (Modelle ohne LogsActivity + BelongsToTenant),
  `app/Livewire/Facility/Trinkwasser.php` (Gate/Tenant-Muster).

## Tests (Pest, TDD)

- **Modelle/Typen:** KonversationTyp::label; `darfSchreiben` (Ankündigung nur admin; Gruppe Mitglied mit Recht;
  Nicht-Mitglied false).
- **DirektnachrichtOeffnen:** zweimaliges Öffnen A↔B → dieselbe Konversation (Dedupe); Partner anderer Tenant → Fehler.
- **GruppeErstellen / StationskanalBeitreten:** Mitglieder gesetzt; Stations-Kanal find-or-create + Beitritt; nur
  Tenant-User.
- **AnkuendigungskanalHolen:** genau eine je Tenant; alle aktiven MA Mitglied; darf_schreiben-Gate per Rolle.
- **NachrichtSenden:** Mitglied sendet → Nachricht + Broadcast (Event::fake assertDispatched); Nicht-Mitglied/kein
  Schreibrecht (Ankündigung als Nicht-Admin) → 403; Text required/max.
- **NachrichtZurueckziehen:** eigene ≤15 min ok; fremde → Fehler; >15 min → Fehler.
- **UngeleseneZaehler / KonversationGelesen:** zählt fremde ungelesene, nicht eigene, nicht zurückgezogene; nach
  „gelesen" 0.
- **Channel-Auth:** Mitglied true, Nicht-Mitglied/fremder Tenant false.
- **UI:** Konversation öffnen markiert gelesen; senden erscheint im Thread; zurückziehen blendet aus; DM-Picker
  legt Konversation an; **Gate**: Portal-/Betreuer-User → 403; Nicht-Mitglied sieht fremde Konversation nicht (IDOR).
- **Volle Suite** grün, **Larastan L5** clean, **Pint** clean.

## Risiken & Trade-offs

- **Polling statt Echo-Push:** bewusst (Echo nicht verdrahtet); ausreichend für Team-Koordination. Echo-Layer
  später ohne Datenmodell-Umbau nachrüstbar (Event existiert).
- **Stations-Kanal offener Beitritt:** mangels Mitarbeiter-Station-Zuordnung kein Auto-Membership; jede:r kann
  beitreten — ehrlich, kein erfundenes Zuordnungsmodell.
- **Aufbewahrung/Löschung** der Nachrichten: v1 simpel (bleiben); DSGVO-Löschkonzept als Folge-Iteration.
