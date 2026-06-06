# Team-Energiebarometer (Beschäftigtendaten)

Ein freiwilliges Stimmungsbarometer als **Frühwarnsignal gegen Überlastung** — bewusst datensparsam und
mitbestimmungskonform gebaut, kein Leistungs-Monitoring.

## Norm → App-Logik

| Norm / Quelle | App-Logik |
|---|---|
| **§ 26 BDSG** — Beschäftigtendatenschutz, Freiwilligkeit | Teilnahme optional; eigener Wert jederzeit zurücknehmbar (`zuruecknehmen()`) |
| **§ 87 Abs. 1 Nr. 6 BetrVG** — Mitbestimmung bei techn. Überwachungseinrichtungen | Hinweis in der UI: Einführung mitbestimmungspflichtig (Betriebsrat/Mitarbeitervertretung) |
| Datenminimierung — kein personenbezogenes Verlaufstracking | **genau eine** aktuelle Zeile je Mitarbeitendem (`unique[tenant_id,user_id]`, überschreibend); Modell ohne `LogsActivity` |
| keine Re-Identifikation aus dem Aggregat | nur **anonymer Hausschnitt**; Anzeige erst ab `MIN_AUSWERTBAR = 3` Rückmeldungen (k-Anonymität) |

## Operativ

Jede:r Mitarbeitende setzt das eigene aktuelle Energie-Level (dreistufige Ampel: Erschöpft 🔴 / Geht so 🟡 /
Energiegeladen 🟢). Sichtbar ist ausschließlich der aggregierte Hausschnitt mit Gesamt-Ampel (gewichteter
Schnitt) — **nie** ein personenbezogener Wert. Erreichbar für alle Stamm-Rollen über die Dienstplan-Navigation
(„Energie"); Portal-Nutzer (Betreuer/Angehörige) sind ausgeschlossen.

## Datenmodell

`energielevels` (tenant_id, user_id unique, stufe, timestamps), Enum `Energiestufe`, Domain `Personnel`.
Livewire `App\Livewire\Personnel\Energiebarometer` (Route `energiebarometer`). Recherche:
`docs/recherche-offene-punkte-2026-06.md §9`.
