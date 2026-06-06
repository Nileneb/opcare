<div>
    @php($cls = fn ($a) => $a === 'gruen' ? 'green' : ($a === 'gelb' ? 'amber' : 'red'))
    <div class="page-head">
        <div><p class="kicker">Dienstplan · Bedarfsspitzen</p><h1>Spitzenzeiten & Spitzendienste</h1>
            <p class="lead">Tageszeitliche Bedarfs-Fenster (Mahlzeiten, Morgen-Grundpflege) mit Soll-Personenzahl —
                ergänzt den wochenbezogenen § 113c-Betreuungsschlüssel um eine Spitzenzeit-Sicht und schlägt kurze
                Spitzendienste bei Unterdeckung vor.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head">
            <h3>Deckung je Spitzenzeit</h3>
            <span class="badge {{ $analyse->unterdeckungen() === 0 ? 'green' : 'amber' }}">{{ $analyse->unterdeckungen() }} Unterdeckungen</span>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">
            <button class="btn btn-ghost btn-sm" wire:click="woche(-1)">‹ Woche</button>
            <b>{{ $weekLabel }}</b>
            <button class="btn btn-ghost btn-sm" wire:click="woche(1)">Woche ›</button>
        </div>
        <table class="data-table">
            <thead><tr><th>Bedarfs-Fenster</th>@foreach ($analyse->tage as $t)<th style="text-align:center" @class(['muted' => $t['wochenende']])>{{ $t['kurz'] }}<br><span class="muted" style="font-weight:400">{{ $t['tag'] }}</span></th>@endforeach</tr></thead>
            <tbody>
                @forelse ($analyse->fenster as $f)
                    <tr>
                        <td><b>{{ $f->name }}</b><br><span class="muted">{{ $f->beginn }}–{{ $f->ende }} · Soll {{ $f->soll_personen }}@if ($f->nur_werktags) · werktags @endif</span></td>
                        @foreach ($analyse->tage as $t)
                            @php($z = $analyse->zellen[$f->id][$t['datum']])
                            <td style="text-align:center">
                                @if ($z['aktiv'])
                                    <span class="badge {{ $cls($z['ampel']) }}" title="Ist {{ $z['ist'] }} / Soll {{ $z['soll'] }}">{{ $z['ist'] }}/{{ $z['soll'] }}</span>
                                @else
                                    <span class="muted">–</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="8"><p class="empty">Keine aktiven Bedarfs-Fenster.</p></td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($analyse->vorschlaege !== [])
            <p class="kicker" style="margin-top:14px">Vorschläge zur Spitzenzeit-Deckung</p>
            <ul class="muted" style="margin:4px 0 0;padding-left:18px">
                @foreach (array_slice($analyse->vorschlaege, 0, 12) as $v)<li>{{ $v }}</li>@endforeach
                @if (count($analyse->vorschlaege) > 12)<li>… {{ count($analyse->vorschlaege) - 12 }} weitere.</li>@endif
            </ul>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h3>Bedarfs-Fenster verwalten</h3></div>
        <table class="data-table">
            <thead><tr><th>Name</th><th style="width:90px">Beginn</th><th style="width:90px">Ende</th><th style="width:80px">Soll</th><th style="width:90px">werktags</th><th style="width:70px">aktiv</th><th></th></tr></thead>
            <tbody>
                @foreach ($edits as $id => $e)
                    <tr>
                        <td><input type="text" wire:model="edits.{{ $id }}.name" />@error("edits.$id.name")<span class="err">{{ $message }}</span>@enderror</td>
                        <td><input type="time" wire:model="edits.{{ $id }}.beginn" /></td>
                        <td><input type="time" wire:model="edits.{{ $id }}.ende" /></td>
                        <td><input type="number" min="1" max="50" wire:model="edits.{{ $id }}.soll_personen" /></td>
                        <td style="text-align:center"><input type="checkbox" wire:model="edits.{{ $id }}.nur_werktags" /></td>
                        <td style="text-align:center"><input type="checkbox" wire:model="edits.{{ $id }}.aktiv" /></td>
                        <td style="white-space:nowrap">
                            <button class="btn btn-ghost btn-sm" wire:click="speichern({{ $id }})">Speichern</button>
                            <button class="btn btn-ghost btn-sm" wire:click="loeschen({{ $id }})" wire:confirm="Bedarfs-Fenster wirklich entfernen?">✕</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form wire:submit="anlegen" style="margin-top:14px;border-top:1px solid var(--line-cool);padding-top:14px">
            <p class="kicker">Neues Bedarfs-Fenster</p>
            <div class="form-row-3">
                <div class="field"><label>Name</label><input type="text" wire:model="neu_name" placeholder="z. B. Kaffee/Vesper" />@error('neu_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Beginn</label><input type="time" wire:model="neu_beginn" /></div>
                <div class="field"><label>Ende</label><input type="time" wire:model="neu_ende" /></div>
            </div>
            <div class="form-row-2">
                <div class="field"><label>Soll-Personen</label><input type="number" min="1" max="50" wire:model="neu_soll" /></div>
                <div class="field"><label><input type="checkbox" wire:model="neu_werktags" /> nur werktags (Mo–Fr)</label></div>
            </div>
            <button class="btn btn-primary btn-sm">+ Fenster</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Spitzendienst-Schichten</h3><span class="badge gray">{{ $spitzendienste->count() }}</span></div>
        <p class="muted">Kurze Schichten für Bedarfsspitzen — nach dem Anlegen im <a href="{{ route('dienstplan') }}">Dienstplan</a> wie jede Schicht zuweisbar.</p>
        <table class="data-table">
            <thead><tr><th>Name</th><th style="width:120px">Beginn</th><th style="width:120px">Ende</th></tr></thead>
            <tbody>
                @forelse ($spitzendienste as $s)
                    <tr><td><b>{{ $s->name }}</b></td><td>{{ $s->beginn }}</td><td>{{ $s->ende }}</td></tr>
                @empty
                    <tr><td colspan="3"><p class="empty">Noch keine Spitzendienste angelegt.</p></td></tr>
                @endforelse
            </tbody>
        </table>
        <form wire:submit="spitzendienstAnlegen" style="margin-top:14px">
            <div class="form-row-3">
                <div class="field"><label>Name</label><input type="text" wire:model="sd_name" />@error('sd_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Beginn</label><input type="time" wire:model="sd_beginn" /></div>
                <div class="field"><label>Ende</label><input type="time" wire:model="sd_ende" /></div>
            </div>
            <button class="btn btn-ghost btn-sm">+ Spitzendienst</button>
        </form>
    </div>
</div>
