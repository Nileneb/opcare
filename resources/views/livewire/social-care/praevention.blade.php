<div>
    <div class="page-head">
        <div><p class="kicker">Soziale Betreuung · Prävention</p><h1>Prävention (§ 5 SGB XI)</h1>
            <p class="lead">Kassenfinanzierte Programme je Handlungsfeld; Teilnahmen je Bewohner als Verwendungsnachweis.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    @foreach ($handlungsfelder as $feld)
        @php($programme = $programmeNachFeld[$feld->value] ?? collect())
        <div class="card">
            <div class="card-head"><h3><span class="badge {{ $feld->badge() }}">{{ $feld->label() }}</span></h3>
                <span class="badge gray">{{ $programme->count() }} Programm(e)</span>
            </div>
            @forelse ($programme as $p)
                <div class="qm-anf" style="padding:7px 0;border-bottom:1px solid var(--line-cool)">
                    <b>{{ $p->titel }}</b>
                    @if ($p->frequenz)<span class="muted">· {{ $p->frequenz }}</span>@endif
                    @if ($p->verantwortlich)<span class="muted">· {{ $p->verantwortlich }}</span>@endif
                    <span class="badge gray" title="Verwendungsnachweis">{{ $p->teilnahmen_count }} Teilnahmen · {{ (int) $p->teilnahmen_sum_dauer_minuten }} Min.</span>
                    <span style="margin-left:auto;display:inline-flex;gap:6px">
                        <button class="btn btn-ghost btn-sm" wire:click="teilnahmeStart({{ $p->id }})">Teilnahme +</button>
                        <button class="btn btn-ghost btn-sm" wire:click="programmEntfernen({{ $p->id }})" wire:confirm="Programm löschen?">✕</button>
                    </span>
                </div>
                @if ($teilnProgramm === $p->id)
                    <div style="background:var(--bg-cool);border-radius:8px;padding:12px;margin:8px 0">
                        <p class="kicker">Teilnahme dokumentieren — {{ $p->titel }}</p>
                        <div class="form-row-2">
                            <div class="field"><label>Datum</label><input type="date" wire:model="t_datum" />@error('t_datum')<span class="err">{{ $message }}</span>@enderror</div>
                            <div class="field"><label>Dauer (Min.)</label><input type="number" wire:model="t_dauer" />@error('t_dauer')<span class="err">{{ $message }}</span>@enderror</div>
                        </div>
                        <div class="field"><label>Teilnehmende Bewohner:innen</label>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;max-height:160px;overflow:auto">
                                @foreach ($bewohner as $b)
                                    <label style="font-weight:400;white-space:nowrap"><input type="checkbox" value="{{ $b->id }}" wire:model="t_teilnehmer" /> {{ $b->name }}</label>
                                @endforeach
                            </div>
                            @error('t_teilnehmer')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field"><label>Beobachtung (optional)</label><input type="text" wire:model="t_beobachtung" placeholder="z. B. gute Beteiligung" /></div>
                        <button class="btn btn-primary btn-sm" wire:click="teilnahmeSpeichern">Speichern</button>
                    </div>
                @endif
            @empty
                <p class="empty">Noch kein Programm in diesem Handlungsfeld.</p>
            @endforelse
        </div>
    @endforeach

    <div class="card">
        <div class="card-head"><h3>Programm anlegen</h3></div>
        <form wire:submit="programmAnlegen">
            <div class="form-row-2">
                <div class="field"><label>Handlungsfeld</label><select wire:model="p_handlungsfeld">@foreach ($handlungsfelder as $h)<option value="{{ $h->value }}">{{ $h->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Titel</label><input type="text" wire:model="p_titel" placeholder="z. B. Sturzpräventions-Gymnastik" />@error('p_titel')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-row-2">
                <div class="field"><label>Frequenz (optional)</label><input type="text" wire:model="p_frequenz" placeholder="z. B. wöchentlich" /></div>
                <div class="field"><label>Verantwortlich (optional)</label><input type="text" wire:model="p_verantwortlich" /></div>
            </div>
            <button class="btn btn-ghost btn-sm">+ Programm</button>
        </form>
    </div>
</div>
