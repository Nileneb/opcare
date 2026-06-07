<div>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         ANLEGE-MASKE (nur für Leitung)
    ══════════════════════════════════════════════════════ --}}
    @if ($darfAnlegen)
        <div class="card mb-4">
            <div class="card-header"><strong>Neue Abstimmung / Umfrage / Wahl anlegen</strong></div>
            <div class="card-body">
                <form wire:submit="anlegen">
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label">Titel <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('titel') is-invalid @enderror"
                                   wire:model="titel" />
                            @error('titel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ende (optional)</label>
                            <input type="datetime-local" class="form-control @error('ende_am') is-invalid @enderror"
                                   wire:model="ende_am" />
                            @error('ende_am') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control @error('beschreibung') is-invalid @enderror"
                                  wire:model="beschreibung" rows="2"></textarea>
                        @error('beschreibung') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <label class="form-label">Elektorat <span class="text-danger">*</span></label>
                            <select class="form-select @error('elektorat') is-invalid @enderror"
                                    wire:model.live="elektorat">
                                <option value="">— wählen —</option>
                                @foreach ($elektoratOptionen as $e)
                                    <option value="{{ $e->value }}">{{ $e->label() }}</option>
                                @endforeach
                            </select>
                            @error('elektorat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modus <span class="text-danger">*</span></label>
                            <select class="form-select @error('modus') is-invalid @enderror"
                                    wire:model="modus">
                                <option value="">— wählen —</option>
                                @foreach ($modusOptionen as $m)
                                    <option value="{{ $m->value }}">{{ $m->label() }}</option>
                                @endforeach
                            </select>
                            @error('modus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Art <span class="text-danger">*</span></label>
                            <select class="form-select @error('art') is-invalid @enderror"
                                    wire:model="art">
                                <option value="">— wählen —</option>
                                @foreach ($artOptionen as $a)
                                    <option value="{{ $a->value }}">{{ $a->label() }}</option>
                                @endforeach
                            </select>
                            @error('art') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($elektorat === 'gremium')
                        <div class="mb-2">
                            <label class="form-label">Gremium <span class="text-danger">*</span></label>
                            <select class="form-select @error('gremium_id') is-invalid @enderror"
                                    wire:model="gremium_id">
                                <option value="">— wählen —</option>
                                @foreach ($gremien as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                            @error('gremium_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <div class="mb-2">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="mehrfach"
                                   wire:model="mehrfachauswahl" />
                            <label class="form-check-label" for="mehrfach">Mehrfachauswahl erlaubt</label>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Antwortoptionen <span class="text-danger">*</span> (mind. 2)</label>
                        @foreach ($optionen as $i => $opt)
                            <div class="input-group mb-1">
                                <input type="text"
                                       class="form-control @error("optionen.{$i}") is-invalid @enderror"
                                       wire:model="optionen.{{ $i }}"
                                       placeholder="Option {{ $i + 1 }}" />
                                @if (count($optionen) > 2)
                                    <button type="button" class="btn btn-outline-danger"
                                            wire:click="optionEntfernen({{ $i }})">–</button>
                                @endif
                                @error("optionen.{$i}") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                wire:click="optionHinzufuegen">+ Option hinzufügen</button>
                        @error('optionen') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary mt-2">Abstimmung eröffnen</button>
                </form>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         OFFENE ABSTIMMUNGEN (abstimmbar für den eingeloggten User)
    ══════════════════════════════════════════════════════ --}}
    @forelse ($abstimmbar as $a)
        <div class="card mb-3 border-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <strong>{{ $a->titel }}</strong>
                    <span class="badge bg-primary ms-2">Offen</span>
                    <span class="badge bg-secondary ms-1">{{ $a->art->label() }}</span>
                    <span class="badge bg-light text-dark ms-1">{{ $a->modus->label() }}</span>
                </span>
                @if ($a->ende_am)
                    <small class="text-muted">Frist: {{ $a->ende_am->format('d.m.Y H:i') }}</small>
                @endif
            </div>
            <div class="card-body">

                {{-- Hinweis-Kasten Datenschutz / Rechtsrahmen --}}
                <div class="alert alert-info small mb-3" role="note">
                    <strong>Geheime Abgabe:</strong> Ihre Einzelstimme ist für niemanden außer Ihnen
                    nachvollziehbar (kein Personenbezug an der Stimme).
                    <br>
                    <em>Hinweis:</em> vollständige Unverkettbarkeit auch gegen Administratoren erst mit
                    dem Krypto-Härtungspfad.
                    <br>
                    <strong>Rechtsrahmen:</strong> DSG-EKD; gesetzliche Wahlen (Heimbeirat/MAV) sind
                    geheim — § 5 HeimmwV / § 11 MVG-EKD.
                </div>

                @if ($a->beschreibung)
                    <p class="text-muted small">{{ $a->beschreibung }}</p>
                @endif

                @error("auswahl.{$a->id}")
                    <div class="alert alert-danger small">{{ $message }}</div>
                @enderror

                {{-- Beleg-Box (einmalig nach Abstimmung) --}}
                {{-- Bewohner-Kiosk-Hinweis: Bewohner haben i.d.R. keinen User-Login --}}
                @if ($a->elektorat->value === 'bewohner')
                    <div class="alert alert-warning small mb-3" role="alert">
                        <strong>Bewohner-Stimmabgabe:</strong>
                        Diese Abstimmung richtet sich an Bewohner. Die Stimmabgabe durch Bewohner
                        erfolgt assistiert über den Kiosk-Pfad — dieser ist als Folge-Inkrement
                        vorgesehen und hier noch nicht verfügbar.
                        Eingeloggte Mitarbeitende/Gremiumsmitglieder können ggf. stellvertretend
                        laut Vollmacht abstimmen, sofern Sie stimmberechtigt sind.
                    </div>
                @endif

                @if ($belegToken && $belegFuerAbstimmungId === $a->id)
                    <div class="alert alert-success">
                        <strong>Ihre Stimme wurde erfasst.</strong><br>
                        Ihr Beleg: <code>{{ $belegToken }}</code>
                        <small class="d-block mt-1 text-muted">Bitte notieren. Dieser Code wird nicht erneut angezeigt.</small>
                    </div>
                @else
                    <form wire:submit="abstimmen({{ $a->id }})">
                        @foreach ($a->optionen as $option)
                            <div class="form-check mb-1">
                                @if ($a->mehrfachauswahl)
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="opt-{{ $a->id }}-{{ $option->id }}"
                                           wire:model="auswahl.{{ $a->id }}.{{ $option->id }}"
                                           value="{{ $option->id }}" />
                                @else
                                    <input type="radio"
                                           class="form-check-input"
                                           id="opt-{{ $a->id }}-{{ $option->id }}"
                                           name="auswahl-{{ $a->id }}"
                                           wire:model="auswahl.{{ $a->id }}.0"
                                           value="{{ $option->id }}" />
                                @endif
                                <label class="form-check-label" for="opt-{{ $a->id }}-{{ $option->id }}">
                                    {{ $option->text }}
                                </label>
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-primary mt-2"
                                wire:loading.attr="disabled">
                            <span wire:loading wire:target="abstimmen({{ $a->id }})">...</span>
                            Abstimmen
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        @if (!$darfAnlegen)
            <div class="alert alert-secondary">Aktuell keine offenen Abstimmungen für Sie.</div>
        @endif
    @endforelse

    {{-- ══════════════════════════════════════════════════════
         ERGEBNIS-KARTEN (abgeschlossene / eigene Abstimmungen)
    ══════════════════════════════════════════════════════ --}}
    @foreach ($abgeschlossen as $a)
        @if (isset($ergebnisse[$a->id]))
            @php $erg = $ergebnisse[$a->id]; @endphp
            <div class="card mb-3 border-secondary">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <strong>{{ $a->titel }}</strong>
                        <span class="badge bg-secondary ms-2">{{ $a->status->label() }}</span>
                        <span class="badge bg-light text-dark ms-1">{{ $a->art->label() }}</span>
                        <span class="badge bg-light text-dark ms-1">{{ $a->modus->label() }}</span>
                    </span>
                    <small class="text-muted">
                        Beteiligung: {{ $erg['beteiligung']['abgestimmt'] }} / {{ $erg['beteiligung']['berechtigt'] }}
                    </small>
                </div>
                <div class="card-body">

                    {{-- Hinweis-Kasten Datenschutz / Rechtsrahmen --}}
                    <div class="alert alert-info small mb-3" role="note">
                        <strong>Geheime Abgabe:</strong> Ihre Einzelstimme ist für niemanden außer Ihnen
                        nachvollziehbar (kein Personenbezug an der Stimme).
                        <br>
                        <em>Hinweis:</em> vollständige Unverkettbarkeit auch gegen Administratoren erst mit
                        dem Krypto-Härtungspfad.
                        <br>
                        <strong>Rechtsrahmen:</strong> DSG-EKD; gesetzliche Wahlen (Heimbeirat/MAV) sind
                        geheim — § 5 HeimmwV / § 11 MVG-EKD.
                    </div>

                    @if ($a->beschreibung)
                        <p class="text-muted small">{{ $a->beschreibung }}</p>
                    @endif

                    @php
                        $totalStimmen = collect($erg['optionen'])->sum('stimmen');
                    @endphp

                    @foreach ($erg['optionen'] as $optId => $opt)
                        @php
                            $pct = $totalStimmen > 0 ? round($opt['stimmen'] / $totalStimmen * 100) : 0;
                        @endphp
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ $opt['text'] }}</span>
                                <span><strong>{{ $opt['stimmen'] }}</strong> Stimmen ({{ $pct }} %)</span>
                            </div>
                            <div class="progress" style="height: 12px;">
                                <div class="progress-bar bg-primary" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach

                    @if ($erg['namentlich'] !== null)
                        <div class="mt-3">
                            <strong class="small">Namentliches Ergebnis:</strong>
                            @foreach ($erg['namentlich'] as $optId => $namen)
                                @if (isset($erg['optionen'][$optId]))
                                    <div class="small mt-1">
                                        <em>{{ $erg['optionen'][$optId]['text'] }}:</em>
                                        {{ empty($namen) ? '—' : implode(', ', $namen) }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @endforeach
</div>
