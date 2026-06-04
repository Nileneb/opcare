<div>
    <div class="page-head">
        <div>
            <p class="kicker">Stammdaten</p>
            <h1>Bewohner:innen</h1>
            <p class="lead">Alle Bewohner:innen des Wohnbereichs — anlegen und öffnen für Diagnosen, Kassen, SIS-Planung.</p>
        </div>
        <button class="btn btn-primary" wire:click="$toggle('showForm')">
            {{ $showForm ? 'Abbrechen' : '+ Neue:r Bewohner:in' }}
        </button>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    @if ($showForm)
        <div class="card">
            <div class="card-head"><h3>Neue:r Bewohner:in</h3></div>
            <form wire:submit="save">
                <div class="form-row">
                    <div class="field">
                        <label>Name</label>
                        <input type="text" wire:model="name" required />
                        @error('name') <span class="err">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label>Zimmer</label>
                        <select wire:model="room_id">
                            <option value="">— kein Zimmer —</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}">Zimmer {{ $room->nummer }} ({{ $room->station?->name }})</option>
                            @endforeach
                        </select>
                        @error('room_id') <span class="err">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="field">
                        <label>Geburtsdatum</label>
                        <input type="date" wire:model="geburtsdatum" required />
                        @error('geburtsdatum') <span class="err">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label>Geschlecht</label>
                        <select wire:model="geschlecht">
                            <option value="w">weiblich</option>
                            <option value="m">männlich</option>
                            <option value="d">divers</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Pflegegrad</label>
                        <select wire:model="pflegegrad">
                            <option value="">—</option>
                            @for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                        </select>
                        @error('pflegegrad') <span class="err">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="field" style="max-width:240px">
                    <label>Aufnahme am</label>
                    <input type="date" wire:model="aufnahme_am" required />
                    @error('aufnahme_am') <span class="err">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Anlegen</button>
            </form>
        </div>
    @endif

    <div class="card">
        @if ($residents->isEmpty())
            <p class="empty">Noch keine Bewohner:innen angelegt.</p>
        @else
            <table class="data">
                <thead><tr><th>Name</th><th>Zimmer</th><th>Pflegegrad</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($residents as $r)
                        <tr class="clickable" onclick="window.location='{{ route('bewohner.show', $r) }}'">
                            <td><b>{{ $r->name }}</b></td>
                            <td>{{ $r->room?->nummer ?? '—' }}</td>
                            <td>{{ $r->pflegegrad ?? '—' }}</td>
                            <td><span class="badge green">{{ ucfirst($r->status) }}</span></td>
                            <td style="text-align:right"><a href="{{ route('bewohner.show', $r) }}" class="btn btn-ghost btn-sm" wire:navigate>Öffnen</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
