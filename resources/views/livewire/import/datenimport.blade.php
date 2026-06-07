<div>
    @php
        $statusBadge = fn ($s) => match ($s instanceof \App\Domains\Import\Enums\ImportZeileStatus ? $s->value : $s) {
            'vorgeschlagen' => 'amber',
            'importiert' => 'green',
            'uebersprungen' => 'gray',
            default => 'gray',
        };
        $aktionLabel = fn ($a) => match ($a instanceof \App\Domains\Import\Enums\ImportAktion ? $a->value : $a) {
            'anlegen' => 'Anlegen',
            'mergen' => 'Mergen',
            'ueberspringen' => 'Überspringen',
            default => '–',
        };
        $zielFeldLabel = fn ($f) => match ($f) {
            'name' => 'Name / Bezeichnung',
            'einheit' => 'Einheit',
            'abteilung' => 'Abteilung',
            'einkaufspreis' => 'Einkaufspreis',
            'mindestbestand' => 'Mindestbestand',
            'bestand' => 'Anfangsbestand',
            'einstandspreis' => 'Einstandspreis',
            'pg_nummer' => 'PG-Nummer',
            'lieferant' => 'Lieferant',
            'charge_nr' => 'Charge-Nr.',
            'mhd' => 'MHD',
            default => $f,
        };
    @endphp

    <div class="page-head">
        <div>
            <p class="kicker">Verwaltung · Finanzen</p>
            <h1>Datenimport (Stammdaten)</h1>
            <p class="lead">CSV hochladen → Spalten prüfen/korrigieren → Zeilen bestätigen. Nichts wird ohne deine Bestätigung gebucht.</p>
        </div>
    </div>

    <div class="card" style="border-left:4px solid var(--color-amber,#f59e0b);padding:12px 16px;margin-bottom:16px">
        <strong>Datenschutz-Hinweis:</strong> Nur Waren- und Lieferantendaten — keine Bewohnerdaten hochladen.
    </div>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    {{-- ─── Upload-Karte ─── --}}
    <div class="card">
        <div class="card-head"><h3>CSV hochladen</h3></div>
        <form wire:submit="parsen">
            <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
                <div class="field">
                    <label>CSV-Datei</label>
                    <input type="file" wire:model="datei" accept=".csv,.txt" />
                    @error('datei')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Ziel-Typ</label>
                    <select wire:model="ziel_typ">
                        <option value="artikel">Artikel</option>
                        <option value="lieferant">Lieferant</option>
                    </select>
                </div>
                <div class="field">
                    <label>Anfangsbestand-Modus</label>
                    <select wire:model="anfangsbestand_modus">
                        <option value="ebk">Eröffnungsbilanz (EBK)</option>
                        <option value="verbindlichkeit">Verbindlichkeit</option>
                    </select>
                </div>
            </div>
            <div wire:loading wire:target="datei" class="muted" style="margin-top:6px">Lade Datei …</div>
            <div wire:loading wire:target="parsen" class="muted" style="margin-top:6px">Analysiere CSV …</div>
            <button class="btn btn-primary btn-sm" style="margin-top:10px"
                wire:loading.attr="disabled" wire:target="parsen">Parsen &amp; Vorschau</button>
        </form>
    </div>

    @if ($batch)
        {{-- ─── Spalten-Mapping-Block ─── --}}
        <div class="card">
            <div class="card-head">
                <h3>Spalten-Mapping</h3>
                <span class="muted" style="font-size:.85em">Datei: {{ $batch->dateiname }}</span>
            </div>
            <p class="muted" style="margin-bottom:12px">Ordne jede Datei-Spalte dem richtigen Zielfeld zu. Felder ohne passende Spalte auf „– ignorieren –" lassen.</p>

            @if (count($headerSpalten) > 0)
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px">
                    @foreach ($zielFelder as $ziel)
                        <div class="field" style="margin:0">
                            <label style="font-size:.85em">{{ $zielFeldLabel($ziel) }}</label>
                            <select wire:model="mapping.{{ $ziel }}" style="width:100%">
                                <option value="">– ignorieren –</option>
                                @foreach ($headerSpalten as $spalte)
                                    <option value="{{ $spalte }}">{{ $spalte }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <button class="btn btn-secondary btn-sm" style="margin-top:12px"
                    wire:click="mappingAnwenden"
                    wire:loading.attr="disabled" wire:target="mappingAnwenden">Mapping anwenden &amp; neu berechnen</button>
            @else
                <p class="muted">Keine Header-Spalten erkannt.</p>
            @endif
        </div>

        {{-- ─── Status-Badges ─── --}}
        <div style="display:flex;gap:8px;margin-bottom:12px">
            <span class="badge amber">{{ $statusZaehler['vorgeschlagen'] }} Vorgeschlagen</span>
            <span class="badge green">{{ $statusZaehler['importiert'] }} Importiert</span>
            <span class="badge gray">{{ $statusZaehler['uebersprungen'] }} Übersprungen</span>
        </div>

        {{-- ─── Alle übernehmen ─── --}}
        @if ($statusZaehler['vorgeschlagen'] > 0)
            <div style="margin-bottom:12px">
                <button class="btn btn-primary btn-sm"
                    wire:click="bestaetigeAlle"
                    wire:confirm="Alle {{ $statusZaehler['vorgeschlagen'] }} offenen Zeilen importieren?"
                    wire:loading.attr="disabled" wire:target="bestaetigeAlle">
                    Alle übernehmen ({{ $statusZaehler['vorgeschlagen'] }})
                </button>
            </div>
        @endif

        {{-- ─── Zeilen-Tabelle ─── --}}
        <div class="card">
            <div class="card-head"><h3>Zeilen</h3></div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Einheit</th>
                        <th>Bestand</th>
                        <th>Einstandspreis</th>
                        <th>Aktion</th>
                        <th>Match</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($zeilen as $z)
                        <tr>
                            <td>
                                @if ($z->offen())
                                    <input type="text" wire:model="ist.{{ $z->id }}.name"
                                        style="width:160px" />
                                    @error("ist.{$z->id}.name")<span class="err">{{ $message }}</span>@enderror
                                @else
                                    {{ $z->name ?: '–' }}
                                @endif
                            </td>
                            <td>
                                @if ($z->offen())
                                    <input type="text" wire:model="ist.{{ $z->id }}.einheit"
                                        style="width:70px" />
                                @else
                                    {{ $z->einheit ?: '–' }}
                                @endif
                            </td>
                            <td>
                                @if ($z->offen())
                                    <input type="number" step="0.01" wire:model="ist.{{ $z->id }}.bestand"
                                        style="width:90px" placeholder="Menge" />
                                    @error("ist.{$z->id}.bestand")<span class="err">{{ $message }}</span>@enderror
                                @else
                                    {{ $z->bestand !== null ? number_format((float) $z->bestand, 2, ',', '.') : '–' }}
                                @endif
                            </td>
                            <td>
                                @if ($z->offen())
                                    <input type="number" step="0.0001" wire:model="ist.{{ $z->id }}.einstandspreis"
                                        style="width:90px" placeholder="€" />
                                    @error("ist.{$z->id}.einstandspreis")<span class="err">{{ $message }}</span>@enderror
                                @else
                                    {{ $z->einstandspreis !== null ? number_format((float) $z->einstandspreis, 4, ',', '.') : '–' }}
                                @endif
                            </td>
                            <td style="min-width:130px">
                                @if ($z->offen())
                                    <select wire:model="ist.{{ $z->id }}.aktion" style="width:100%">
                                        <option value="anlegen">Anlegen</option>
                                        <option value="mergen">Mergen</option>
                                        <option value="ueberspringen">Überspringen</option>
                                    </select>
                                    @error("ist.{$z->id}.aktion")<span class="err">{{ $message }}</span>@enderror
                                @else
                                    {{ $aktionLabel($z->aktion) }}
                                @endif
                            </td>
                            <td style="min-width:180px">
                                @if ($z->offen())
                                    @if ($z->ziel_typ === 'artikel')
                                        <select wire:model="ist.{{ $z->id }}.matched_artikel_id" style="width:100%">
                                            <option value="">– Artikel wählen –</option>
                                            @if ($z->kandidaten)
                                                <optgroup label="Vorschläge">
                                                    @foreach ($z->kandidaten as $k)
                                                        <option value="{{ $k['artikel_id'] ?? '' }}">
                                                            {{ $k['name'] ?? '–' }}
                                                            @if (isset($k['score']))
                                                                ({{ number_format((float) $k['score'] * 100, 0) }} %)
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                            <optgroup label="Alle Artikel">
                                                @foreach ($artikel as $a)
                                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                        @error("ist.{$z->id}.matched_artikel_id")<span class="err">{{ $message }}</span>@enderror
                                    @elseif ($z->ziel_typ === 'lieferant')
                                        <select wire:model="ist.{{ $z->id }}.matched_lieferant_id" style="width:100%">
                                            <option value="">– Lieferant wählen –</option>
                                            @foreach ($lieferanten as $l)
                                                <option value="{{ $l->id }}">{{ $l->name }}</option>
                                            @endforeach
                                        </select>
                                        @error("ist.{$z->id}.matched_lieferant_id")<span class="err">{{ $message }}</span>@enderror
                                    @endif
                                @else
                                    @if ($z->ergebnis_artikel_id)
                                        Artikel #{{ $z->ergebnis_artikel_id }}
                                    @elseif ($z->ergebnis_lieferant_id)
                                        Lieferant #{{ $z->ergebnis_lieferant_id }}
                                    @else
                                        –
                                    @endif
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $statusBadge($z->status) }}">{{ $z->status->label() }}</span>
                            </td>
                            <td style="white-space:nowrap">
                                @if ($z->offen())
                                    <button class="btn btn-primary btn-sm"
                                        wire:click="bestaetigeZeile({{ $z->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="bestaetigeZeile({{ $z->id }})">Übernehmen</button>
                                    <button class="btn btn-ghost btn-sm"
                                        wire:click="$set('ist.{{ $z->id }}.aktion','ueberspringen')"
                                        wire:then="bestaetigeZeile({{ $z->id }})">Überspringen</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><p class="empty">Keine Zeilen gefunden.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="card">
            <p class="empty" style="padding:24px">Noch keine CSV geladen. Datei hochladen und „Parsen" klicken.</p>
        </div>
    @endif
</div>
