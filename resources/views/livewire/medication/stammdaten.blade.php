<div>
    <div class="page-head"><div><p class="kicker">Medikation</p><h1>Medikationsstamm</h1>
        <p class="lead">Präparate für Verordnungen pflegen.</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Neues Produkt</h3></div>
        <form wire:submit="speichern">
            <div class="form-row">
                <div class="field"><label>Handelsname</label><input wire:model="name" />@error('name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Wirkstoff</label><input wire:model="wirkstoff" /></div>
                <div class="field"><label>Stärke</label><input wire:model="staerke" placeholder="z. B. 400 mg" /></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Darreichungsform</label>
                    <select wire:model="tradeFormId">
                        <option value="">– wählen –</option>
                        @foreach ($tradeForms as $tf)<option value="{{ $tf->id }}">{{ $tf->name }} ({{ $tf->einheit }})</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>ATC</label><input wire:model="atcCode" /></div>
                <div class="field"><label>PZN</label><input wire:model="pzn" /></div>
                <div class="field check"><label><input type="checkbox" wire:model="btm" /> Betäubungsmittel (BtM)</label></div>
            </div>
            <button class="btn btn-primary">Anlegen</button>
        </form>
    </div>

    <div class="card">
        <table class="data"><thead><tr><th>Name</th><th>Wirkstoff</th><th>Stärke</th><th>Form</th><th>BtM</th></tr></thead>
            <tbody>
                @forelse ($produkte as $p)
                    <tr>
                        <td><b>{{ $p->name }}</b></td>
                        <td>{{ $p->wirkstoff }}</td>
                        <td>{{ $p->staerke }}</td>
                        <td>{{ $p->tradeForm?->name }}</td>
                        <td>@if ($p->btm)<span class="badge badge-warn">BtM</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch keine Produkte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
