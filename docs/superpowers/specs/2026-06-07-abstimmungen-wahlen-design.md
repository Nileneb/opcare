# Abstimmungen & Wahlen (anonym + namentlich) — Design

**Datum:** 2026-06-07
**Status:** Design — vom User approved (2026-06-07). **Bau zurückgestellt** (erst Vision-MCP → XLSX); diese Spec
hält die Entscheidungen fest.

## Ziel

Ein Modul für **Abstimmungen und Wahlen** in der App: geheime (anonyme) und — je Abstimmung wählbar — namentliche
Stimmabgabe, für Bewohner, Mitarbeitende und Gremien. Von der niedrigschwelligen Umfrage („wohin der Ausflug?") bis
zur Heimbeirat-Wahl. Ergebnis öffentlich (nur für Berechtigte), Einzelstimme bei geheim **für niemanden außer der
abstimmenden Person selbst** nachvollziehbar.

## Entscheidungen (User, 2026-06-07)

- **Anonymitäts-Niveau: gestuft.** Standard = **pragmatische Unverkettbarkeit** (Trennung + Zugriffskontrolle +
  Timing-Entkopplung). Ein **Krypto-Härtungspfad** (blind-signierter Token) ist als *späterer* optionaler Modus
  vorgemerkt — jetzt NICHT gebaut.
- **Elektorat: allgemein** — je Abstimmung wählbar: Bewohner / Mitarbeitende / ein Gremium.
- **Modus je Abstimmung:** geheim oder namentlich.

## Ehrliche Decke (im Modul dokumentiert, nicht überdeklarieren)

- „Niemand außer dem Einzelnen" ist als **Design- + Zugriffsversprechen** erreichbar (kein Personen-FK an der
  geheimen Stimme, getrennte Listen, Timing-Entkopplung, Rollen-Gate). Ein **Root-Admin** mit Code-/Log-Zugriff ist
  nur mit echter E-Voting-Krypto (Härtungspfad) vollständig auszuschließen — das wird offen so benannt.
- Der **Beleg-Token ist bewusst NICHT vote-beweisend** (Receipt-Freeness-Abschwächung): er bestätigt „deine Stimme
  ist gezählt", soll aber nicht als Nötigungs-/Kaufbeleg „so habe ich gestimmt" taugen. (Voll receipt-frei nur mit
  Krypto-Pfad.)

## Architektur

Neuer Bounded Context `app/Domains/Voting`. Nutzt `Gremium` (Quality) für das Gremium-Elektorat und die
**Anonymitäts-Disziplin des Energiebarometers** (`Energielevel`: nur `BelongsToTenant`, **kein** `LogsActivity`) für
die Stimme. Tenant-scoped. Livewire-UI.

### Trennung der Belange (Kern der Anonymität)

Drei Modelle, bewusst entkoppelt:
- **`Abstimmung`** (BaseModel — Metadaten dürfen geloggt werden): `titel`, `beschreibung`, `elektorat`
  (Enum `Elektorat`: Bewohner/Mitarbeitende/Gremium), `gremium_id` (nullable FK, wenn Gremium), `modus`
  (Enum `Stimmodus`: Geheim/Namentlich), `art` (Enum `Abstimmungsart`: Umfrage/Wahl/Beschluss — niedrigschwellig
  vs. bindend), `mehrfachauswahl` (bool), `start_am`, `ende_am`, `status` (Enum: Entwurf/Offen/Geschlossen),
  `ergebnis_sichtbar` (bool), `erstellt_von`.
- **`AbstimmungOption`** (BaseModel): `abstimmung_id`, `text`, `sortierung`.
- **`Wahlteilnahme`** (nur `BelongsToTenant`) — die **personenbezogene Wählerliste**: `abstimmung_id`,
  `user_id` ODER `resident_id` (je Elektorat, nullable), `hat_abgestimmt` (bool, default false). Trägt **nur das
  Häkchen** (Boolean, keine Uhrzeit/Kanal/Gerät — Research: Datensparsamkeit Art. 5(1)(c)), NIE den Stimminhalt.
  Unique (`abstimmung_id`,`user_id`/`resident_id`) = one-person-one-vote. Verhindert Doppelabstimmung, ermöglicht
  Wahlbeteiligung. Löschung gemäß Frist unten.
