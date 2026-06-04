<div>
    <div class="page-head"><div><p class="kicker">Planung</p><h1>Kalender</h1>
        <p class="lead">Termine und wiederkehrende Maßnahmen.</p></div>
        <div class="field"><label>Monat</label><input type="month" wire:model.live="monat" /></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Neuer Termin</h3></div>
        <form wire:submit="speichern">
            <div class="form-row">
                <div class="field"><label>Art</label>
                    <select wire:model="type">
                        @foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>Titel</label><input wire:model="titel" />@error('titel')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-row">
                <div class="field"><label>Beginn</label><input type="datetime-local" wire:model="beginntAm" />@error('beginntAm')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Ende</label><input type="datetime-local" wire:model="endetAm" />@error('endetAm')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Wiederholung</label>
                    <select wire:model="wiederholung">
                        <option value="">einmalig</option>
                        <option value="daily">täglich</option>
                        <option value="weekly">wöchentlich</option>
                        <option value="monthly">monatlich</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary">Speichern</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Termine im {{ $monat }}</h3></div>
        <table class="data"><thead><tr><th>Wann</th><th>Art</th><th>Titel</th></tr></thead>
            <tbody>
                @forelse ($vorkommen as $v)
                    <tr>
                        <td>{{ $v['zeitpunkt']->format('d.m.Y H:i') }}</td>
                        <td>{{ $v['type']->label() }}</td>
                        <td><b>{{ $v['titel'] }}</b></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Keine Termine in diesem Monat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
