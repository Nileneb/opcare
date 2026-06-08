<div>
    <div class="page-head">
        <div>
            <p class="kicker">Arbeitsschutz · Gefährdungsbeurteilung</p>
            <h1>Gefährdungsbeurteilung</h1>
            <p class="lead">Beurteilung der Arbeitsbedingungen und Dokumentation von Gefährdungen und Schutzmaßnahmen (§ 5 ArbSchG, § 6 ArbSchG).</p>
        </div>
        @if ($ueberfaellig > 0)
            <span class="badge red" title="überfällige Fortschreibungen">{{ $ueberfaellig }} Fortschreibung(en) überfällig</span>
        @endif
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- Belastungsmeldungen (§ 5 ArbSchG live) --}}
    <div class="card">
        <div class="card-head">
            <h3>Belastungsmeldungen (§ 5 ArbSchG live)</h3>
            <span class="badge {{ $belastungsmeldungen->isEmpty() ? 'green' : 'red' }}">
                {{ $belastungsmeldungen->isEmpty() ? 'Keine offenen Meldungen' : $belastungsmeldungen->count().' offen' }}
            </span>
        </div>
        @forelse ($belastungsmeldungen as $m)
            <div style="border:1px solid #fee2e2;border-radius:4px;padding:.75rem;margin-bottom:.75rem;background:#fff7f7">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem">
                    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                        {{-- Farbverlauf-Indikator statt Stufen-Badge --}}
                        <div style="width:18px;height:18px;border-radius:50%;background:{{ \App\Domains\Arbeitsschutz\Support\BelastungsAmpel::farbe($m->lage()) }};border:1px solid rgba(0,0,0,.12);flex-shrink:0"
                             title="{{ $m->wohnbereich }} — Lage {{ $m->lage() }}/10 ({{ $m->stufe->label() }})"></div>
                        <strong>{{ $m->wohnbereich }}</strong>
                        <span class="muted" style="font-size:.82em">gemeldet {{ $m->gemeldet_am->format('d.m.Y') }}</span>
                    </div>
                    <button class="btn btn-primary btn-sm" wire:click="meldungQuittieren({{ $m->id }})" wire:confirm="Meldung quittieren?">
                        Quittieren
                    </button>
                </div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem">
                    @foreach ($m->signale as $label => $wert)
                        <span class="badge gray" style="font-size:.8em"><b>{{ $label }}:</b> {{ $wert }}</span>
                    @endforeach
                </div>
                @if ($m->schutzmassnahme)
                    <p class="muted" style="font-size:.85em;margin-top:.4rem">
                        Entlastungsmaßnahme: {{ $m->schutzmassnahme->beschreibung }}
                        @if ($m->schutzmassnahme->frist)
                            · Frist: {{ $m->schutzmassnahme->frist->format('d.m.Y') }}
                        @endif
                    </p>
                @else
                    <p class="muted" style="font-size:.85em;margin-top:.4rem">Noch keine Entlastungsmaßnahme verknüpft — im Dienstplan „Entlasten" klicken.</p>
                @endif
            </div>
        @empty
            <p class="empty">Keine offenen Belastungsmeldungen.</p>
        @endforelse
        <p class="muted" style="margin-top:6px;font-size:.8em">Legende: <span style="color:hsl(0,75%,45%)">rot</span> = stark belastet · <span style="color:hsl(50,75%,45%)">gelb</span> = mäßig · <span style="color:hsl(120,75%,45%)">grün</span> = entlastet</p>
    </div>

    {{-- Selbst-Überlastungsmeldungen (Mode C) --}}
    <div class="card">
        <div class="card-head">
            <h3>Selbst-Überlastungsmeldungen (Mode C)</h3>
            <span class="badge {{ $selbstmeldungen->isEmpty() ? 'green' : 'amber' }}">
                {{ $selbstmeldungen->isEmpty() ? 'Keine offenen Meldungen' : $selbstmeldungen->count().' offen' }}
            </span>
        </div>
        <p class="muted" style="margin-bottom:10px;font-size:.85em">Mitarbeitende melden sich hier selbst an die Leitung —
            ausschließlich per eigenem Knopfdruck, nie automatisch (§ 87 BetrVG, Art. 6(1)(a) DSGVO).</p>
        @forelse ($selbstmeldungen as $sm)
            <div style="border:1px solid #fef3c7;border-radius:4px;padding:.75rem;margin-bottom:.75rem;background:#fffbeb">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem">
                    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                        {{-- Farbverlauf-Indikator (wert = 0-10 wie lage) --}}
                        <div style="width:18px;height:18px;border-radius:50%;background:{{ \App\Domains\Arbeitsschutz\Support\BelastungsAmpel::farbe($sm->wert) }};border:1px solid rgba(0,0,0,.12);flex-shrink:0"
                             title="Belastungswert {{ $sm->wert }}/10"></div>
                        <strong>{{ $sm->user->name }}</strong>
                        <span class="muted" style="font-size:.82em">gemeldet {{ $sm->gemeldet_am->format('d.m.Y') }}</span>
                    </div>
                    <button class="btn btn-primary btn-sm" wire:click="selbstmeldungQuittieren({{ $sm->id }})"
                            wire:confirm="Meldung von {{ $sm->user->name }} quittieren?">
                        Quittieren
                    </button>
                </div>
                @if ($sm->notiz)
                    <p class="muted" style="font-size:.85em;margin-top:.4rem">{{ $sm->notiz }}</p>
                @endif
            </div>
        @empty
            <p class="empty">Keine offenen Selbst-Überlastungsmeldungen.</p>
        @endforelse
    </div>

    {{-- GBU anlegen --}}
    <div class="card">
        <div class="card-head"><h3>GBU anlegen</h3><span class="badge gray">§ 5 / § 6 ArbSchG</span></div>
        <form wire:submit="gbuAnlegen">
            <div class="form-row-3">
                <div class="field">
                    <label>Arbeitsbereich *</label>
                    <input type="text" wire:model="arbeitsbereich" placeholder="z. B. Pflege Wohnbereich 1" />
                    @error('arbeitsbereich')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Tätigkeit</label>
                    <input type="text" wire:model="taetigkeit" placeholder="z. B. Heben und Umlagern" />
                    @error('taetigkeit')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Überprüfungsintervall (Monate) *</label>
                    <input type="number" wire:model="ueberpruefungsintervall_monate" min="1" max="120" />
                    @error('ueberpruefungsintervall_monate')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Verantwortlich</label>
                    <input type="text" wire:model="verantwortlich" placeholder="z. B. Fachkraft für Arbeitssicherheit" />
                    @error('verantwortlich')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn btn-primary btn-sm">+ GBU anlegen</button>
        </form>
    </div>

    {{-- GBU-Liste --}}
    @forelse ($beurteilungen as $gbu)
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>{{ $gbu->arbeitsbereich }}</h3>
                    @if ($gbu->taetigkeit)<span class="muted">{{ $gbu->taetigkeit }}</span>@endif
                </div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                    {{-- Frist-Ampel --}}
                    @php $ampel = $gbu->faelligkeitsStatus(); @endphp
                    @if ($ampel === 'rot')
                        <span class="badge red">Fortschreibung überfällig</span>
                    @elseif ($ampel === 'gelb')
                        <span class="badge amber">Fortschreibung bald fällig</span>
                    @else
                        <span class="badge green">Fortschreibung aktuell</span>
                    @endif

                    {{-- Status-Badge --}}
                    @if ($gbu->status === \App\Domains\Arbeitsschutz\Enums\GbuStatus::Entwurf)
                        <span class="badge gray">{{ $gbu->status->label() }}</span>
                    @elseif ($gbu->status === \App\Domains\Arbeitsschutz\Enums\GbuStatus::Freigegeben)
                        <span class="badge green">{{ $gbu->status->label() }}</span>
                    @else
                        <span class="badge amber">{{ $gbu->status->label() }}</span>
                    @endif

                    {{-- Offene Maßnahmen (SSOT: offeneMassnahmen()) --}}
                    @if ($gbu->hatOffeneMassnahmen())
                        <span class="badge amber">{{ $gbu->offeneMassnahmen()->count() }} offene Maßnahme(n)</span>
                    @endif

                    {{-- Höchste Risikostufe --}}
                    @php $risiko = $gbu->hoechsteRisikostufe(); @endphp
                    @if ($risiko === 'hoch')
                        <span class="badge red">Risiko: hoch</span>
                    @elseif ($risiko === 'mittel')
                        <span class="badge amber">Risiko: mittel</span>
                    @elseif ($risiko === 'gering')
                        <span class="badge green">Risiko: gering</span>
                    @endif
                </div>
            </div>

            <div class="grid-3" style="margin-bottom:1rem">
                <div>
                    <span class="muted">Letzte Überprüfung</span><br>
                    {{ $gbu->letzte_ueberpruefung_am?->format('d.m.Y') ?? '—' }}
                </div>
                <div>
                    <span class="muted">Nächste Fälligkeit</span><br>
                    {{ $gbu->naechsteUeberpruefung()?->format('d.m.Y') ?? '—' }}
                </div>
                <div>
                    <span class="muted">Verantwortlich</span><br>
                    {{ $gbu->verantwortlich ?? '—' }}
                </div>
            </div>

            {{-- Aktions-Buttons je Status --}}
            <div style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
                @if ($gbu->status === \App\Domains\Arbeitsschutz\Enums\GbuStatus::Entwurf)
                    <button class="btn btn-primary btn-sm" wire:click="gbuFreigeben({{ $gbu->id }})" wire:confirm="GBU freigeben?">Freigeben</button>
                @endif
                @if ($gbu->status === \App\Domains\Arbeitsschutz\Enums\GbuStatus::Freigegeben)
                    <form wire:submit="gbuFortschreiben({{ $gbu->id }})" style="display:inline-flex;gap:.5rem;align-items:center">
                        <input type="date" wire:model="fortschreibung_datum" max="{{ today()->toDateString() }}" />
                        @error('fortschreibung_datum')<span class="err">{{ $message }}</span>@enderror
                        <button class="btn btn-primary btn-sm">Fortschreiben</button>
                    </form>
                @endif
            </div>

            {{-- Gefährdungen --}}
            @forelse ($gbu->gefaehrdungen as $gefaehrdung)
                <div style="border:1px solid #e5e7eb;border-radius:4px;padding:.75rem;margin-bottom:.75rem">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem">
                        <div>
                            <strong>{{ $gefaehrdung->faktor->paragraph() }}</strong>
                            <span class="muted" style="margin-left:.5rem">{{ $gefaehrdung->faktor->label() }}</span>
                        </div>
                        @php $stufe = $gefaehrdung->risikostufe(); @endphp
                        @if ($stufe === 'hoch')
                            <span class="badge red">Risiko: hoch ({{ $gefaehrdung->risikowert() }})</span>
                        @elseif ($stufe === 'mittel')
                            <span class="badge amber">Risiko: mittel ({{ $gefaehrdung->risikowert() }})</span>
                        @else
                            <span class="badge green">Risiko: gering ({{ $gefaehrdung->risikowert() }})</span>
                        @endif
                    </div>
                    <p style="margin:.25rem 0">{{ $gefaehrdung->beschreibung }}</p>
                    <p class="muted" style="font-size:.85em">W: {{ $gefaehrdung->wahrscheinlichkeit }} · S: {{ $gefaehrdung->schwere }}</p>

                    {{-- Maßnahmen --}}
                    @forelse ($gefaehrdung->massnahmen as $massnahme)
                        <div style="background:#f9fafb;border-radius:3px;padding:.5rem;margin:.4rem 0;display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;flex-wrap:wrap">
                            <div>
                                <span class="badge gray" style="font-size:.8em">{{ $massnahme->typ->label() }} (T{{ $massnahme->typ->rang() }})</span>
                                <span style="margin-left:.4rem">{{ $massnahme->beschreibung }}</span>
                                @if ($massnahme->verantwortlich)<span class="muted" style="font-size:.85em"> · {{ $massnahme->verantwortlich }}</span>@endif
                                @if ($massnahme->frist)<span class="muted" style="font-size:.85em"> · Frist: {{ $massnahme->frist->format('d.m.Y') }}</span>@endif
                            </div>
                            <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                                @if ($massnahme->istWirksamGeprueft())
                                    <span class="badge green" style="font-size:.8em">Wirksam geprüft {{ $massnahme->wirksam_geprueft_am->format('d.m.Y') }}</span>
                                @elseif (! $massnahme->istOffen())
                                    <span class="badge amber" style="font-size:.8em">Umgesetzt {{ $massnahme->umgesetzt_am->format('d.m.Y') }}</span>
                                    <form wire:submit="wirksamkeitPruefen({{ $massnahme->id }})" style="display:inline-flex;gap:.3rem;align-items:center">
                                        <input type="date" wire:model="wirksam_geprueft_am" max="{{ today()->toDateString() }}" style="font-size:.85em" />
                                        @error('wirksam_geprueft_am')<span class="err">{{ $message }}</span>@enderror
                                        <button class="btn btn-sm">Wirksamkeit geprüft</button>
                                    </form>
                                @else
                                    <span class="badge red" style="font-size:.8em">Offen</span>
                                    <form wire:submit="massnahmeUmgesetzt({{ $massnahme->id }})" style="display:inline-flex;gap:.3rem;align-items:center">
                                        <input type="date" wire:model="umgesetzt_am" max="{{ today()->toDateString() }}" style="font-size:.85em" />
                                        @error('umgesetzt_am')<span class="err">{{ $message }}</span>@enderror
                                        <button class="btn btn-sm">umgesetzt</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="muted" style="font-size:.85em">Noch keine Maßnahmen erfasst.</p>
                    @endforelse

                    {{-- Maßnahme hinzufügen --}}
                    <details style="margin-top:.5rem">
                        <summary class="btn btn-sm" style="cursor:pointer;display:inline-block">+ Maßnahme hinzufügen</summary>
                        <form wire:submit="massnahmeHinzufuegen({{ $gefaehrdung->id }})" style="margin-top:.5rem">
                            <div class="form-row-3">
                                <div class="field">
                                    <label>Typ (TOP) *</label>
                                    <select wire:model="massnahme_typ">
                                        <option value="">— wählen —</option>
                                        @foreach ($massnahmentypen as $typ)
                                            <option value="{{ $typ->value }}">{{ $typ->label() }} (Rang {{ $typ->rang() }})</option>
                                        @endforeach
                                    </select>
                                    @error('massnahme_typ')<span class="err">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label>Beschreibung *</label>
                                    <input type="text" wire:model="massnahme_beschreibung" placeholder="z. B. Höhenverstellbare Pflegebetten" />
                                    @error('massnahme_beschreibung')<span class="err">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label>Verantwortlich</label>
                                    <input type="text" wire:model="massnahme_verantwortlich" />
                                    @error('massnahme_verantwortlich')<span class="err">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label>Frist</label>
                                    <input type="date" wire:model="massnahme_frist" />
                                    @error('massnahme_frist')<span class="err">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <button class="btn btn-primary btn-sm">Maßnahme anlegen</button>
                        </form>
                    </details>
                </div>
            @empty
                <p class="muted">Noch keine Gefährdungen erfasst.</p>
            @endforelse

            {{-- Gefährdung hinzufügen --}}
            <details style="margin-top:.75rem">
                <summary class="btn btn-sm" style="cursor:pointer;display:inline-block">+ Gefährdung hinzufügen</summary>
                <form wire:submit="gefaehrdungHinzufuegen({{ $gbu->id }})" style="margin-top:.5rem">
                    <div class="form-row-3">
                        <div class="field">
                            <label>Gefährdungsfaktor (§ 5 Abs. 3 ArbSchG) *</label>
                            <select wire:model="gefaehrdung_faktor">
                                <option value="">— wählen —</option>
                                @foreach ($faktoren as $faktor)
                                    <option value="{{ $faktor->value }}">{{ $faktor->paragraph() }} · {{ $faktor->label() }}</option>
                                @endforeach
                            </select>
                            @error('gefaehrdung_faktor')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Beschreibung *</label>
                            <input type="text" wire:model="gefaehrdung_beschreibung" placeholder="z. B. Heben/Umlagern von Bewohnern" />
                            @error('gefaehrdung_beschreibung')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Wahrscheinlichkeit (1–3) *</label>
                            <input type="number" wire:model="gefaehrdung_wahrscheinlichkeit" min="1" max="3" />
                            @error('gefaehrdung_wahrscheinlichkeit')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label>Schwere (1–3) *</label>
                            <input type="number" wire:model="gefaehrdung_schwere" min="1" max="3" />
                            @error('gefaehrdung_schwere')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm">Gefährdung anlegen</button>
                </form>
            </details>
        </div>
    @empty
        <div class="card"><p class="muted">Noch keine Gefährdungsbeurteilungen angelegt. Bitte oben eine GBU anlegen.</p></div>
    @endforelse

    <p class="muted" style="font-size:.8em;margin-top:1.5rem">
        Rechtsgrundlage: § 5 ArbSchG (Beurteilung der Arbeitsbedingungen), § 6 ArbSchG (Dokumentation),
        § 3 Abs. 1 ArbSchG (Wirksamkeitskontrolle und Fortschreibung), § 4 ArbSchG (Maßnahmen-Grundsätze TOP).
    </p>
</div>
