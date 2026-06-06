<div>
    <div class="page-head">
        <div><p class="kicker">Verwaltung · Landesrecht</p><h1>Heimrecht (Landesrecht)</h1>
            <p class="lead">Seit der Föderalismusreform 2006 ist das Heimrecht Landesrecht — Nachtdienst, Fachkraftquote,
                Heimmitwirkung und Meldepflichten regelt jedes Land in einem eigenen Heimgesetz.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Geltendes Landesheimrecht</h3>
            @if ($land)<span class="badge {{ $ausPlz ? 'amber' : 'green' }}">{{ $ausPlz ? 'aus PLZ abgeleitet' : 'manuell gewählt' }}</span>
            @else<span class="badge red">nicht ermittelbar</span>@endif
        </div>

        @if ($land)
            <table class="data-table">
                <tbody>
                    <tr><th style="width:240px">Bundesland</th><td><b>{{ $land->label() }}</b></td></tr>
                    <tr><th>Landesheimgesetz</th><td><b>{{ $land->heimgesetz() }}</b> — {{ $land->gesetzTitel() }}</td></tr>
                    <tr><th>Amtlicher Volltext</th><td><a href="{{ $land->gesetzUrl() }}" target="_blank" rel="noopener">{{ $land->gesetzUrl() }}</a></td></tr>
                    <tr><th>Standort</th><td>{{ $tenant->plz }} {{ $tenant->ort }}</td></tr>
                </tbody>
            </table>
        @else
            <p class="empty">Kein Bundesland hinterlegt und keine (gültige) PLZ in der Einrichtungs-Adresse. Bitte Bundesland wählen.</p>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h3>Abgeleitete Personalbemessung</h3>
            <span class="badge {{ $heimrecht['landesspezifisch'] ? 'green' : 'gray' }}">{{ $heimrecht['landesspezifisch'] ? 'Landeswert' : 'bundeseinheitlicher Richtwert' }}</span>
        </div>
        <table class="data-table">
            <thead><tr><th>Kennzahl</th><th style="text-align:right;width:140px">Wert</th><th>Grundlage</th></tr></thead>
            <tbody>
                <tr>
                    <td>Mindest-Fachkraftquote</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums"><b>{{ number_format($heimrecht['fachkraftquote_min'] * 100, 0) }} %</b></td>
                    <td class="muted">§ 5 HeimPersV (bundeseinheitlich fortgeltend)</td>
                </tr>
                <tr>
                    <td>Bewohner je Nachtwache (Richtwert)</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums"><b>{{ $heimrecht['nachtdienst_je_fachkraft'] }}</b></td>
                    <td class="muted">bundeseinheitlicher Richtwert</td>
                </tr>
            </tbody>
        </table>
        <p class="muted" style="margin-top:10px">Diese Defaults speisen den <a href="{{ route('arbeitsrecht') }}">Betreuungsschlüssel</a> beim ersten
            Anlegen (Bundes-Default → Landes-Override → Träger-Override). Wo ein Land keinen verifizierten abweichenden Schlüssel
            hinterlegt hat, gilt der Bundeswert — landesspezifische Werte trägt der Träger im Betreuungsschlüssel ein
            (kein geratener Landeswert). Quelle: <code>docs/recherche-offene-punkte-2026-06.md §8</code>.</p>
    </div>

    @if ($darfBearbeiten)
        <div class="card">
            <div class="card-head"><h3>Bundesland zuordnen</h3></div>
            <form wire:submit="speichern">
                <div class="field" style="max-width:420px">
                    <label>Bundesland (leer = automatisch aus der PLZ)</label>
                    <select wire:model="bundesland">
                        <option value="">– automatisch aus Adresse –</option>
                        @foreach ($laender as $b)<option value="{{ $b->value }}">{{ $b->label() }} ({{ $b->heimgesetz() }})</option>@endforeach
                    </select>
                    @error('bundesland')<span class="err">{{ $message }}</span>@enderror
                </div>
                <button class="btn btn-primary btn-sm" style="margin-top:10px">Speichern</button>
            </form>
        </div>
    @endif
</div>
