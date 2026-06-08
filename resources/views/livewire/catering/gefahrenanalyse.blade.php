<div>
    <div class="page-head">
        <div>
            <p class="kicker">Küche · HACCP</p>
            <h1>Gefahrenanalyse-Register</h1>
            <p class="lead">Systematische Gefahrenanalyse je Prozessschritt (HACCP-Prinzip 1–3) mit CCP-Entscheidung,
                Verknüpfung zur Temperaturüberwachung und Lenkungsmaßnahmen — VO (EG) 852/2004 Art. 5, Codex Alimentarius.</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            @if ($mitLuecken > 0)
                <span class="badge red" title="signifikante Gefahr ohne Lenkung oder CCP ohne Überwachung">{{ $mitLuecken }} mit Lücke</span>
            @endif
            @if ($ueberfaellig > 0)
                <span class="badge red" title="überfällige Verifizierungen">{{ $ueberfaellig }} Verifizierung(en) überfällig</span>
            @endif
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <p class="muted" style="font-size:.9em;margin:0">
            Das tägliche <a href="{{ route('haccp') }}">HACCP-Tagesblatt</a> überwacht die Grenzwerte der hier als
            <strong>CCP</strong> bestimmten Punkte (HACCP-Prinzip 4/5). Dieses Register begründet, <em>warum</em> ein
            Punkt überwacht wird.
        </p>
    </div>

    {{-- Analyse anlegen --}}
    <div class="card">
        <div class="card-head"><h3>Gefahrenanalyse anlegen</h3><span class="badge gray">VO 852/2004 Art. 5</span></div>
        <form wire:submit="analyseAnlegen">
            <div class="form-row-3">
                <div class="field">
                    <label>Prozessschritt *</label>
                    <input type="text" wire:model="prozessschritt" placeholder="z. B. Wareneingang Kühlware" />
                    @error('prozessschritt')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Bereich</label>
                    <input type="text" wire:model="bereich" placeholder="z. B. Küche, Lager, Ausgabe" />
                    @error('bereich')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Verifizierungsintervall (Monate) *</label>
                    <input type="number" wire:model="verifizierungsintervall_monate" min="1" max="120" />
                    @error('verifizierungsintervall_monate')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Verantwortlich</label>
                    <input type="text" wire:model="verantwortlich" placeholder="z. B. Küchenleitung" />
                    @error('verantwortlich')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ Gefahrenanalyse anlegen</button>
        </form>
    </div>

    {{-- Analyse-Liste --}}
    @forelse ($analysen as $analyse)
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>{{ $analyse->prozessschritt }}</h3>
                    @if ($analyse->bereich)<span class="muted">{{ $analyse->bereich }}</span>@endif
                </div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                    {{-- Frist-Ampel --}}
                    @php $ampel = $analyse->faelligkeitsStatus(); @endphp
                    @if ($ampel === 'rot')
                        <span class="badge red">Verifizierung überfällig</span>
                    @elseif ($ampel === 'gelb')
                        <span class="badge amber">Verifizierung bald fällig</span>
                    @else
                        <span class="badge green">Verifizierung aktuell</span>
                    @endif

                    {{-- Status-Badge --}}
                    @if ($analyse->status === \App\Domains\Catering\Enums\GefahrenanalyseStatus::Entwurf)
                        <span class="badge gray">{{ $analyse->status->label() }}</span>
                    @elseif ($analyse->status === \App\Domains\Catering\Enums\GefahrenanalyseStatus::Freigegeben)
                        <span class="badge green">{{ $analyse->status->label() }}</span>
                    @else
                        <span class="badge amber">{{ $analyse->status->label() }}</span>
                    @endif

                    {{-- Offene Lenkungen (SSOT) --}}
                    @if ($analyse->hatOffeneLenkungsmassnahmen())
                        <span class="badge amber">{{ $analyse->offeneLenkungsmassnahmen()->count() }} offene Lenkung(en)</span>
                    @endif

                    {{-- Höchste Risikostufe --}}
                    @php $risiko = $analyse->hoechsteRisikostufe(); @endphp
                    @if ($risiko === 'hoch')
                        <span class="badge red">Risiko: hoch</span>
                    @elseif ($risiko === 'mittel')
                        <span class="badge amber">Risiko: mittel</span>
                    @elseif ($risiko === 'gering')
                        <span class="badge green">Risiko: gering</span>
                    @endif
                </div>
            </div>

            {{-- Lücken-Warnung (SSOT, kein stilles Kappen) --}}
            @php $sigOhneLenkung = $analyse->signifikanteGefahrenOhneLenkung(); $ccpOhne = $analyse->ccpOhneUeberwachung(); @endphp
            @if ($sigOhneLenkung->isNotEmpty() || $ccpOhne->isNotEmpty())
                <div class="alert alert-danger" style="margin-bottom:1rem">
                    @if ($sigOhneLenkung->isNotEmpty())
                        <div><b>{{ $sigOhneLenkung->count() }}</b> signifikante Gefahr(en) ohne Lenkungsmaßnahme.</div>
                    @endif
                    @if ($ccpOhne->isNotEmpty())
                        <div><b>{{ $ccpOhne->count() }}</b> als CCP eingestufte Gefahr(en) ohne verknüpften Überwachungs-Messpunkt (HACCP-Prinzip 4).</div>
                    @endif
                </div>
            @endif

            <div class="grid-3" style="margin-bottom:1rem">
                <div>
                    <span class="muted">Letzte Verifizierung</span><br>
                    {{ $analyse->letzte_verifizierung_am?->format('d.m.Y') ?? '—' }}
                </div>
                <div>
                    <span class="muted">Nächste Fälligkeit</span><br>
                    {{ $analyse->naechsteVerifizierung()?->format('d.m.Y') ?? '—' }}
                </div>
                <div>
                    <span class="muted">Verantwortlich</span><br>
                    {{ $analyse->verantwortlich ?? '—' }}
                </div>
            </div>

            {{-- Aktions-Buttons je Status --}}
            <div style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
                @if ($analyse->status === \App\Domains\Catering\Enums\GefahrenanalyseStatus::Entwurf)
                    <button class="btn btn-primary btn-sm" wire:click="analyseFreigeben({{ $analyse->id }})" wire:confirm="Gefahrenanalyse freigeben?">Freigeben</button>
                @endif
                @if ($analyse->status === \App\Domains\Catering\Enums\GefahrenanalyseStatus::Freigegeben)
                    <form wire:submit="analyseVerifizieren({{ $analyse->id }})" style="display:inline-flex;gap:.5rem;align-items:center">
                        <input type="date" wire:model="verifizierung_datum" max="{{ today()->toDateString() }}" />
                        @error('verifizierung_datum')<span class="err">{{ $message }}</span>@enderror
                        <button class="btn btn-primary btn-sm">Verifizieren</button>
                    </form>
                @endif
            </div>

            {{-- Gefahren --}}
            @forelse ($analyse->gefahren as $gefahr)
                <div style="border:1px solid #e5e7eb;border-radius:4px;padding:.75rem;margin-bottom:.75rem">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;gap:.5rem;flex-wrap:wrap">
                        <div>
                            <span class="badge gray" title="{{ $gefahr->gefahrenart->beispiel() }}">{{ $gefahr->gefahrenart->kuerzel() }} · {{ $gefahr->gefahrenart->label() }}</span>
                            @if ($gefahr->ist_ccp)
                                @if ($gefahr->istCcpOhneUeberwachung())
                                    <span class="badge red" style="margin-left:.3rem">CCP ohne Überwachung</span>
                                @else
                                    <span class="badge green" style="margin-left:.3rem">CCP · {{ $gefahr->messpunkt?->bezeichnung }}</span>
                                @endif
                            @endif
                        </div>
                        @php $stufe = $gefahr->risikostufe(); @endphp
                        @if ($stufe === 'hoch')
                            <span class="badge red">Risiko: hoch ({{ $gefahr->risikowert() }})</span>
                        @elseif ($stufe === 'mittel')
                            <span class="badge amber">Risiko: mittel ({{ $gefahr->risikowert() }})</span>
                        @else
                            <span class="badge green">Risiko: gering ({{ $gefahr->risikowert() }})</span>
                        @endif
                    </div>
                    <p style="margin:.25rem 0">{{ $gefahr->beschreibung }}</p>
                    <p class="muted" style="font-size:.85em">W: {{ $gefahr->wahrscheinlichkeit }} · S: {{ $gefahr->schwere }}@if ($gefahr->ccp_begruendung) · CCP-Begründung: {{ $gefahr->ccp_begruendung }}@endif</p>

                    @if ($gefahr->signifikant() && ! $gefahr->hatLenkung())
                        <p class="err" style="font-size:.85em">Signifikante Gefahr ohne Lenkungsmaßnahme — bitte unten festlegen.</p>
                    @endif

                    {{-- Lenkungsmaßnahmen --}}
                    @forelse ($gefahr->lenkungsmassnahmen as $lenkung)
                        <div style="background:#f9fafb;border-radius:3px;padding:.5rem;margin:.4rem 0;display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;flex-wrap:wrap">
                            <div>
                                <span class="badge gray" style="font-size:.8em">{{ $lenkung->art->label() }}</span>
                                <span style="margin-left:.4rem">{{ $lenkung->beschreibung }}</span>
                                @if ($lenkung->verantwortlich)<span class="muted" style="font-size:.85em"> · {{ $lenkung->verantwortlich }}</span>@endif
                                @if ($lenkung->frist)<span class="muted" style="font-size:.85em"> · Frist: {{ $lenkung->frist->format('d.m.Y') }}</span>@endif
                            </div>
                            <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                                @if ($lenkung->istVerifiziert())
                                    <span class="badge green" style="font-size:.8em">Verifiziert {{ $lenkung->verifiziert_am->format('d.m.Y') }}</span>
                                @elseif (! $lenkung->istOffen())
                                    <span class="badge amber" style="font-size:.8em">Umgesetzt {{ $lenkung->umgesetzt_am->format('d.m.Y') }}</span>
                                    <form wire:submit="lenkungVerifizieren({{ $lenkung->id }})" style="display:inline-flex;gap:.3rem;align-items:center">
                                        <input type="date" wire:model="verifiziert_am" max="{{ today()->toDateString() }}" style="font-size:.85em" />
                                        @error('verifiziert_am')<span class="err">{{ $message }}</span>@enderror
                                        <button class="btn btn-sm">Wirksamkeit verifiziert</button>
                                    </form>
                                @else
                                    <span class="badge red" style="font-size:.8em">Offen</span>
                                    <form wire:submit="lenkungUmgesetzt({{ $lenkung->id }})" style="display:inline-flex;gap:.3rem;align-items:center">
                                        <input type="date" wire:model="umgesetzt_am" max="{{ today()->toDateString() }}" style="font-size:.85em" />
                                        @error('umgesetzt_am')<span class="err">{{ $message }}</span>@enderror
                                        <button class="btn btn-sm">umgesetzt</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="muted" style="font-size:.85em">Noch keine Lenkungsmaßnahmen erfasst.</p>
                    @endforelse

                    {{-- Lenkung hinzufügen --}}
                    <details style="margin-top:.5rem">
                        <summary class="btn btn-ghost btn-sm" style="cursor:pointer;display:inline-block">+ Lenkungsmaßnahme hinzufügen</summary>
                        <form wire:submit="lenkungHinzufuegen({{ $gefahr->id }})" style="margin-top:.5rem">
                            <div class="form-row-3">
                                <div class="field">
                                    <label>Art *</label>
                                    <select wire:model="lenkung_art">
                                        <option value="">— wählen —</option>
                                        @foreach ($lenkungsarten as $art)
                                            <option value="{{ $art->value }}">{{ $art->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('lenkung_art')<span class="err">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label>Beschreibung *</label>
                                    <input type="text" wire:model="lenkung_beschreibung" placeholder="z. B. Kühlkette ≤ 7 °C, dokumentierte Messung" />
                                    @error('lenkung_beschreibung')<span class="err">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label>Verantwortlich</label>
                                    <input type="text" wire:model="lenkung_verantwortlich" />
                                    @error('lenkung_verantwortlich')<span class="err">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label>Frist</label>
                                    <input type="date" wire:model="lenkung_frist" />
                                    @error('lenkung_frist')<span class="err">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <button class="btn btn-primary btn-sm">Lenkungsmaßnahme anlegen</button>
                        </form>
                    </details>
                </div>
            @empty
                <p class="muted">Noch keine Gefahren erfasst.</p>
            @endforelse

            {{-- Gefahr hinzufügen --}}
            <details style="margin-top:.75rem">
                <summary class="btn btn-ghost btn-sm" style="cursor:pointer;display:inline-block">+ Gefahr hinzufügen</summary>
                <form wire:submit="gefahrHinzufuegen({{ $analyse->id }})" style="margin-top:.5rem">
                    <div class="form-row-3">
                        <div class="field">
                            <label>Gefahrenart *</label>
                            <select wire:model="gefahr_art">
                                <option value="">— wählen —</option>
                                @foreach ($gefahrenarten as $art)
                                    <option value="{{ $art->value }}">{{ $art->kuerzel() }} · {{ $art->label() }}</option>
                                @endforeach
                            </select>
                            @error('gefahr_art')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Beschreibung *</label>
                            <input type="text" wire:model="gefahr_beschreibung" placeholder="z. B. Salmonellenvermehrung bei Unterbrechung der Kühlkette" />
                            @error('gefahr_beschreibung')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Wahrscheinlichkeit (1–3) *</label>
                            <input type="number" wire:model="gefahr_wahrscheinlichkeit" min="1" max="3" />
                            @error('gefahr_wahrscheinlichkeit')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Schwere (1–3) *</label>
                            <input type="number" wire:model="gefahr_schwere" min="1" max="3" />
                            @error('gefahr_schwere')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>CCP-Entscheidung</label>
                            <label style="display:flex;align-items:center;gap:.4rem;font-weight:normal">
                                <input type="checkbox" wire:model="gefahr_ist_ccp" style="width:auto" /> kritischer Kontrollpunkt (CCP)
                            </label>
                        </div>
                        <div class="field">
                            <label>Überwachungs-Messpunkt (CCP)</label>
                            <select wire:model="gefahr_messpunkt_id">
                                <option value="">— keiner —</option>
                                @foreach ($messpunkte as $mp)
                                    <option value="{{ $mp->id }}">{{ $mp->bezeichnung }} ({{ $mp->art->label() }})</option>
                                @endforeach
                            </select>
                            @error('gefahr_messpunkt_id')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>CCP-Begründung</label>
                            <input type="text" wire:model="gefahr_ccp_begruendung" placeholder="z. B. letzter Schritt zur Gefahrenbeherrschung" />
                            @error('gefahr_ccp_begruendung')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm">Gefahr anlegen</button>
                </form>
            </details>
        </div>
    @empty
        <div class="card"><p class="muted">Noch keine Gefahrenanalysen angelegt. Bitte oben einen Prozessschritt anlegen.</p></div>
    @endforelse

    <p class="muted" style="font-size:.8em;margin-top:1.5rem">
        Rechtsgrundlage: VO (EG) 852/2004 Art. 5 (HACCP-System), Codex Alimentarius CAC/RCP 1-1969
        (7 HACCP-Grundsätze), VO (EU) 1169/2011 (LMIV — Allergene). Die Überwachung der CCP-Grenzwerte erfolgt
        im HACCP-Tagesblatt.
    </p>
</div>
