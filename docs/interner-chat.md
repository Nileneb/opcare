# Interner Chat (Team-Kommunikation)

Schnelle interne Nachrichten im System, damit Mitarbeitende nicht auf WhatsApp ausweichen. Eigene Domäne
`app/Domains/Communication`, Route `/chat`, Header-Glocke mit Ungelesen-Zähler.

## Scope

- **4 Konversationsarten:** Direkt (1:1), Gruppe (ad-hoc), Station/Wohnbereich (offener Beitritt),
  Ankündigung (Leitung → alle; nur `admin`/`super-admin` dürfen posten).
- **Kein Bewohner-Bezug, nur Text.** Bewohner-Bezogenes bleibt in SIS/Ereignis/Beschwerde, Fotos in den
  Bewohner-Medien. Verhindert Schatten-Doku + hält DSGVO sauber.
- **Tenant-isoliert**, **kein Activity-Log** (Chat ist privat), Portal-/Betreuer-Nutzer ausgeschlossen.

## Realtime (ehrlich)

Reverb läuft serverseitig, aber **Laravel Echo ist im Frontend nicht verdrahtet** (auch die NotificationBell
pollt). Daher liefert v1 neue Nachrichten **per Livewire-Polling** (`wire:poll.10s` Thread, `.30s` Glocke). Das
Broadcast-Event `NachrichtGesendet` (privater Kanal `konversation.{id}`, Mitglieder-Auth) wird trotzdem gefeuert,
damit eine spätere Echo-Schicht ohne Datenmodell-Umbau andockt.

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

Nachrichten bleiben mit Absender; DSGVO-Löschkonzept (Austritt) + echter Echo-Push sind bewusst spätere
Iterationen. Kein E-Mail/Mailserver (verworfen).

## Spec

`docs/superpowers/specs/2026-06-08-interner-chat-design.md`
