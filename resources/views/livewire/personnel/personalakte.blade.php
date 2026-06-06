<div>
    <div class="page-head">
        <div><p class="kicker">Verwaltung · Personal</p><h1>{{ $employee->name }}</h1>
            <p class="lead">Personalakte — {{ $employee->email }}</p></div>
        <div><a href="{{ route('admin.users') }}" class="btn btn-ghost" wire:navigate>← Mitarbeitende</a></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Rolle &amp; Zugriff</h3>
            <span class="badge gray">Rollenverwaltung</span>
        </div>
        <div class="form-row">
            <div class="field"><label>Rolle</label>
                <select wire:model="role" wire:change="setRole">
                    @foreach ($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                </select>
            </div>
        </div>
    </div>

    <form wire:submit="speichern">
        @foreach ($sections as $title => $felder)
            <div class="card">
                <div class="card-head"><h3>{{ $title }}</h3></div>
                <div class="form-grid">
                    @foreach ($felder as $key => [$label, $type])
                        @php $isEnum = is_string($type) && enum_exists($type); @endphp
                        <div class="field">
                            <label>{{ $label }}</label>
                            @if ($type === 'bool')
                                <label style="font-weight:400"><input type="checkbox" wire:model="f.{{ $key }}" /> ja</label>
                            @elseif ($isEnum)
                                <select wire:model="f.{{ $key }}">
                                    <option value="">– keine Angabe –</option>
                                    @foreach ($type::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                                </select>
                            @elseif ($type === 'date')
                                <input type="date" wire:model="f.{{ $key }}" />
                            @elseif ($type === 'number')
                                <input type="number" step="0.5" wire:model="f.{{ $key }}" />
                            @else
                                <input type="text" wire:model="f.{{ $key }}" />
                            @endif
                            @error("f.{$key}")<span class="err">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="card" style="position:sticky;bottom:0">
            <button class="btn btn-primary">Personalakte speichern</button>
            <span class="muted" style="margin-left:10px;font-size:.85em">Steuer-ID, SV-Nummer und IBAN werden verschlüsselt gespeichert.</span>
        </div>
    </form>
</div>
