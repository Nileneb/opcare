# Interner Chat (Team-Kommunikation)

Schnelle interne Nachrichten im System, damit Mitarbeitende nicht auf WhatsApp ausweichen. Eigene Domäne
`app/Domains/Communication`, Route `/chat`, Header-Glocke mit Ungelesen-Zähler.

## Scope

- **4 Konversationsarten:** Direkt (1:1), Gruppe (ad-hoc), Station/Wohnbereich (offener Beitritt),
  Ankündigung (Leitung → alle; nur `admin`/`super-admin` dürfen posten).
- **Kein Bewohner-Bezug, nur Text.** Bewohner-Bezogenes bleibt in SIS/Ereignis/Beschwerde, Fotos in den
  Bewohner-Medien. Verhindert Schatten-Doku + hält DSGVO sauber.
- **Tenant-isoliert**, **kein Activity-Log** (Chat ist privat), Portal-/Betreuer-Nutzer ausgeschlossen.

## Realtime (Reverb/Echo, End-to-End verifiziert)

Neue Nachrichten kommen **in Echtzeit per WebSocket** an: Laravel **Reverb** (Server) + **Echo** (Frontend,
`resources/js/echo.js`, via `@vite`). Das Broadcast-Event `NachrichtGesendet` (privater Kanal `konversation.{id}`,
Mitglieder-Auth in `routes/channels.php`) wird über Horizon an Reverb gepusht; Thread, Seitenleiste und Glocke
abonnieren **alle** Kanäle der Person (Livewire bindet Echo-Kanäle nur beim Init) und refreshen sofort. Per
Browser-Test bewiesen — eine serverseitig gesendete Nachricht erscheint ohne Reload live im Thread.

`broadcastAs('NachrichtGesendet')` + Listener mit **führendem Punkt** (`.NachrichtGesendet`), sonst prefixt Echo
einen Namespace und matcht nie. Ein langsamer `wire:poll` (Thread 30 s, Glocke 60 s) bleibt nur als
WS-Ausfall-Fallback. Docker: Browser → `REVERB_HOST`, Server → `REVERB_INTERNAL_HOST` (Servicename `reverb`),
`VITE_REVERB_*` als Build-Args ins Bundle, CSP `connect-src` erlaubt den WS-Origin.

## Modell

- `Konversation` (`typ`, `titel`, `station_id`, `erstellt_von`) — `darfSchreiben(User)` (Ankündigung nur admin),
  `istMitglied()`, `letzteNachricht()`.
- `KonversationTeilnehmer` (Mitgliedschaft + `zuletzt_gelesen_am` + `darf_schreiben`).
- `Nachricht` (`inhalt`, `geloescht_am` für Zurückziehen ≤ 15 min).

## Services

`DirektnachrichtOeffnen` (Dedupe A↔B), `GruppeErstellen`, `StationskanalBeitreten` (find-or-create + Beitritt),
`AnkuendigungskanalHolen` (eine je Tenant, alle Staff als Leser), `NachrichtSenden` (Schreibrecht + Broadcast),
`NachrichtZurueckziehen` (eigene, ≤ 15 min), `KonversationGelesen`, `UngeleseneZaehler`.

## UI

- **`/chat`** Zwei-Spalten-Screen: links Konversationsliste mit Ungelesen-Badges + „Neu" (Direkt/Gruppe/Station/
  Ankündigung), rechts Thread + Eingabe (eigene Nachrichten hervorgehoben, zurückgezogene als Platzhalter).
- **Header-Glocke** (`ChatGlocke`) mit Ungelesen-Zähler, Klick → `/chat`.
- Gate: Staff-Rollen (Portal-User ausgeschlossen).

## Aufbewahrung / spätere Stufen

Nachrichten bleiben mit Absender; ein DSGVO-Löschkonzept (Austritt) ist bewusst eine spätere
Iteration. Kein E-Mail/Mailserver (verworfen).

## Spec

`docs/superpowers/specs/2026-06-08-interner-chat-design.md`
