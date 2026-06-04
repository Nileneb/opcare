<div>
    <div class="page-head">
        <div>
            <p class="kicker">Qualitätsmanagement</p>
            <h1>Controlling</h1>
            <p class="lead">KPIs, Pflegegrad-Verteilung und Qualitätsindikatoren auf einen Blick.</p>
        </div>
        <div class="btn-row">
            <a href="{{ route('quality.report') }}" class="btn btn-primary" wire:navigate>Qualitätsbericht</a>
        </div>
    </div>

    <div class="grid-4" style="margin-bottom:var(--space-5)">
        <div class="stat">
            <div class="n">{{ $kpi->bewohnerAktiv }}</div>
            <div class="l">Aktive Bewohner:innen</div>
        </div>
        <div class="stat">
            <div class="n">{{ $kpi->auslastung() }}&thinsp;%</div>
            <div class="l">Belegung</div>
        </div>
        <div class="stat">
            <div class="n">{{ $kpi->belegt }}</div>
            <div class="l">Belegte Betten</div>
        </div>
        <div class="stat">
            <div class="n">{{ $kpi->betten }}</div>
            <div class="l">Betten gesamt</div>
        </div>
    </div>

    <div class="grid-2" style="margin-bottom:var(--space-5)">
        <div class="card">
            <div class="card-head"><h3>Pflegegrad-Verteilung</h3></div>
            @if (empty($kpi->pflegegradVerteilung))
                <p class="empty">Keine aktiven Bewohner.</p>
            @else
                @php $max = max($kpi->pflegegradVerteilung); @endphp
                <div style="display:flex;flex-direction:column;gap:var(--space-2);padding:var(--space-3) 0">
                    @foreach (range(1,5) as $pg)
                        @php $n = $kpi->pflegegradVerteilung[$pg] ?? 0; @endphp
                        <div style="display:flex;align-items:center;gap:var(--space-2)">
                            <span style="min-width:2.5rem;font-size:.85rem;color:var(--c-muted)">PG&nbsp;{{ $pg }}</span>
                            <div style="flex:1;background:var(--c-border);border-radius:var(--radius-sm);height:.75rem;overflow:hidden">
                                <div style="width:{{ $max > 0 ? round($n / $max * 100) : 0 }}%;height:100%;background:var(--c-primary);transition:width .3s"></div>
                            </div>
                            <span style="min-width:1.5rem;font-size:.85rem;font-weight:600">{{ $n }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head">
                <h3>Qualitätsindikatoren</h3>
                <span style="font-size:.8rem;color:var(--c-muted)">Letzte 3 Monate</span>
            </div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Indikator</th>
                        <th style="text-align:right">Betroffene</th>
                        <th style="text-align:right">Kohorte</th>
                        <th style="text-align:right">Quote</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($incidences as $r)
                        <tr>
                            <td>{{ \App\Domains\Quality\Enums\QualityIndicator::from($r->indicator)->label() }}</td>
                            <td style="text-align:right">{{ $r->betroffene }}</td>
                            <td style="text-align:right">{{ $r->kohorte }}</td>
                            <td style="text-align:right">
                                @if ($r->betroffene > 0)
                                    <span class="badge badge-warn">{{ $r->quote() }}&thinsp;%</span>
                                @else
                                    <span class="badge">{{ $r->quote() }}&thinsp;%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
