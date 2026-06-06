<div>
    <div class="page-head">
        <div><p class="kicker">Planung</p><h1>Dienstplan</h1>
            <p class="lead">Wochenplan mit arbeitsrechtlicher Live-Prüfung (ArbZG).</p></div>
        <div style="display:flex;gap:8px;align-items:center">
            <a href="{{ route('arbeitsrecht') }}" class="btn btn-ghost" wire:navigate>§ Arbeitsrecht-Regeln</a>
        </div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="plan-nav">
        <button class="btn btn-ghost btn-sm" wire:click="woche(-1)">← Woche</button>
        <button class="btn btn-ghost btn-sm" wire:click="heute">Heute</button>
        <button class="btn btn-ghost btn-sm" wire:click="woche(1)">Woche →</button>
        <b style="margin-left:6px">{{ $weekLabel }}</b>
        @if ($offeneVerstoesse > 0)
            <span class="badge red" style="margin-left:auto" title="offene, unbegründete Verstöße/Warnungen">{{ $offeneVerstoesse }} offen</span>
        @else
            <span class="badge green" style="margin-left:auto">arbeitsrechtlich o. B.</span>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h3>Betreuungsschlüssel (§ 113c SGB XI)</h3>
            <span class="badge gray" title="Personalbemessung aus dem Pflegegrad-Mix (PeBeM)">PeBeM</span>
        </div>
        <div class="form-row-3" style="gap:18px">
            <div>
                <p class="kicker">Pflegegrad-Mix (aktiv)</p>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    @foreach ($staffing->pgCounts as $pg => $n)<span class="badge gray">PG{{ $pg }}: {{ $n }}</span>@endforeach
                </div>
                <p class="muted" style="margin-top:6px;font-size:.85em">Soll: {{ number_format($staffing->sollVzaeGesamt, 2, ',', '.') }} VZÄ gesamt · {{ number_format($staffing->sollVzaeFachkraft, 2, ',', '.') }} VZÄ Fachkraft</p>
            </div>
            <div>
                <p class="kicker">Gesamtbesetzung (Wochenstunden)</p>
                <b style="font-size:1.3em">{{ number_format($staffing->istWochenstundenGesamt, 0, ',', '.') }}</b> / {{ number_format($staffing->sollWochenstundenGesamt, 0, ',', '.') }} h
                <span class="badge {{ $staffing->ampelGesamt() === 'gruen' ? 'green' : ($staffing->ampelGesamt() === 'gelb' ? 'amber' : 'red') }}">{{ $staffing->deckungGesamt() }} %</span>
            </div>
            <div>
                <p class="kicker">davon Fachkraft</p>
                <b style="font-size:1.3em">{{ number_format($staffing->istWochenstundenFachkraft, 0, ',', '.') }}</b> / {{ number_format($staffing->sollWochenstundenFachkraft, 0, ',', '.') }} h
                <span class="badge {{ $staffing->ampelFachkraft() === 'gruen' ? 'green' : ($staffing->ampelFachkraft() === 'gelb' ? 'amber' : 'red') }}">{{ $staffing->deckungFachkraft() }} %</span>
            </div>
        </div>
        <p class="muted" style="margin-top:10px;font-size:.85em">Soll = Pflegegrad-Mix × Personalanhaltswerte (§ 113c, bundeseinheitlich) × Multiplikator,
            umgerechnet auf Wochenstunden. Tarif-Wochenstunden, Fachkraftquote und Multiplikator sind unter
            <a href="{{ route('arbeitsrecht') }}" wire:navigate>Regeln</a> je Einrichtung einstellbar.</p>
    </div>

    <div class="card" style="overflow-x:auto">
        <table class="plan">
            <thead>
                <tr>
                    <th class="plan-name">Mitarbeiter:in</th>
                    @foreach ($days as $day)
                        <th @class(['plan-sun' => $day['sonntag'], 'plan-today' => $day['heute']])>
                            {{ $day['kurz'] }}<br><span class="plan-date">{{ $day['tag'] }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        @php
                            $soll = $user->employeeProfile?->wochenstunden;
                            $ist = $geplant[$user->id] ?? 0;
                            $fmt = fn ($n) => rtrim(rtrim(number_format($n, 1, ',', ''), '0'), ',');
                            $sollLabel = $fmt($ist).' h'.($soll ? ' / '.$fmt($soll).' h Pensum' : '');
                            $over = $soll && $ist > $soll;
                        @endphp
                        <td class="plan-name">{{ $user->name }}
                            <span class="plan-soll {{ $over ? 'plan-soll-over' : '' }}">{{ $sollLabel }}</span>
                        </td>
                        @foreach ($days as $day)
                            <td @class(['plan-cell', 'plan-sun' => $day['sonntag'], 'plan-warn' => isset($marks[$user->id][$day['datum']])])>
                                @php $wunsch = $wuensche[$user->id][$day['datum']] ?? null; @endphp
                                @if ($wunsch)
                                    <span class="badge {{ $wunsch->typ->badge() }}" title="Wunsch: {{ $wunsch->typ->label() }}{{ $wunsch->notiz ? ' — '.$wunsch->notiz : '' }}" style="display:block;margin-bottom:2px;font-size:.68em">{{ $wunsch->typ->kurz() }}</span>
                                @endif
                                @foreach ($grid[$user->id][$day['datum']] ?? [] as $e)
                                    <span class="plan-shift" title="{{ $e->shift?->beginn }}–{{ $e->shift?->ende }}">
                                        {{ $e->shift?->name }}
                                        <button wire:click="entfernen({{ $e->id }})" title="Entfernen">×</button>
                                    </span>
                                @endforeach
                                @if ($pickUser === $user->id && $pickDatum === $day['datum'])
                                    <select class="plan-pick" wire:change="zuweisen($event.target.value)">
                                        <option value="">– Schicht –</option>
                                        @foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                    </select>
                                @else
                                    <button class="plan-add" wire:click="pick({{ $user->id }}, '{{ $day['datum'] }}')" title="Dienst zuweisen">+</button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Keine Mitarbeitenden im Mandanten.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h3>Arbeitszeit-Konformität (Woche)</h3>
            <span class="badge gray" title="Editierbares ArbZG-Regelwerk">ArbZG</span>
        </div>
        @forelse ($findingsByUser as $userId => $findings)
            <div class="plan-findings">
                <b>{{ $findings[0]->userName }}</b>
                @foreach ($findings as $f)
                    @php $datum = $f->dates[0] ?? null; $istTag = $datum && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum); @endphp
                    <div class="plan-finding">
                        <span class="badge {{ $f->severity->badge() }}">{{ $f->severity->label() }}</span>
                        <span>{{ $f->message }}</span>
                        <a href="{{ $f->gesetzUrl }}" target="_blank" rel="noopener" class="plan-law">{{ $f->paragraph }} ↗</a>
                        @if ($f->istBegruendet())
                            <span class="badge gray" title="dokumentierte § 14-Begründung">begründet</span>
                            <span class="muted">— {{ $f->begruendung }} ({{ $f->begruendetVon }})</span>
                        @elseif ($f->offenerVerstoss() && $istTag)
                            <button class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="begruendeStart('{{ $f->ruleKey }}', {{ $f->userId }}, '{{ $datum }}')">Begründen (§ 14)</button>
                        @endif
                    </div>
                    @if ($begruendeKey === $f->ruleKey.'|'.$f->userId.'|'.$datum)
                        <div class="plan-begruenden">
                            <textarea wire:model="grund" rows="2" placeholder="Zwingender Grund nach § 14 ArbZG, z. B. „Nachfolgekraft nicht erschienen, Bewohner durften nicht unbeaufsichtigt bleiben.&quot;"></textarea>
                            @error('grund')<span class="err">{{ $message }}</span>@enderror
                            <div style="display:flex;gap:8px;margin-top:6px">
                                <button class="btn btn-primary btn-sm" wire:click="begruendeSpeichern">Begründung speichern</button>
                                <button class="btn btn-ghost btn-sm" wire:click="begruendeAbbrechen">Abbrechen</button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @empty
            <p class="empty">Keine arbeitsrechtlichen Auffälligkeiten in dieser Woche.</p>
        @endforelse
        <p class="muted" style="margin-top:12px;font-size:.85em">Schwellwerte &amp; Schwere sind unter
            <a href="{{ route('arbeitsrecht') }}" wire:navigate>§ Arbeitsrecht-Regeln</a> editierbar. § 4 (Pausen) wird
            mangels Pausen-Erfassung als „nicht prüfbar" geführt; die Wochenprüfung betrachtet die angezeigte Woche.</p>
    </div>

    <div class="card">
        <div class="card-head"><h3>Ergonomie-Empfehlungen (Schichtgestaltung)</h3>
            <span class="badge gray" title="Arbeitswissenschaftliche Empfehlungen — § 6 ArbZG / BAuA / BGHM">freiwillig</span>
        </div>
        @forelse ($qualityByUser as $userId => $findings)
            <div class="plan-findings">
                <b>{{ $findings[0]->userName }}</b>
                @foreach ($findings as $qf)
                    <div class="plan-finding">
                        <span class="badge {{ $qf->severity->badge() }}">{{ $qf->severity->label() }}</span>
                        <span><b>{{ $qf->label }}:</b> {{ $qf->message }}</span>
                        <span class="muted" style="margin-left:auto;font-size:.85em" title="{{ $qf->quelle }}">ⓘ</span>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="empty">Keine Ergonomie-Hinweise — die Schichtfolge entspricht den Empfehlungen.</p>
        @endforelse
        <p class="muted" style="margin-top:12px;font-size:.85em">Bewusst <b>Empfehlungen</b> (Warnung/Hinweis), der harten
            ArbZG-Prüfung nachgelagert (§ 6 ArbZG: gesicherte arbeitswissenschaftliche Erkenntnisse). Regeln &amp;
            Schwellwerte sind unter <a href="{{ route('arbeitsrecht') }}" wire:navigate>Regeln</a> je Einrichtung an-/abschaltbar.</p>
    </div>
</div>
