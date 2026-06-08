<div>
    <div class="page-head">
        <div>
            <p class="kicker">Küche · Lebensmittelhygiene</p>
            <h1>HACCP-Tagesblatt</h1>
            <p class="lead">Temperaturkontrolle kritischer Kontrollpunkte (CCP) nach VO (EG) 852/2004 Art. 5 und DIN 10508. Jede Abweichung erfordert eine dokumentierte Korrekturmaßnahme.</p>
        </div>
        @php $offeneGesamt = collect($tagesblatt)->filter(fn($e) => $e['offene_abweichung'])->count(); @endphp
        @if ($offeneGesamt > 0)
            <span class="badge red" title="offene Korrekturmaßnahmen erforderlich">{{ $offeneGesamt }} CCP {{ $offeneGesamt === 1 ? 'Abweichung' : 'Abweichungen' }} offen</span>
        @endif
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- Tagesblatt --}}
    @if (empty($tagesblatt))
        <div class="card"><p class="muted">Noch keine aktiven Messpunkte erfasst. Bitte unten einen Messpunkt anlegen.</p></div>
    @else
        @foreach ($tagesblatt as $eintrag)
            @php
                $mp = $eintrag['messpunkt'];
                $richtung = $mp->art->istMax() ? '≤' : '≥';
                $messungenHeute = $eintrag['messungen_heute'];
                $offeneAbw = $eintrag['offene_abweichung'];
            @endphp
            <div class="card" style="{{ $offeneAbw ? 'border-left:4px solid #c0392b' : '' }}">
                <div class="card-head">
                    <div>
                        <h3>{{ $mp->bezeichnung }}</h3>
                        <span class="muted">{{ $mp->art->label() }} &nbsp;·&nbsp; Soll {{ $richtung }} {{ number_format((float)$mp->grenzwert, 1, ',', '') }} °C</span>
                    </div>
                    @if ($offeneAbw)
                        <span class="badge red">Korrektur ausstehend</span>
                    @elseif (count($messungenHeute) > 0)
                        <span class="badge green">Heute gemessen</span>
                    @else
                        <span class="badge amber">Heute noch keine Messung</span>
                    @endif
                </div>

                {{-- Roter Pflicht-Kasten bei offener Abweichung --}}
                @if ($offeneAbw)
                    @foreach ($messungenHeute as $abwMessung)
                        @if ($abwMessung->offen())
                            <div class="alert alert-danger" style="border:2px solid #c0392b;background:#fdf0ef;padding:1rem;border-radius:4px;margin-bottom:1rem">
                                <strong>&#9888; Grenzwert-Abweichung am CCP {{ $mp->bezeichnung }}: Ist {{ number_format((float)$abwMessung->wert, 1, ',', '') }} °C, Soll {{ $richtung }} {{ number_format((float)$mp->grenzwert, 1, ',', '') }} °C &mdash; Korrekturmaßnahme erforderlich (VO 852/2004 Art. 5).</strong>
                                <form wire:submit="korrekturSetzen({{ $abwMessung->id }})" style="margin-top:.75rem">
                                    <div class="field">
                                        <label>Eingeleitete Korrekturmaßnahme *</label>
                                        <textarea wire:model="korrektur_text" rows="3" placeholder="z. B. Produkte umgelagert, Techniker verständigt, Ware geprüft und freigegeben/verworfen"></textarea>
                                        @error('korrektur_text')<span class="err">{{ $message }}</span>@enderror
                                    </div>
                                    <button class="btn btn-danger btn-sm">Korrekturmaßnahme dokumentieren</button>
                                </form>
                            </div>
                        @endif
                    @endforeach
                    {{-- Falls Abweichung von gestrigen Messungen offen (kein heutiger Eintrag) --}}
                    @if (count($messungenHeute) === 0 || collect($messungenHeute)->filter(fn($m) => $m->offen())->isEmpty())
                        @php
                            $offeneMsgs = $mp->messungen()->where('abweichung', true)->whereNull('korrekturmassnahme')->latest('gemessen_am')->get();
                        @endphp
                        @foreach ($offeneMsgs as $abwMessung)
                            <div class="alert alert-danger" style="border:2px solid #c0392b;background:#fdf0ef;padding:1rem;border-radius:4px;margin-bottom:1rem">
                                <strong>&#9888; Grenzwert-Abweichung am CCP {{ $mp->bezeichnung }}: Ist {{ number_format((float)$abwMessung->wert, 1, ',', '') }} °C, Soll {{ $richtung }} {{ number_format((float)$mp->grenzwert, 1, ',', '') }} °C &mdash; Korrekturmaßnahme erforderlich (VO 852/2004 Art. 5).</strong>
                                <span class="muted" style="display:block;margin-top:.25rem;font-size:.85em">Messung vom {{ $abwMessung->gemessen_am->format('d.m.Y H:i') }} Uhr</span>
                                <form wire:submit="korrekturSetzen({{ $abwMessung->id }})" style="margin-top:.75rem">
                                    <div class="field">
                                        <label>Eingeleitete Korrekturmaßnahme *</label>
                                        <textarea wire:model="korrektur_text" rows="3" placeholder="z. B. Produkte umgelagert, Techniker verständigt, Ware geprüft und freigegeben/verworfen"></textarea>
                                        @error('korrektur_text')<span class="err">{{ $message }}</span>@enderror
                                    </div>
                                    <button class="btn btn-danger btn-sm">Korrekturmaßnahme dokumentieren</button>
                                </form>
                            </div>
                        @endforeach
                    @endif
                @endif

                {{-- Heutige Messungen --}}
                @if (count($messungenHeute) > 0)
                    <div style="margin-bottom:1rem">
                        <h4>Heutige Messungen</h4>
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Zeitpunkt</th>
                                    <th>Ist (°C)</th>
                                    <th>Bewertung</th>
                                    <th>Korrekturmaßnahme</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($messungenHeute as $messung)
                                    <tr @if($messung->abweichung) style="background:#fdf0ef" @endif>
                                        <td>{{ $messung->gemessen_am->format('H:i') }} Uhr</td>
                                        <td>
                                            <strong @if($messung->abweichung) style="color:#c0392b" @endif>
                                                {{ number_format((float)$messung->wert, 1, ',', '') }} °C
                                            </strong>
                                        </td>
                                        <td>
                                            @if ($messung->abweichung)
                                                <span class="badge red">Abweichung</span>
                                            @else
                                                <span class="badge green">OK</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($messung->korrekturmassnahme)
                                                <span class="badge green" title="{{ $messung->korrekturmassnahme }}">dokumentiert</span>
                                            @elseif ($messung->abweichung)
                                                <span class="badge red">ausstehend</span>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Messung erfassen --}}
                <details style="margin-top:.5rem">
                    <summary class="btn btn-sm" style="cursor:pointer;display:inline-block">+ Messung erfassen</summary>
                    <form wire:submit="messungErfassen({{ $mp->id }})" style="margin-top:.75rem">
                        <div class="form-row-3">
                            <div class="field">
                                <label>Temperatur (°C) *</label>
                                <input type="number" step="0.1" wire:model="wert" placeholder="{{ number_format($mp->grenzwertDefault ?? $mp->grenzwert, 1, ',', '') }}" />
                                @error('wert')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label>Messzeitpunkt *</label>
                                <input type="datetime-local" wire:model="gemessen_am" max="{{ now()->format('Y-m-d\TH:i') }}" />
                                @error('gemessen_am')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label>Korrekturmaßnahme (optional)</label>
                                <input type="text" wire:model="korrektur" placeholder="bei bekannter Abweichung" />
                                @error('korrektur')<span class="err">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm">Messung erfassen</button>
                    </form>
                </details>
            </div>
        @endforeach
    @endif

    {{-- Messpunkt anlegen --}}
    <div class="card">
        <div class="card-head">
            <h3>Messpunkt anlegen</h3>
            <span class="badge gray">CCP nach VO (EG) 852/2004 Art. 5</span>
        </div>
        <form wire:submit="messpunktSpeichern">
            <div class="form-row-3">
                <div class="field">
                    <label>Bezeichnung *</label>
                    <input type="text" wire:model="bezeichnung" placeholder="z. B. Kühlhaus, Tiefkühltruhe, Bain-Marie" />
                    @error('bezeichnung')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Art *</label>
                    <select wire:model.live="art">
                        <option value="">— bitte wählen —</option>
                        @foreach ($haccpArten as $haccpArt)
                            <option value="{{ $haccpArt->value }}">{{ $haccpArt->label() }} (Default: {{ number_format($haccpArt->grenzwertDefault(), 1, ',', '') }} °C)</option>
                        @endforeach
                    </select>
                    @error('art')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Grenzwert (°C) — leer = Default</label>
                    <input type="number" step="0.1" wire:model="grenzwert"
                        placeholder="{{ $artDefault !== null ? number_format($artDefault, 1, ',', '') . ' (Default)' : '—' }}" />
                    @if ($artDefault !== null)
                        <span class="muted" style="font-size:.85em">
                            Default: {{ number_format($artDefault, 1, ',', '') }} °C
                            ({{ $artIstMax ? 'Maximaltemperatur, Abweichung wenn Ist &gt; Soll' : 'Minimaltemperatur, Abweichung wenn Ist &lt; Soll' }})
                        </span>
                    @endif
                    @error('grenzwert')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ Messpunkt anlegen</button>
        </form>
    </div>

    {{-- Alle Messpunkte (inkl. inaktiver) --}}
    @if ($alleMesspunkte->isNotEmpty())
        <div class="card">
            <div class="card-head"><h3>Alle Messpunkte</h3></div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Bezeichnung</th>
                        <th>Art</th>
                        <th>Grenzwert</th>
                        <th>Richtung</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($alleMesspunkte as $mp)
                        <tr>
                            <td>{{ $mp->bezeichnung }}</td>
                            <td>{{ $mp->art->label() }}</td>
                            <td>{{ number_format((float)$mp->grenzwert, 1, ',', '') }} °C</td>
                            <td>{{ $mp->art->istMax() ? '≤ (Max-CCP)' : '≥ (Min-CCP)' }}</td>
                            <td>
                                @if ($mp->aktiv)
                                    <span class="badge green">aktiv</span>
                                @else
                                    <span class="badge gray">inaktiv</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="muted" style="font-size:.8em;margin-top:1.5rem">
        Rechtsgrundlage: Verordnung (EG) Nr. 852/2004 Art. 5 (HACCP-Grundsätze),
        LMHV — Lebensmittelhygiene-Verordnung, DIN 10508 (Temperaturen für Lebensmittel).
    </p>
</div>