- **`Stimme`** (nur `BelongsToTenant`, **kein LogsActivity**) — die **anonyme/namentliche Stimme**:
  **`id` = random UUID (KEIN Auto-Increment)**, `abstimmung_id`, `option_id` (bzw. Mehrfach: Pivot
  `stimme_option`), `beleg_token` (random 128-bit hex, unlinkbar), **`waehler_user_id`/`waehler_resident_id` NUR bei
  `modus=Namentlich`** (bei Geheim **kein** Personen-FG/Feld). Append-only.
  - **KRITISCH (Research bestätigt — echte Anonymität ⇒ kein Personenbezug, ErwG 26):** die geheime `Stimme` trägt
    **KEINE `timestamps()`** (kein `created_at`), **kein Datum** und **keine Sequenznummer** — der UUID-PK verhindert,
    dass die Einfüge-Reihenfolge die n-te Teilnahme mit der n-ten Stimme verkettet. Bezug zum Wahlzeitpunkt entsteht
    nur indirekt über `abstimmung_id` (die Abstimmung selbst hat Fristen). Eine Sequenz/ein Timestamp an der Stimme
    würde den Personenbezug wiederherstellen → dann wäre es NICHT anonym und DSGVO/DSG-EKD voll anwendbar.

### Stimmabgabe-Flow (geheim)

1. Berechtigung: Elektorat trifft auf die Person zu UND eine `Wahlteilnahme` existiert mit `hat_abgestimmt=false`.
   (Wahlteilnahme-Zeilen werden beim Öffnen der Abstimmung für alle Berechtigten angelegt.)
2. Person wählt Option(en), bestätigt.
3. Server in **entkoppelten Schritten** (Timing-/Reihenfolge-Korrelation vermeiden):
   (a) `Wahlteilnahme.hat_abgestimmt = true` (personenbezogen, **nur Boolean**, keine Uhrzeit/Kanal/Gerät —
   Datensparsamkeit, Research bestätigt);
   (b) `Stimme` mit UUID-PK + Option + `beleg_token` (random), **kein** Personen-FG, **keine timestamps/Datum/Sequenz**.
   Die Wahlteilnahme-Markierung und die Stimme dürfen NICHT so geschrieben werden, dass „n-te Teilnahme = n-te
   Stimme" rekonstruierbar ist (UUID-PK + kein Stimmen-Timestamp erledigt das).
