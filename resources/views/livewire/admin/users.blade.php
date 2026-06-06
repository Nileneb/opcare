<div>
    <div class="page-head"><div><p class="kicker">Verwaltung</p><h1>Mitarbeitende</h1>
        <p class="lead">Anlegen, Rollen vergeben und die vollständige Personalakte pflegen.</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    <div class="card">
        <div class="card-head"><h3>Neuer Mitarbeitender</h3></div>
        <form wire:submit="save">
            <div class="form-row">
                <div class="field"><label>Name</label><input wire:model="name" />@error('name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>E-Mail</label><input wire:model="email" type="email" />@error('email')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-row">
                <div class="field"><label>Passwort</label><input wire:model="password" type="password" />@error('password')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Rolle</label>
                    <select wire:model="role">
                        @foreach ($roles as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                    </select>
                    @error('role')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary">Anlegen</button>
        </form>
    </div>
    <div class="card"><table class="data"><thead><tr><th>Name</th><th>E-Mail</th><th>Qualifikation</th><th>Pensum</th><th>Rolle</th><th></th></tr></thead>
        <tbody>@foreach ($users as $u)<tr>
            <td><b>{{ $u->name }}</b></td>
            <td>{{ $u->email }}</td>
            <td>{{ $u->employeeProfile?->qualifikation?->label() ?? '—' }}</td>
            <td>{{ $u->employeeProfile?->wochenstunden ? $u->employeeProfile->wochenstunden.' h' : '—' }}</td>
            <td>
                <select wire:change="setRole({{ $u->id }}, $event.target.value)">
                    @foreach ($roles as $r)<option value="{{ $r }}" @selected($u->hasRole($r))>{{ $r }}</option>@endforeach
                </select>
            </td>
            <td><a href="{{ route('personnel.akte', $u) }}" class="btn btn-ghost btn-sm" wire:navigate>Personalakte</a></td>
        </tr>@endforeach</tbody>
    </table></div>
</div>
