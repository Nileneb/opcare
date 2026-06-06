<div>
    <div class="page-head">
        <div><p class="kicker">Planung · Selbstverwaltung</p><h1>Tauschbörse &amp; Krankmeldung</h1>
            <p class="lead">Dienste tauschen, sich krankmelden und offene Vertretungen übernehmen.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="flash" style="background:#fee2e2">{{ session('error') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Krankmeldung / Abwesenheit</h3></div>
        <form wire:submit="krankmelden">
            <div class="form-row-3">
                @if ($darfFuerAndere)
                    <div class="field"><label>Mitarbeiter:in</label>
                        <select wire:model="km_user"><option value="">– wählen –</option>@foreach ($kollegen as $k)<option value="{{ $k->id }}">{{ $k->name }}</option>@endforeach</select>
                        @error('km_user')<span class="err">{{ $message }}</span>@enderror
                    </div>
                @endif
                <div class="field"><label>Art</label><select wire:model="km_typ">@foreach ($abwesenheitsTypen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Von</label><input type="date" wire:model="km_von" />@error('km_von')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Bis</label><input type="date" wire:model="km_bis" />@error('km_bis')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="field"><label>Notiz (optional)</label><input type="text" wire:model="km_notiz" /></div>
            <button class="btn btn-primary btn-sm">Abwesenheit melden</button>
        </form>
        @if ($meineAbwesenheiten->isNotEmpty())
            <div style="margin-top:10px">
                @foreach ($meineAbwesenheiten as $a)
                    <span class="badge {{ $a->typ->badge() }}">{{ $a->typ->label() }}: {{ $a->von->format('d.m.') }}–{{ $a->bis->format('d.m.Y') }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid-2" style="align-items:start;gap:18px">
        <div class="card">
            <div class="card-head"><h3>Meine Dienste</h3></div>
            @forelse ($meineDienste as $d)
                <div class="qm-anf" style="padding:6px 0;border-bottom:1px solid var(--line-cool)">
                    <b>{{ $d->dienst_am->isoFormat('dd DD.MM.') }}</b> {{ $d->shift?->name }}
                    @if (in_array($d->id, $offeneIds, true))
                        <span class="badge amber" style="margin-left:auto">zum Tausch offen</span>
                    @else
                        <button class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="tauschAnbieten({{ $d->id }})">Abgeben / Tauschen</button>
                    @endif
                </div>
            @empty
                <p class="empty">Keine künftigen Dienste.</p>
            @endforelse

            @if ($meineAnfragen->isNotEmpty())
                <p class="kicker" style="margin-top:12px">Meine offenen Anfragen</p>
                @foreach ($meineAnfragen as $r)
                    <div class="qm-anf" style="padding:5px 0">
                        <span>{{ $r->assignment->dienst_am->format('d.m.') }} {{ $r->assignment->shift?->name }}</span>
                        <span class="badge {{ $r->typ === 'krankheit' ? 'red' : 'amber' }}">{{ $r->typ === 'krankheit' ? 'Vertretung' : 'Tausch' }}</span>
                        <button class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="zurueckziehen({{ $r->id }})">zurückziehen</button>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h3>Offene Vertretungen &amp; Tausche</h3><span class="badge gray">{{ $offeneFremd->count() }}</span></div>
            @forelse ($offeneFremd as $r)
                <div class="qm-anf" style="padding:7px 0;border-bottom:1px solid var(--line-cool)">
                    <b>{{ $r->assignment->dienst_am->isoFormat('dd DD.MM.') }}</b> {{ $r->assignment->shift?->name }}
                    <span class="badge {{ $r->typ === 'krankheit' ? 'red' : 'amber' }}">{{ $r->typ === 'krankheit' ? 'Krankheits-Vertretung' : 'Tausch' }}</span>
                    <span class="muted">von {{ $r->anfrager?->name }}</span>
                    <button class="btn btn-primary btn-sm" style="margin-left:auto" wire:click="uebernehmen({{ $r->id }})">Übernehmen</button>
                </div>
            @empty
                <p class="empty">Keine offenen Anfragen.</p>
            @endforelse
        </div>
    </div>
</div>
