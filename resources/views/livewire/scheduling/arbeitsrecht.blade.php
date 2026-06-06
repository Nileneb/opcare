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
</div>
