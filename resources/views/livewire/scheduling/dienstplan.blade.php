<div>
    <div class="page-head"><div><p class="kicker">Planung</p><h1>Dienstplan</h1>
        <p class="lead">Schichten den Mitarbeitenden zuweisen.</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Dienst eintragen</h3></div>
        <form wire:submit="zuweisen">
            <div class="form-row">
                <div class="field"><label>Datum</label><input type="date" wire:model.live="dienstAm" /></div>
                <div class="field"><label>Mitarbeiter:in</label>
                    <select wire:model="userId">
                        <option value="">– wählen –</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>@error('userId')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Schicht</label>
                    <select wire:model="shiftId">
                        <option value="">– wählen –</option>
                        @foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->beginn }}–{{ $s->ende }})</option>@endforeach
                    </select>@error('shiftId')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary">Eintragen</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>Dienste am {{ $dienstAm }}</h3></div>
        <table class="data"><thead><tr><th>Schicht</th><th>Mitarbeiter:in</th><th></th></tr></thead>
            <tbody>
                @forelse ($eintraege as $e)
                    <tr>
                        <td><b>{{ $e->shift?->name }}</b></td>
                        <td>{{ $e->user?->name }}</td>
                        <td><button class="btn btn-link" wire:click="entfernen({{ $e->id }})">Entfernen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Keine Dienste eingetragen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
