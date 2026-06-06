<div>
    <div class="page-head">
        <div><p class="kicker">Personal · Arbeitszeit</p><h1>Zeiterfassung</h1>
            <p class="lead">Kommen/Gehen stempeln — Arbeitszeit-Erfassungspflicht (BAG/EuGH).</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Stempeln</h3></div>
        @if ($laufend)
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <span class="badge amber">eingestempelt seit {{ $laufend->beginn }} Uhr ({{ optional($laufend->datum)->format('d.m.Y') }})</span>
                <div class="field" style="margin:0"><label>Pause (Min.)</label><input type="number" min="0" max="480" wire:model="g_pause" style="width:90px" /></div>
                <button class="btn btn-primary btn-sm" wire:click="gehen">Gehen</button>
            </div>
        @else
            <button class="btn btn-primary" wire:click="kommen">Kommen</button>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h3>Meine Woche</h3>
            <div class="plan-nav" style="margin:0">
                <button class="btn btn-ghost btn-sm" wire:click="woche(-1)">← Woche</button>
                <button class="btn btn-ghost btn-sm" wire:click="$set('weekStart', '{{ \Carbon\CarbonImmutable::parse(today())->startOfWeek()->toDateString() }}')">Heute</button>
                <button class="btn btn-ghost btn-sm" wire:click="woche(1)">Woche →</button>
                <b style="margin-left:6px">{{ $weekLabel }}</b>
                <span class="badge {{ $istEigene >= $sollEigene ? 'green' : 'amber' }}" style="margin-left:auto">Ist {{ $istEigene }} h / Soll {{ $sollEigene }} h</span>
            </div>
        </div>
        <table class="data">
            <thead><tr><th>Datum</th><th>Beginn</th><th>Ende</th><th>Pause</th><th>Ist</th><th></th></tr></thead>
            <tbody>
                @forelse ($eigene as $b)
                    <tr>
                        <td>{{ optional($b->datum)->format('d.m.Y') }}</td>
                        <td>{{ $b->beginn }}</td>
                        <td>{{ $b->ende ?? '— läuft' }}</td>
                        <td>{{ $b->pause_minuten }} min</td>
                        <td>{{ $b->istStunden() !== null ? $b->istStunden().' h' : '—' }}</td>
                        <td><button class="btn btn-link" wire:click="entfernen({{ $b->id }})" wire:confirm="Buchung löschen?">Löschen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Keine Buchungen in dieser Woche.</td></tr>
                @endforelse
            </tbody>
        </table>

        <form wire:submit="manuellAnlegen" style="margin-top:14px;border-top:1px solid var(--line-cool);padding-top:14px">
            <div class="form-row-3">
                <div class="field"><label>Datum</label><input type="date" wire:model="m_datum" />@error('m_datum')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Beginn</label><input type="time" wire:model="m_beginn" />@error('m_beginn')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Ende</label><input type="time" wire:model="m_ende" />@error('m_ende')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="field"><label>Pause (Min.)</label><input type="number" min="0" max="480" wire:model="m_pause" style="width:120px" /></div>
            <button class="btn btn-ghost btn-sm">+ Zeit manuell erfassen</button>
        </form>
    </div>

    @if ($darfAlle && $alleUebersicht !== [])
        <div class="card">
            <div class="card-head"><h3>Team — Ist vs. Soll (Woche)</h3><span class="badge gray">Leitung</span></div>
            <table class="data">
                <thead><tr><th>Mitarbeiter:in</th><th>Ist</th><th>Soll (Plan)</th><th>Differenz</th></tr></thead>
                <tbody>
                    @foreach ($alleUebersicht as $z)
                        @php $diff = round($z['ist'] - $z['soll'], 1); @endphp
                        <tr>
                            <td><b>{{ $z['name'] }}</b></td>
                            <td>{{ $z['ist'] }} h</td>
                            <td>{{ $z['soll'] }} h</td>
                            <td><span class="badge {{ $diff < 0 ? 'amber' : 'green' }}">{{ $diff > 0 ? '+' : '' }}{{ $diff }} h</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
