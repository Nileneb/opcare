<div>
    <div class="page-head">
        <div><p class="kicker">Planung · Wünsche</p><h1>Mein Wunschdienstplan</h1>
            <p class="lead">Dienstwünsche hinterlegen — die Planung sieht sie (Vorschlag, nicht bindend).</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Meine Woche</h3>
            <div class="plan-nav" style="margin:0">
                <button class="btn btn-ghost btn-sm" wire:click="woche(-1)">← Woche</button>
                <button class="btn btn-ghost btn-sm" wire:click="$set('weekStart', '{{ \Carbon\CarbonImmutable::parse(today())->startOfWeek()->toDateString() }}')">Heute</button>
                <button class="btn btn-ghost btn-sm" wire:click="woche(1)">Woche →</button>
                <b style="margin-left:6px">{{ $weekLabel }}</b>
            </div>
        </div>
        <table class="data">
            <thead><tr><th>Tag</th><th>Wunsch</th><th>Notiz</th></tr></thead>
            <tbody>
                @foreach ($days as $day)
                    <tr @class(['plan-sun' => $day['sonntag']])>
                        <td><b>{{ $day['label'] }}</b></td>
                        <td>
                            <select wire:model="w.{{ $day['datum'] }}.typ">
                                <option value="">– kein Wunsch –</option>
                                @foreach ($typen as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                            </select>
                        </td>
                        <td><input type="text" wire:model="w.{{ $day['datum'] }}.notiz" placeholder="optional (z. B. Arzttermin)" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <button class="btn btn-primary btn-sm" wire:click="speichern" style="margin-top:12px">Wünsche speichern</button>
    </div>
</div>
