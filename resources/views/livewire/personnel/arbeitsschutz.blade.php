<div>
    <div class="page-head">
        <div><p class="kicker">Personal · Arbeitsschutz</p><h1>Arbeitsschutz-Nachweise</h1>
            <p class="lead">Unterweisung, Vorsorge, Erste Hilfe, Brandschutzhelfer und BEM mit Fälligkeits-Ampel.</p></div>
        @if ($ueberfaellig > 0)
            <span class="badge red" style="align-self:center">{{ $ueberfaellig }} überfällig</span>
        @else
            <span class="badge green" style="align-self:center">alle Fristen gewahrt</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Mitarbeiter:in</th>
                    @foreach ($typen as $t)
                        <th title="{{ $t->gesetz() }}">{{ $t->label() }}<br><span class="muted" style="font-weight:400;font-size:.8em">{{ $t->intervallMonate() ? $t->intervallMonate().' Mon.' : 'anlassbezogen' }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td><b>{{ $user->name }}</b><br><span class="muted" style="font-size:.8em">{{ $user->employeeProfile?->qualifikation?->label() }}</span></td>
                        @foreach ($typen as $t)
                            @php($n = $latest[$user->id][$t->value] ?? null)
                            <td>
                                @if ($n)
                                    <span class="badge {{ $n->ampel() }}" title="Status: {{ $n->status() }}">{{ $n->datum->format('d.m.Y') }}</span>
                                    @if ($n->faelligAm())<br><span class="muted" style="font-size:.78em">fällig {{ $n->faelligAm()->format('d.m.Y') }}</span>@endif
                                @else
                                    <span class="badge {{ $t->intervallMonate() ? 'red' : 'gray' }}">{{ $t->intervallMonate() ? 'fehlt' : '–' }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($typen) + 1 }}"><p class="empty">Keine Mitarbeitenden mit Personalakte.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>Nachweis erfassen</h3></div>
        <form wire:submit="erfassen">
            <div class="form-row-3">
                <div class="field"><label>Mitarbeiter:in</label>
                    <select wire:model="erf_user"><option value="">– wählen –</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                    @error('erf_user')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Nachweis-Typ</label><select wire:model="erf_typ">@foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Datum</label><input type="date" wire:model="erf_datum" />@error('erf_datum')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-row-2">
                <div class="field"><label>Intervall (Monate, optional — sonst Standard)</label><input type="number" wire:model="erf_intervall" placeholder="Standard je Typ" />@error('erf_intervall')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Notiz</label><input type="text" wire:model="erf_notiz" placeholder="z. B. Träger-Schulung, Anbieter" /></div>
            </div>
            <button class="btn btn-primary btn-sm">+ Nachweis</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Betriebsärztliche & sicherheitstechnische Betreuung</h3><span class="badge gray">ASiG / DGUV V2</span></div>
        <table class="data">
            <thead><tr><th>Art / Basis</th><th>Person / Firma</th><th>Kontakt</th><th>Einsatzzeit</th><th>Nächste Begehung</th><th></th></tr></thead>
            <tbody>
                @forelse ($betreuungen as $bb)
                    <tr>
                        <td><b>{{ $bb->art->label() }}</b><br><span class="muted" style="font-size:.8em">{{ $bb->art->rechtsbasis() }}</span></td>
                        <td>{{ $bb->name }}@if ($bb->firma)<br><span class="muted">{{ $bb->firma }} · {{ $bb->extern ? 'extern' : 'intern' }}</span>@endif</td>
                        <td>{{ $bb->telefon ?? '' }}@if ($bb->email)<br><span class="muted">{{ $bb->email }}</span>@endif</td>
                        <td>{{ $bb->einsatzzeit_stunden ? $bb->einsatzzeit_stunden.' h/Jahr' : '—' }}</td>
                        <td>
                            @php($n = $bb->naechsteBegehung())
                            @if ($n)<span class="badge {{ $bb->ampel() }}">{{ $n->format('d.m.Y') }}</span>
                            @else<span class="badge {{ $bb->ampel() }}">{{ $bb->begehung_intervall_monate ? 'offen' : '—' }}</span>@endif
                            <div style="display:flex;gap:4px;margin-top:4px"><input type="date" wire:model="beg_datum" style="max-width:140px" /><button class="btn btn-ghost btn-sm" wire:click="begehungDokumentieren({{ $bb->id }})">Begehung</button></div>
                        </td>
                        <td><button class="btn btn-ghost btn-sm" wire:click="betreuungLoeschen({{ $bb->id }})" wire:confirm="Eintrag entfernen?">×</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Noch keine Betreuung hinterlegt — Bestellung von Betriebsarzt und Sifa ist ab 1 Beschäftigtem Pflicht (ASiG).</td></tr>
                @endforelse
            </tbody>
        </table>

        <form wire:submit="betreuungAnlegen" style="margin-top:12px">
            <div class="form-row-3">
                <div class="field"><label>Art</label><select wire:model="bb_art">@foreach ($betreuungsArten as $a)<option value="{{ $a->value }}">{{ $a->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Name *</label><input type="text" wire:model="bb_name" placeholder="Dr. … / Name der Sifa" />@error('bb_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Firma / Dienst</label><input type="text" wire:model="bb_firma" placeholder="z. B. überbetrieblicher Dienst" /></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Telefon</label><input type="text" wire:model="bb_telefon" /></div>
                <div class="field"><label>E-Mail</label><input type="email" wire:model="bb_email" />@error('bb_email')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Bestellt am</label><input type="date" wire:model="bb_bestellt_am" /></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Einsatzzeit (h/Jahr, DGUV V2)</label><input type="number" wire:model="bb_einsatzzeit" />@error('bb_einsatzzeit')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Begehungsintervall (Monate)</label><input type="number" wire:model="bb_intervall" />@error('bb_intervall')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>extern?</label><label style="font-weight:400"><input type="checkbox" wire:model="bb_extern" /> externer Dienst</label></div>
            </div>
            <button class="btn btn-ghost btn-sm">+ Betreuung hinterlegen</button>
        </form>
    </div>
</div>
