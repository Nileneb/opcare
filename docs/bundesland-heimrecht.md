# Bundesland-Overrides (föderales Heimrecht)

Seit der **Föderalismusreform 2006** ist das Heimrecht **Landesrecht**: jedes Land hat ein eigenes Heimgesetz
(Wohn-/Teilhabe-/Betreuungsqualität), das Nachtdienst, Fachkraftquote, Heimmitwirkung und Meldepflichten regelt.
opcare wählt das passende Landesrecht **automatisch aus der Einrichtungs-Adresse** und legt eine
Override-Ebene über die bundeseinheitlichen Defaults.

## Norm → App-Logik

| Norm / Quelle | App-Logik |
|---|---|
| Heimrecht ist Landesrecht (Föderalismusreform 2006) — 16 Landesheimgesetze (`docs/recherche-offene-punkte-2026-06.md §8`) | `Bundesland`-Enum mit Kurztitel, Langtitel und amtlichem Volltext-Link je Land |
| Bundesland aus dem Einrichtungsstandort | `BundeslandResolver::fromPlz()` — Näherung über die PLZ-Leitregion, manuell korrigierbar |
| § 5 HeimPersV — Mindest-Fachkraftquote 50 % (bundeseinheitlich fortgeltend) | `HeimrechtRegelwerk::FACHKRAFTQUOTE_BUND = 0.5` |
| nächtliche Personalrelation (Richtwert) | `HeimrechtRegelwerk::NACHTDIENST_RICHTWERT_BUND = 50` |
| landesspezifische Abweichungen | `HeimrechtRegelwerk::overrides()` — Erweiterungs-Punkt, **bewusst leer statt geratener Werte** |

## Drei-Schichten-Modell (Norm-als-Daten)

**Bundes-Default → Landes-Override → Träger-Override.**

1. **Bundes-Default** — § 5 HeimPersV (50 % Fachkraftquote) + § 113c-PAW (bundeseinheitlich).
2. **Landes-Override** — `HeimrechtRegelwerk::fuer(?Bundesland)`. Wo ein Land einen verifizierten abweichenden
   Schlüssel normiert, wird er hier hinterlegt; sonst gilt der Bundeswert. Es werden **keine Landeswerte geraten** —
   fehlt ein verifizierter Wert, bleibt das Feld leer und die Heimrecht-Seite weist transparent den Bundes-Richtwert
   aus (`landesspezifisch: false`).
3. **Träger-Override** — die editierbare `StaffingConfig` im [Betreuungsschlüssel](betreuungsschluessel-schichtregeln.md).
   `PersonalbemessungDefaults::ensureConfig()` speist die Landes-/Bundeswerte beim **ersten** Anlegen als Default ein.

## Automatische Zuordnung

Das maßgebliche Bundesland (`Tenant::landesrecht()`) ist das **explizit gewählte**, sonst das **aus der PLZ
abgeleitete**. Da PLZ-Leitregionen teils Landesgrenzen überschreiten, ist die Ableitung ein Vorschlag, der auf der
Heimrecht-Seite manuell korrigiert werden kann (`tenants.bundesland`).

## Datenmodell

`tenants.bundesland` (nullable, Enum `Bundesland`). Livewire `App\Livewire\Admin\Heimrecht` (Route `heimrecht`,
nur Leitung; Bearbeiten nur admin/super-admin). Recherche: `docs/recherche-offene-punkte-2026-06.md §8`.
