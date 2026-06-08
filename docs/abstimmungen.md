# Abstimmungen & Wahlen (anonym + namentlich)

Modul `app/Domains/Voting` für Umfragen, Wahlen und Beschlüsse — geheim (anonym) oder namentlich, für Bewohner,
Mitarbeitende und Gremien. Route `/abstimmungen`.

## Anonymität by Design (echte Anonymität, nicht nur Pseudonym)

Drei **bewusst entkoppelte** Modelle:
- **`Abstimmung`** / **`AbstimmungOption`** — Metadaten (BaseModel, dürfen geloggt werden).
- **`Wahlteilnahme`** — die **personenbezogene Wählerliste**: nur ein Boolean `hat_abgestimmt` je Berechtigtem,
  Unique `(abstimmung_id, user_id/resident_id)` = one-person-one-vote. **Keine `timestamps`** (kein `updated_at`,
  das sonst die Stimm-Sekunde personenbezogen lecken würde — Datensparsamkeit Art. 5(1)(c)).
- **`Stimme`** — die **anonyme Stimme**: **UUID-PK** (kein Auto-Increment = keine Reihenfolge-Spur), **keine
  `timestamps`**, **kein Personen-FK** bei geheim. Beleg-Token (128 bit) zum Wiederfinden. Energiebarometer-Disziplin
  (`BelongsToTenant`, kein Activity-Log).

So ist die Einzelstimme bei geheim für **niemanden außer der abstimmenden Person** rekonstruierbar — weder über
Personen-FK, noch über Zeitstempel, noch über Insert-Reihenfolge (UUID), noch über die Nachbar-Tabelle
(Wahlteilnahme ist timestamp-frei). DSGVO ErwG 26: echte Anonymisierung → die Stimme ist kein personenbezogenes Datum.

> **Ehrliche Decke:** „niemand außer der Person" ist als Design-/Zugriffsversprechen erreichbar. Vollständige
> Unverkettbarkeit auch gegen einen Root-Admin (der Code/Logs ändern kann) erst mit dem **Krypto-Härtungspfad**
> (blind-signierter Token, Modus `GeheimKrypto`). Der Beleg-Token ist bewusst **nicht vote-beweisend**.

### `GeheimKrypto` — gebaut & stillgelegt (echter Schalter)

Der Modus `GeheimKrypto` ist kein loses Versprechen mehr, sondern ein **registrierter Inbetriebnahme-Schalter**
(`docs/INBETRIEBNAHME.md` §6, `voting.krypto_unverkettbarkeit_aktiv`, Default `false`):

- **Enum-Case** `Stimmodus::GeheimKrypto` (`istGeheim()` true — erfüllt die Geheim-Pflicht, `istKrypto()` true).
- **Service-Sperre an der Quelle:** `AbstimmungStarten` lehnt das Anlegen ab, `StimmeAbgeben` lehnt die Abgabe ab,
  solange der Schalter aus ist (Defense-in-depth). Fehlertext: „Krypto-unverkettbarer Modus stillgelegt (Inbetriebnahme …)".
- **UI** blendet den Modus aus, bis der Schalter an ist — kein wählbarer Modus, dessen Garantie der Code nicht hält.

**Warum stillgelegt:** Die kryptografische Härtung selbst (blind-signierter Berechtigungstoken: der Wähler holt
einen blind signierten Token, gibt ihn beim Einwurf entkoppelt ab, sodass der Server Stimme↔Wähler nicht mehr
verketten kann) ist **noch nicht implementiert**. Sie würde nur halbgar gegen die heutige „pragmatische
Unverkettbarkeit" wirken — also bleibt der Schalter ehrlich aus, statt eine Root-Unverkettbarkeit vorzutäuschen.
Aktivieren: blind-signierten Token-Fluss bauen → `VOTING_KRYPTO_UNVERKETTBARKEIT=true`.

## Ablauf

1. **Anlegen** (admin/leitung): Titel, Elektorat (Bewohner/Mitarbeitende/Gremium), Modus (geheim/namentlich), Art
   (Umfrage/Wahl/Beschluss), Frist, Optionen. **Eröffnen** generiert die `Wahlteilnahme`-Liste je Berechtigtem.
2. **Abstimmen** (eingeloggte Person, für sich selbst): identischer Stimmzettel, Option(en) wählen → bucht die
   anonyme `Stimme` + markiert `hat_abgestimmt` → zeigt **einmalig** den Beleg. Doppelabgabe blockiert.
3. **Ergebnis** (rollen-gegated): Auszählung je Option + Wahlbeteiligung; bei namentlich zusätzlich die Namensliste.

## Rechtsrahmen (Research-verifiziert)

- **Geheime Wahl erzwungen** bei `art=Wahl` + Bewohner/Mitarbeitende (Namentlich → abgelehnt): **§ 5 HeimmwV**
  (Heimbeirat geheim/unmittelbar), **§ 11 MVG-EKD** (Diakonie-MAV — NICHT BetrVG). Anwendbares DS-Recht: **DSG-EKD**.
- **Löschfrist** Wählerliste/Stimmen: Ende Amtszeit + Anfechtungsfrist; danach nur anonymes Ergebnisprotokoll.
- **Online-Wahl hat keinen Safe-Harbor** (anfechtbar) → bindende Online-Wahlen hinter dem Inbetriebnahme-Schalter
  `voting.online_wahl_aktiv` (Default aus, blockiert schon bei der Eröffnung); Umfragen frei.

## Offen (Folge-Inkremente)

- **Bewohner-Kiosk-Stimmabgabe** — Residents haben i.d.R. keinen Login; diese UI deckt User-Login-Wähler
  (Mitarbeitende/Gremium) ab. Der assistierte/Kiosk-Pfad für Bewohner-Wahlen ist ein Folge-Inkrement
  (`docs/INBETRIEBNAHME.md`).
- **Krypto-Härtungspfad** (`GeheimKrypto`, blind-signierter Token) für Server-Unverkettbarkeit — als
  **registrierter Schalter** gebaut & stillgelegt (siehe oben + `docs/INBETRIEBNAHME.md` §6), Implementierung der
  Krypto-Primitive offen.

## Spec & Plan

- Spec: `docs/superpowers/specs/2026-06-07-abstimmungen-wahlen-design.md`
- Plan: `docs/superpowers/plans/2026-06-07-abstimmungen-wahlen.md`