4. Der `beleg_token` wird der Person **einmalig** angezeigt („Beleg … — damit Sie Ihre Stimme in der Ergebnisliste
   wiederfinden"). Er wird **nie** gegen die Identität gespeichert.
5. Ergebnisliste (für Berechtigte): Stimmen mit Token + Auszählung. Bei `Namentlich`: mit Name statt Token.

### Krypto-Härtungspfad (gestuft — NICHT jetzt bauen, nur Naht)

`Stimme.beleg_token` als blind-signiertes Credential: Server stellt dem Berechtigten ein blind unterschriebenes Token
aus (kennt den Klartext nicht), die Stimme trägt das entblendete Token. Damit kann **auch der Server** Teilnahme und
Stimme nicht verketten. Das `Stimmodus`-Enum bekommt später einen Fall `GeheimKrypto`; die Modell-Trennung oben
bleibt unverändert tragfähig.

## DSGVO / Rechtslage (Research-verifiziert)

- **Geheime + unmittelbare Wahl ist bei Heimbeirat-/Bewohnerbeirat-Wahlen Pflicht:** **§ 5 Abs. 1 HeimmwV** (Bund,
  subsidiär: „gleicher, geheimer und unmittelbarer Wahl"); Landesrecht z. B. **Bayern AVPfleWoqG § 27**, **NRW WTG
  § 22 + Durchführungsverordnung**. Größe nach § 4 HeimmwV (3/5/7/9 je Bewohnerzahl), Amtszeit ~2 Jahre (§ 8), kein
  Gültigkeits-Quorum, wahlberechtigt alle Bewohner am Wahltag, Leitung/Träger/Behörde nicht wählbar.
  → Bei `art=Wahl` + Elektorat Bewohner wird **`modus=Geheim` erzwungen** (Namentlich nicht wählbar).
- **Mitarbeitervertretung (Diakonie) = MVG-EKD, NICHT BetrVG** (§ 118 Abs. 2 BetrVG schließt kirchliche Träger aus).
  **§ 11 Abs. 1 MVG-EKD:** „gleicher, freier, geheimer und unmittelbarer Wahl". WahlO-MVG: **identische Stimmzettel**
  (§ 7 Abs. 3 → bei digital: gleiche UI-Vorlage, kein personalisierter Stimmzettel), **unbeobachtete Kennzeichnung**
  (§ 8 Abs. 5 Nr. 1 → Einzelsession, keine Admin-Echtzeitsicht), **Wahlanfechtung § 14** (Frist ~2 Wochen).
  → Bei `art=Wahl` + Elektorat Mitarbeitende ebenfalls `modus=Geheim` erzwungen.
- **Anwendbares Datenschutzrecht:** für den kirchlich-diakonischen Träger gilt **DSG-EKD** (kirchliches
  Datenschutzrecht auf DSGVO-Niveau), nicht die DSGVO unmittelbar — Doku/Hinweise entsprechend formulieren.
- **Anonyme Stimmen** sind **keine** personenbezogenen Daten (ErwG 26) — **aber nur bei echter Anonymität**: kein
  Zeitstempel, keine Sequenznummer, keine Session-ID/IP an der Stimme (s. UUID-PK/keine-timestamps oben).
  Pseudonymisierung (Art. 4(5)) genügt NICHT — pseudonyme Daten blieben personenbezogen.
- **`Wahlteilnahme` ist personenbezogen** → Rechtsgrundlage **Art. 6(1)(c) DSGVO / DSG-EKD analog** (gesetzliche
  Wahlpflicht), Zweckbindung (nur Wahldurchführung), **nur Boolean „hat abgestimmt"** (keine weiteren Metadaten).
  **Löschfrist: Ende Amtszeit + Anfechtungsfrist (empfohlen + 1 Monat)** → danach Wählerliste + Stimmen löschen, nur
  **anonymes Ergebnisprotokoll** behalten. (Artisan-Command/Hinweis im Modul.)
- **Namentlich** → nur wo NICHT gesetzlich geheim: Einwilligung (Art. 6(1)(a)) bzw. Geschäftsordnung; bei
  vorgeschriebener Geheimwahl ist namentliche Einzelerfassung **rechtswidrig** (Modus-Erzwingung deckt das ab).
- **Wahlgrundsätze technisch:** geheim (keine Rückverfolgbarkeit), frei (kein Vorteil/Nachteil), gleich
  (one-person-one-vote über `Wahlteilnahme`-Unique), unmittelbar, Ergebnis nachvollziehbar ohne Einzelstimmen-Verkettung.

## Online-Wahl: Anfechtungsrisiko (Inbetriebnahme-Schalter)

**Kein gesetzlicher Safe-Harbor** für elektronische Heimbeirat-/MAV-Wahlen — HeimmwV und MVG-EKD beschreiben
Papierverfahren; eine Online-Wahl ist **anfechtbar**, solange keine ausdrückliche Träger-/Behörden-Freigabe vorliegt
(Analogie BGH-Vereinswahlen: zulässig bei technischer Äquivalenz der Grundsätze). Folgen fürs Modul:
- **Niedrigschwellige Umfragen** (`art=Umfrage`, z. B. Ausflug/Speiseplan) sind unkritisch → frei nutzbar.
- **Bindende Wahlen** (`art=Wahl` Heimbeirat/MAV) als **optionaler, abschaltbarer Online-Kanal** — standardmäßig
  hinter einem **Inbetriebnahme-Schalter** (siehe `docs/INBETRIEBNAHME.md`, Regel [[opcare-inbetriebnahme-schalter-regel]]):
  „gebaut & stillgelegt bis Träger-/Behörden-Freigabe + dokumentierte technische Äquivalenz". So bleibt der
  Outcome-Eintrittspunkt vorhanden, ohne ein anfechtbares Verfahren scharf zu schalten.

## UI

Livewire `Abstimmungen` (Route `/abstimmungen`, Nav passend; Gate: Anlegen = admin/leitung, Abstimmen = Elektorats-
Rolle/Person): Liste offener/geschlossener Abstimmungen, Anlegen (Optionen, Elektorat, Modus, Frist), Stimmabgabe
(Optionen + Beleg-Anzeige), Ergebnis (Auszählung + Token-/Namensliste). Hinweis-Kasten zur Anonymitäts-Decke +
DSGVO bei jeder Abstimmung.

## Verifikation

- Geheim: Stimme trägt **keinen** Personen-FG, **UUID-PK, keine timestamps/Datum/Sequenz** (Schema-Test: Tabelle
  `stimmen` hat kein `created_at`/`updated_at`, PK ist UUID); `Wahlteilnahme.hat_abgestimmt` true; Doppelabstimmung
  blockiert (Unique). Namentlich: Stimme trägt Person.
- **Geheim erzwungen** bei `art=Wahl` für Elektorat **Bewohner UND Mitarbeitende** (Versuch `Namentlich` → abgelehnt).
- Beleg-Token random/unique, **nicht gegen Identität auffindbar** (keine Query Person→Token möglich — Test: es gibt
  keine Spalte/Relation, die das erlauben würde).
- Auszählung korrekt; Ergebnis-Sichtbarkeit rollen-gegated; tenant-scoped (kein Fremd-Tenant-Zugriff).
- Bindende Online-Wahl steht **hinter dem Inbetriebnahme-Schalter** (Default stillgelegt); Umfragen frei.
- Anonymitäts-/DSG-EKD-Hinweis sichtbar; identische Stimmzettel-UI (kein personalisierter Stimmzettel).
- Volle Suite/PHPStan/Pint, Screenshot, Doku/Wiki, `docs/INBETRIEBNAHME.md`-Eintrag.

## Folge-Inkrement

Krypto-Härtungspfad (`GeheimKrypto`, blind-signierter Token) für bindende geheime Wahlen mit Server-Unverkettbarkeit.
