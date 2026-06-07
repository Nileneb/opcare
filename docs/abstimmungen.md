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
> (blind-signierter Token, `GeheimKrypto` — vorgemerkte Naht). Der Beleg-Token ist bewusst **nicht vote-beweisend**.

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
- **Krypto-Härtungspfad** (`GeheimKrypto`, blind-signierter Token) für Server-Unverkettbarkeit.

## Spec & Plan

- Spec: `docs/superpowers/specs/2026-06-07-abstimmungen-wahlen-design.md`
- Plan: `docs/superpowers/plans/2026-06-07-abstimmungen-wahlen.md`
