<div>
    <div class="page-head"><div><p class="kicker">Medikation</p><h1>Vitalwerte</h1><p class="lead">{{ $resident->name }}</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <form wire:submit="erfassen">
            <div class="form-row">
                <div class="field"><label>Messung</label>
                    <select wire:model.live="typ">
                        @foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }} ({{ $t->einheit() }})</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>Wert</label>
                    <input type="number" step="0.1" wire:model="wert" />
                    @error('wert')<span class="err">{{ $message }}</span>@enderror
                </div>
                @if ($typ === 'blutdruck')
                    <div class="field"><label>Diastolisch (2. Wert)</label>
                        <input type="number" step="1" wire:model="wert2" placeholder="z. B. 80" />
                    </div>
                @endif
            </div>
            <x-voice-field model="notiz" label="Notiz" :rows="1" />
            <button class="btn btn-primary">Erfassen</button>
        </form>
    </div>

    <div class="card">
        <table class="data"><thead><tr><th>Wann</th><th>Messung</th><th>Wert</th></tr></thead>
            <tbody>
                @forelse ($messungen as $m)
                    <tr>
                        <td>{{ optional($m->gemessen_am)->format('d.m.Y H:i') }}</td>
                        <td>{{ $m->typ instanceof \BackedEnum ? $m->typ->label() : $m->typ }}</td>
                        <td>
                            <b>{{ $m->wert }}@if ($m->wert2)/{{ $m->wert2 }}@endif</b>
                            {{ $m->einheit }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Noch keine Messungen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
