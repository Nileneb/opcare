<div>
    <div class="page-head">
        <div>
            <p class="kicker">Qualitätsmanagement</p>
            <h1>Qualitätsbericht</h1>
            <p class="lead">Inzidenz-Auswertung aller Qualitätsindikatoren für einen definierten Zeitraum.</p>
        </div>
        <div class="btn-row">
            <a href="{{ route('controlling') }}" class="btn btn-ghost" wire:navigate>← Controlling</a>
        </div>
    </div>

    <div class="card" style="margin-bottom:var(--space-5)">
        <div class="card-head"><h3>Filter</h3></div>
        <div style="display:flex;gap:var(--space-3);flex-wrap:wrap;align-items:flex-end;padding:var(--space-2) 0">
            <div>
                <label style="display:block;font-size:.85rem;color:var(--c-muted);margin-bottom:var(--space-1)">Stichtag (Kohorte)</label>
                <input type="date" wire:model="stichtag" class="input" />
            </div>
            <div>
                <label style="display:block;font-size:.85rem;color:var(--c-muted);margin-bottom:var(--space-1)">Von</label>
                <input type="date" wire:model="von" class="input" />
            </div>
            <div>
                <label style="display:block;font-size:.85rem;color:var(--c-muted);margin-bottom:var(--space-1)">Bis</label>
                <input type="date" wire:model="bis" class="input" />
            </div>
            <button wire:click="berechnen" class="btn btn-primary">Berechnen</button>
        </div>
    </div>

    @if (!empty($ergebnisse))
        <div class="card">
            <div class="card-head">
                <h3>Ergebnisse</h3>
                <span style="font-size:.85rem;color:var(--c-muted)">Kohorte: {{ $kohorte }} Bewohner:innen am Stichtag</span>
            </div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Indikator</th>
                        <th style="text-align:right">Betroffene</th>
                        <th style="text-align:right">Quote&nbsp;%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ergebnisse as $e)
                        <tr>
                            <td>{{ \App\Domains\Quality\Enums\QualityIndicator::from($e['indicator'])->label() }}</td>
                            <td style="text-align:right">{{ $e['betroffene'] }}</td>
                            <td style="text-align:right">
                                @if ($e['betroffene'] > 0)
                                    <span class="badge badge-warn">{{ $e['quote'] }}&thinsp;%</span>
                                @else
                                    <span class="badge">{{ $e['quote'] }}&thinsp;%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card">
            <p class="empty">Noch keine Auswertung. Filter setzen und „Berechnen" klicken.</p>
        </div>
    @endif
</div>
