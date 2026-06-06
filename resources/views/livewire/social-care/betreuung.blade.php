<div>
    <div class="page-head">
        <div><p class="kicker">Soziale Betreuung</p><h1>Betreuung & Aktivierung</h1>
            <p class="lead">Angebote der zusätzlichen Betreuung (§ 43b SGB XI) planen und die Teilnahme je Bewohner dokumentieren.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Angebote</h3>
            <div class="plan-nav" style="margin:0">
                <button class="btn btn-ghost btn-sm" wire:click="tag(-1)">← Tag</button>
                <button class="btn btn-ghost btn-sm" wire:click="$set('datum', '{{ today()->toDateString() }}')">Heute</button>
                <button class="btn btn-ghost btn-sm" wire:click="tag(1)">Tag →</button>
                <b style="margin-left:6px">{{ $datumLabel }}</b>
            </div>
        </div>

        @forelse ($angebote as $a)
            <div class="qm-item">
                <div class="qm-anf">
                    <b>{{ $a->titel }}</b>
                    <span class="badge gray">{{ $a->art->label() }}</span>
                    <span class="badge gray">{{ $a->typ->label() }}</span>
                    <span class="muted">{{ $a->beginn ? $a->beginn.' Uhr · ' : '' }}{{ $a->dauer_minuten }} min</span>
                    <span class="badge {{ $a->teilnahmen->count() ? 'green' : 'amber' }}">{{ $a->teilnahmen->count() }} Teilnehmer</span>
                    <button class="btn btn-ghost btn-sm" wire:click="teilnahmeOeffnen({{ $a->id }})" style="margin-left:auto">Teilnehmer</button>
                    <button class="btn btn-ghost btn-sm" wire:click="angebotEntfernen({{ $a->id }})" wire:confirm="Angebot entfernen?">✕</button>
                </div>
                @if ($teilnAngebot === $a->id)
                    <div class="plan-begruenden">
                        <p class="muted" style="margin:0 0 6px">Wer hat teilgenommen?</p>
                        <div class="kueche-allergene">
                            @foreach ($residents as $r)
                                <label class="kueche-chk"><input type="checkbox" value="{{ $r->id }}" wire:model="teilnehmer" /> {{ $r->name }}</label>
                            @endforeach
                        </div>
                        <div style="display:flex;gap:8px;margin-top:8px">
                            <button class="btn btn-primary btn-sm" wire:click="teilnahmeSpeichern">Teilnahme speichern</button>
                            <button class="btn btn-ghost btn-sm" wire:click="$set('teilnAngebot', null)">Abbrechen</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="empty">Keine Angebote an diesem Tag.</p>
        @endforelse

        <form wire:submit="angebotAnlegen" style="margin-top:14px;border-top:1px solid var(--line-cool);padding-top:14px">
            <div class="form-row-3">
                <div class="field"><label>Art</label><select wire:model="a_art">@foreach ($arten as $art)<option value="{{ $art->value }}">{{ $art->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Titel *</label><input type="text" wire:model="a_titel" placeholder="z. B. Singkreis im Aufenthaltsraum" />@error('a_titel')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Typ</label><select wire:model="a_typ">@foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Beginn</label><input type="time" wire:model="a_beginn" /></div>
                <div class="field"><label>Dauer (Min.)</label><input type="number" min="5" max="480" wire:model="a_dauer" /></div>
            </div>
            <button class="btn btn-ghost btn-sm">+ Angebot</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Betreuungs-Nachweis je Bewohner</h3><span class="badge gray">{{ $monatLabel }}</span></div>
        <table class="data">
            <thead><tr><th>Bewohner:in</th><th>Einheiten</th><th>Minuten</th><th></th></tr></thead>
            <tbody>
                @foreach ($residents as $r)
                    @php $b = $bilanz[$r->id] ?? ['einheiten' => 0, 'minuten' => 0]; @endphp
                    <tr>
                        <td><b>{{ $r->name }}</b></td>
                        <td>{{ $b['einheiten'] }}</td>
                        <td>{{ $b['minuten'] }} min</td>
                        <td>@if ($b['einheiten'] === 0)<span class="badge amber">noch keine Betreuung im Monat</span>@else<span class="badge green">dokumentiert</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
