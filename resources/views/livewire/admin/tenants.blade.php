<div>
    <div class="page-head"><div><p class="kicker">Verwaltung</p><h1>Einrichtungen</h1>
        <p class="lead">Heime/Mandanten anlegen und pflegen (nur Super-Admin).</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    <div class="card">
        <div class="card-head"><h3>Neue Einrichtung</h3></div>
        <form wire:submit="save">
            <div class="form-row">
                <div class="field"><label>Name</label><input wire:model="name" />@error('name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Kürzel (slug)</label><input wire:model="slug" />@error('slug')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="form-row">
                <div class="field"><label>Träger</label><input wire:model="traeger" /></div>
                <div class="field"><label>IK-Nummer</label><input wire:model="ik_nummer" /></div>
            </div>
            <button class="btn btn-primary">Anlegen</button>
        </form>
    </div>
    <div class="card"><table class="data"><thead><tr><th>Name</th><th>Träger</th><th>IK</th><th>Status</th></tr></thead>
        <tbody>@foreach ($tenants as $t)<tr><td><b>{{ $t->name }}</b></td><td>{{ $t->traeger }}</td><td>{{ $t->ik_nummer }}</td>
            <td><span class="badge {{ $t->aktiv ? 'green' : 'gray' }}">{{ $t->aktiv ? 'aktiv' : 'inaktiv' }}</span></td></tr>@endforeach</tbody>
    </table></div>
</div>
