<div>
    <div class="page-head"><div><p class="kicker">Assessment</p><h1>Risiko-Assessments</h1>
        <p class="lead">{{ $resident->name }}</p></div></div>

    <div class="card">
        <div class="card-head"><h3>Neues Assessment durchführen</h3></div>
        <div class="btn-row">
            @foreach ($instrumente as $instr)
                <a class="btn" href="{{ route('assessment.durchfuehren', [$resident, $instr]) }}" wire:navigate>{{ $instr->name }}</a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Aktuelle Einstufung</h3></div>
        <table class="data"><thead><tr><th>Instrument</th><th>Score</th><th>Risiko</th><th>Durchgeführt</th><th>Fällig</th></tr></thead>
            <tbody>
                @forelse ($aktuelle as $a)
                    <tr @class(['row-warn' => $a->risk_band?->istKritisch()])>
                        <td><b>{{ $a->instrument?->name }}</b></td>
                        <td>{{ $a->score }}</td>
                        <td>{{ $a->risk_band?->label() }}</td>
                        <td>{{ optional($a->durchgefuehrt_am)->format('d.m.Y') }}</td>
                        <td @class(['err' => $a->istFaellig()])>{{ optional($a->faellig_am)->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch keine Assessments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>Verlauf</h3></div>
        <table class="data"><tbody>
            @foreach ($historie as $a)
                <tr>
                    <td>{{ optional($a->durchgefuehrt_am)->format('d.m.Y') }}</td>
                    <td>{{ $a->instrument?->name }} (v{{ $a->version }})</td>
                    <td>Score {{ $a->score }} — {{ $a->risk_band?->label() }}</td>
                </tr>
            @endforeach
        </tbody></table>
    </div>
</div>
