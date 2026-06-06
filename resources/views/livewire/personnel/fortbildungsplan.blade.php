<div>
    <div class="page-head">
        <div><p class="kicker">Personal · Qualifizierung</p><h1>Fortbildungsplan</h1>
            <p class="lead">Geplante und absolvierte Fortbildungen je Mitarbeiter:in (QPR QB6, § 132a SGB V) mit
                Pflicht-Themen-Matrix und Auffrischungs-Ampel. Ein Pflichtthema ohne gültigen Nachweis ist rot —
                so wird die Fortbildungspflicht des Trägers operativ nachvollziehbar.</p></div>
        @if ($luecken > 0)
            <span class="badge red" style="align-self:center">{{ $luecken }} Pflicht-Lücke(n)</span>
        @else
            <span class="badge green" style="align-self:center">Pflichtthemen erfüllt</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Pflicht-Themen-Matrix (Auffrischungs-Ampel)</h3></div>
        <table class="data">
            <thead><tr><th>Mitarbeiter:in</th>@foreach ($pflichtThemen as $t)<th title="{{ $t->rechtsbasis() }}">{{ $t->label() }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td><b>{{ $u->name }}</b></td>
                        @foreach ($pflichtThemen as $t)
                            @php $fb = $matrix[$u->id][$t->value] ?? null; @endphp
                            <td>
                                @if ($fb)
                                    <span class="badge {{ $fb->ampel() }}">{{ $fb->absolviert_am->format('m/Y') }}</span>
                                @else
                                    <span class="badge red">fehlt</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($pflichtThemen) + 1 }}" class="muted">Keine Mitarbeitenden mit Personalakte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>Fortbildung erfassen</h3></div>
        <form wire:submit="planen">
            <div class="form-row-3">
                <div class="field"><label>Mitarbeiter:in *</label><select wire:model="f_user"><option value="">– wählen –</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>@error('f_user')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Thema</label><select wire:model.live="f_thema">@foreach ($themen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Titel *</label><input type="text" wire:model="f_titel" placeholder="z. B. Auffrischung Händehygiene" />@error('f_titel')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Anbieter</label><input type="text" wire:model="f_anbieter" /></div>
                <div class="field"><label>Geplant am</label><input type="date" wire:model="f_geplant_am" /></div>
                <div class="field"><label>Absolviert am</label><input type="date" wire:model="f_absolviert_am" /></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Umfang (Stunden)</label><input type="number" wire:model="f_stunden" />@error('f_stunden')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Pflicht-Intervall (Monate)</label><input type="number" wire:model="f_intervall" @disabled(! $f_pflicht) />@error('f_intervall')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>&nbsp;</label><label class="muted" style="display:flex;align-items:center;gap:4px;font-weight:normal"><input type="checkbox" wire:model="f_pflicht" /> Pflichtfortbildung (wiederkehrend)</label></div>
            </div>
            <button class="btn btn-primary btn-sm">+ Fortbildung</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Geplante & absolvierte Fortbildungen</h3></div>
        <table class="data">
            <thead><tr><th>Mitarbeiter:in</th><th>Thema / Titel</th><th>Status</th><th>Nächste Fälligkeit</th><th></th></tr></thead>
            <tbody>
                @forelse ($fortbildungen as $f)
                    <tr>
                        <td><b>{{ $f->user->name }}</b></td>
                        <td>{{ $f->titel }}<br><span class="muted" style="font-size:.8em">{{ $f->thema->label() }}@if ($f->anbieter) · {{ $f->anbieter }}@endif@if ($f->umfang_stunden) · {{ $f->umfang_stunden }} h@endif</span></td>
                        <td>
                            @if ($f->absolviert_am)
                                <span class="badge {{ $f->ampel() }}">absolviert {{ $f->absolviert_am->format('d.m.Y') }}</span>
                            @else
                                <span class="badge {{ $f->ampel() }}">geplant@if ($f->geplant_am) {{ $f->geplant_am->format('d.m.Y') }}@endif</span>
                                <button class="btn btn-ghost btn-sm" wire:click="absolviert({{ $f->id }})">absolviert</button>
                            @endif
                        </td>
                        <td>@if ($f->naechsteFaelligkeit())<span class="badge {{ $f->ampel() }}">{{ $f->naechsteFaelligkeit()->format('d.m.Y') }}</span>@else<span class="muted">—</span>@endif</td>
                        <td><button class="btn btn-ghost btn-sm" wire:click="loeschen({{ $f->id }})" wire:confirm="Eintrag entfernen?">entfernen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch keine Fortbildungen erfasst.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
