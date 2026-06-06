<div>
    <div class="page-head">
        <div><p class="kicker">Personal · Pflichten</p><h1>Beauftragten-Register</h1>
            <p class="lead">Benannte, qualifizierte Personen je Pflicht-Rolle — mit Fälligkeits-Ampel und Lücken-Hinweis.</p></div>
        <div style="display:flex;gap:6px;align-self:center">
            @if ($unbesetztePflicht > 0)<span class="badge red">{{ $unbesetztePflicht }} Pflicht-Rolle(n) unbesetzt</span>@endif
            @if ($ueberfaellig > 0)<span class="badge amber">{{ $ueberfaellig }} überfällig</span>@endif
            @if ($unbesetztePflicht === 0 && $ueberfaellig === 0)<span class="badge green">vollständig besetzt</span>@endif
        </div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card" style="overflow-x:auto">
        <table class="data-table">
            <thead><tr><th>Rolle</th><th>Bereich</th><th>Rechtsbasis / Schwelle</th><th>Benannte Person(en)</th></tr></thead>
            <tbody>
                @foreach ($rollen as $r)
                    <tr @class(['row-warn' => $r->pflicht && $r->bestellungen->isEmpty()])>
                        <td><b>{{ $r->name }}</b>@if ($r->pflicht)<span class="badge gray" title="Pflicht">Pflicht</span>@endif</td>
                        <td>{{ ucfirst($r->bereich) }}</td>
                        <td class="muted" style="font-size:.82em">{{ $r->rechtsbasis }}@if ($r->schwelle)<br>{{ $r->schwelle }}@endif @if ($r->auffrischung_monate)<br>↻ {{ $r->auffrischung_monate }} Mon.@endif</td>
                        <td>
                            @forelse ($r->bestellungen as $b)
                                <span class="badge {{ $b->ampel() }}" title="{{ $b->status() }}">●</span> {{ $b->user?->name }}
                                @if ($b->gueltig_bis)<span class="muted" style="font-size:.8em">bis {{ $b->gueltig_bis->format('d.m.Y') }}</span>@endif
                                <button class="btn btn-ghost btn-sm" wire:click="abbestellen({{ $b->id }})" wire:confirm="Abbestellen?">✕</button><br>
                            @empty
                                <span class="badge {{ $r->pflicht ? 'red' : 'gray' }}">{{ $r->pflicht ? 'unbesetzt' : '–' }}</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>Person bestellen</h3></div>
        <form wire:submit="bestellen">
            <div class="form-row-3">
                <div class="field"><label>Rolle</label>
                    <select wire:model="b_rolle"><option value="">– wählen –</option>@foreach ($rollen as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
                    @error('b_rolle')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Person</label>
                    <select wire:model="b_user"><option value="">– wählen –</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                    @error('b_user')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Bestellt am</label><input type="date" wire:model="b_datum" />@error('b_datum')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <button class="btn btn-primary btn-sm">+ Bestellung</button>
        </form>
    </div>
</div>
