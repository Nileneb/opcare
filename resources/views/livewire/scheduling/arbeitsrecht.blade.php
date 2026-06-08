<div>
    <div class="page-head">
        <div><p class="kicker">Planung · Compliance</p><h1>Arbeitsrecht-Regeln (ArbZG)</h1>
            <p class="lead">Editierbares Regelwerk v{{ $version }} — Schwellwerte, Schwere und Aktivierung anpassbar.
                Jede Regel verlinkt den amtlichen Gesetzestext.</p></div>
        <div><a href="{{ route('dienstplan') }}" class="btn btn-ghost" wire:navigate>← Dienstplan</a></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <p class="muted" style="margin-bottom:16px">Die Regeln sind aus dem Arbeitszeitgesetz abgeleitet und dienen der
        Planungs-Unterstützung — keine Rechtsberatung. Abweichungen aus Tarif- oder Betriebsvereinbarung lassen sich
        hier hinterlegen. § 14 ist die Rechtsgrundlage für dokumentierte Abweichungen im Dienstplan.</p>

    @foreach ($rules as $rule)
        <div class="card">
            <div class="card-head">
                <h3>{{ $rule->label }} <span class="badge gray">{{ $rule->paragraph }}</span></h3>
                <a href="{{ $rule->gesetz_url }}" target="_blank" rel="noopener" class="plan-law">Gesetzestext ↗</a>
            </div>
            <blockquote class="gesetz-zitat">{{ $rule->gesetz_zitat }}</blockquote>

            <div class="form-row">
                <div class="field"><label>Schwere bei Verstoß</label>
                    <select wire:model="edits.{{ $rule->id }}.severity">
                        @foreach ($severities as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>Aktiv</label>
                    <label style="font-weight:400"><input type="checkbox" wire:model="edits.{{ $rule->id }}.aktiv" /> wird im Dienstplan geprüft</label>
                </div>
            </div>

            @if (! empty($rule->params))
                <div class="form-row-3">
                    @foreach ($rule->params as $key => $value)
                        <div class="field"><label>{{ str_replace('_', ' ', $key) }}</label>
                            <input type="number" min="0" max="168" wire:model="edits.{{ $rule->id }}.params.{{ $key }}" />
                            @error("edits.{$rule->id}.params.{$key}")<span class="err">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="field"><label>Notiz (einrichtungsintern)</label>
                <textarea wire:model="edits.{{ $rule->id }}.note" rows="2"></textarea>
                @error("edits.{$rule->id}.note")<span class="err">{{ $message }}</span>@enderror
            </div>

            <div style="display:flex;gap:8px">
                <button class="btn btn-primary btn-sm" wire:click="speichern({{ $rule->id }})">Speichern</button>
                <button class="btn btn-ghost btn-sm" wire:click="zuruecksetzen({{ $rule->id }})"
                        wire:confirm="Regel auf den ArbZG-Standard zurücksetzen?">Auf Standard zurücksetzen</button>
            </div>
        </div>
    @endforeach

    <h2 style="margin-top:28px">Betreuungsschlüssel (§ 113c SGB XI)</h2>
    <p class="muted" style="margin-bottom:12px">Personalanhaltswerte v{{ $pawVersion }} (bundeseinheitlich, Code-Konstante).
        Hier die einrichtungsspezifischen Stellschrauben — Tarif-Wochenstunden, Fachkraftquote, Nachtdienst-Schlüssel
        (landesrechtlich) und der PAW-Multiplikator (private Häuser mit mehr Personal &gt; 1,0).</p>
    <div class="card">
        <div class="form-row-2">
            <div class="field"><label>Tarif-Wochenstunden (1 VZÄ)</label><input type="number" step="0.5" wire:model="sc_wochenstunden" />@error('sc_wochenstunden')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>Fachkraftquote (Anteil, z. B. 0,5)</label><input type="number" step="0.01" wire:model="sc_fachkraftquote" />@error('sc_fachkraftquote')<span class="err">{{ $message }}</span>@enderror</div>
        </div>
        <div class="form-row-2">
            <div class="field"><label>Nachtdienst: Bewohner je Fachkraft (landesrechtlich)</label><input type="number" wire:model="sc_nachtdienst" />@error('sc_nachtdienst')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>PAW-Multiplikator</label><input type="number" step="0.05" wire:model="sc_multiplikator" />@error('sc_multiplikator')<span class="err">{{ $message }}</span>@enderror</div>
        </div>
        <button class="btn btn-primary btn-sm" wire:click="staffingSpeichern">Speichern</button>
    </div>

    <h2 style="margin-top:28px">Belastungs-Features (Mode B/C) — Freischaltung</h2>
    <p class="muted" style="margin-bottom:12px">Die individuelle Selbst-Ampel und Selbst-Überlastungsmeldung (Mode B/C)
        erfordern einen gültigen Mitarbeitenden-Beschluss (§ 87 Abs. 1 Nr. 6 BetrVG). Ohne Beschluss bleibt der
        Energiebarometer-Screen gesperrt.</p>
    <div class="card">
        <div class="card-head">
            <h3>Status</h3>
            @if ($aktiveFreischaltung)
                <span class="badge green">aktiv</span>
            @else
                <span class="badge gray">inaktiv</span>
            @endif
        </div>
        @if ($aktiveFreischaltung)
            <p>Freigeschaltet am {{ $aktiveFreischaltung->freigeschaltet_am->format('d.m.Y') }}
                @if ($aktiveFreischaltung->freigeschaltetVon) von {{ $aktiveFreischaltung->freigeschaltetVon->name }}@endif
                auf Basis des Beschlusses: <strong>{{ $aktiveFreischaltung->abstimmung->titel }}</strong></p>
            <button class="btn btn-ghost btn-sm" wire:click="belastungZuruecknehmen"
                    wire:confirm="Freischaltung der Selbst-Ampel zurücknehmen?">
                Zurücknehmen
            </button>
        @else
            @if ($geschlosseneBeschluesse->isEmpty())
                <p class="empty">Kein geschlossener Mitarbeitenden-Beschluss vorhanden. Bitte zuerst eine Abstimmung
                    (Art: Beschluss, Elektorat: Mitarbeitende) abschließen.</p>
            @else
                <div class="form-row-2" style="margin-bottom:10px">
                    <div class="field">
                        <label>Beschluss wählen</label>
                        <select wire:model.live="freischaltungBeschlussId">
                            <option value="">— Beschluss wählen —</option>
                            @foreach ($geschlosseneBeschluesse as $b)
                                <option value="{{ $b->id }}">{{ $b->titel }} ({{ $b->ende_am?->format('d.m.Y') ?? 'n/a' }})</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($gewaehlterBeschluss && $gewaehlterBeschluss->optionen->isNotEmpty())
                        <div class="field">
                            <label>Zustimmungs-Option (z. B. „Ja")</label>
                            <select wire:model="freischaltungOptionId">
                                <option value="">— Option wählen —</option>
                                @foreach ($gewaehlterBeschluss->optionen as $opt)
                                    <option value="{{ $opt->id }}">{{ $opt->text }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                @if ($freischaltungBeschlussId && $freischaltungOptionId)
                    <button class="btn btn-primary btn-sm" wire:click="belastungFreischalten">
                        Freischalten
                    </button>
                @endif
            @endif
        @endif
    </div>

    <h2 style="margin-top:28px">Belastungs-Index — Gewichte &amp; Schwellen (§ 5 ArbSchG)</h2>
    <p class="muted" style="margin-bottom:12px">Konfiguration des psychischen Belastungsindex je Einrichtung. Gewichte müssen nicht 100 ergeben — der Analyzer normiert automatisch. Schwellen bestimmen ab welchem Score Stufe „Hoch" bzw. „Kritisch" (= meldepflichtig) ausgelöst wird.</p>
    <div class="card">
        <div class="card-head"><h3>Gewichte (0–100)</h3><span class="badge gray">§ 5 Abs. 3 Nr. 6 ArbSchG</span></div>
        <div class="form-row-2">
            <div class="field"><label>Pflegelast</label><input type="number" min="0" max="100" wire:model="bk_gewicht_pflegelast" />@error('bk_gewicht_pflegelast')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>Personaldeckung</label><input type="number" min="0" max="100" wire:model="bk_gewicht_deckung" />@error('bk_gewicht_deckung')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>Spitzenzeit</label><input type="number" min="0" max="100" wire:model="bk_gewicht_spitzenzeit" />@error('bk_gewicht_spitzenzeit')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>Ergonomie</label><input type="number" min="0" max="100" wire:model="bk_gewicht_ergonomie" />@error('bk_gewicht_ergonomie')<span class="err">{{ $message }}</span>@enderror</div>
        </div>
        <div class="card-head" style="margin-top:1rem"><h3>Schwellen</h3></div>
        <div class="form-row-2">
            <div class="field"><label>Schwelle „Hoch" (Score ≥)</label><input type="number" min="0" max="100" wire:model="bk_schwelle_hoch" />@error('bk_schwelle_hoch')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>Schwelle „Kritisch" (Score ≥)</label><input type="number" min="0" max="100" wire:model="bk_schwelle_kritisch" />@error('bk_schwelle_kritisch')<span class="err">{{ $message }}</span>@enderror</div>
        </div>
        <button class="btn btn-primary btn-sm" wire:click="belastungsKonfigSpeichern">Speichern</button>
    </div>

    <h2 style="margin-top:28px">Ergonomie-Empfehlungen (Schichtgestaltung)</h2>
    <p class="muted" style="margin-bottom:12px">Arbeitswissenschaftliche Empfehlungen (§ 6 ArbZG, BAuA/BGHM/DGAUM) —
        der harten ArbZG-Prüfung nachgelagert. An-/abschaltbar, Schwellwerte anpassbar.</p>
    @foreach ($qualityRules as $rule)
        <div class="card">
            <div class="card-head"><h3>{{ $rule->label }}</h3>
                <label style="font-weight:400"><input type="checkbox" wire:model="qedits.{{ $rule->id }}.aktiv" /> aktiv</label>
            </div>
            <p class="muted" style="font-size:.85em">{{ $rule->quelle }}</p>
            <div class="form-row-3">
                <div class="field"><label>Schwere</label>
                    <select wire:model="qedits.{{ $rule->id }}.severity"><option value="warnung">Warnung</option><option value="hinweis">Hinweis</option></select>
                </div>
                @foreach ($rule->params as $name => $val)
                    <div class="field"><label>{{ $name }}</label><input type="number" step="0.5" wire:model="qedits.{{ $rule->id }}.params.{{ $name }}" /></div>
                @endforeach
            </div>
            <button class="btn btn-primary btn-sm" wire:click="qSpeichern({{ $rule->id }})">Speichern</button>
        </div>
    @endforeach
</div>
