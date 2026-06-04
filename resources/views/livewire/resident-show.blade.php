<div>
    <a href="{{ route('bewohner') }}" class="back-link" wire:navigate>← Alle Bewohner:innen</a>
    <div class="page-head">
        <div>
            <p class="kicker">Bewohner:in</p>
            <h1>{{ $resident->name }}</h1>
            <p class="lead">
                Zimmer {{ $resident->room?->nummer ?? '—' }}
                @if ($resident->pflegegrad) · Pflegegrad {{ $resident->pflegegrad }} @endif
                · geb. {{ $resident->geburtsdatum?->format('d.m.Y') }}
                · aufgenommen {{ $resident->aufnahme_am?->format('d.m.Y') }}
            </p>
        </div>
        <div style="display:flex;gap:8px">
            <a href="{{ route('medikation.stellplan', $resident) }}" class="btn btn-primary" wire:navigate>💊 Medikation/Stellplan</a>
            <a href="{{ route('medikation.verordnungen', $resident) }}" class="btn" wire:navigate>Verordnungen</a>
            <a href="{{ route('medikation.vitalwerte', $resident) }}" class="btn" wire:navigate>Vitalwerte</a>
            <a href="{{ route('assessment.verlauf', $resident) }}" class="btn" wire:navigate>Assessments</a>
            <a href="{{ route('pflegeplanung') }}" class="btn btn-ghost">Im SIS-Board ansehen</a>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- ===================== STAMMDATEN ===================== --}}
    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h3>Diagnosen (ICD-10)</h3></div>
            @forelse ($resident->diagnoses as $d)
                <div class="chip"><b>{{ $d->icdCode->code }}</b> {{ $d->icdCode->bezeichnung }} <span class="badge {{ $d->art === 'primär' ? 'green' : 'gray' }}" style="margin-left:auto">{{ $d->art }}</span></div>
            @empty <p class="empty">Keine Diagnosen erfasst.</p> @endforelse
            <form wire:submit="addDiagnosis" style="margin-top:14px">
                <div class="form-row">
                    <div class="field"><label>ICD-Code</label>
                        <select wire:model="diag_icd"><option value="">— wählen —</option>
                            @foreach ($icdCodes as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->bezeichnung }}</option>@endforeach
                        </select>@error('diag_icd')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field"><label>Art</label>
                        <select wire:model="diag_art"><option value="primär">primär</option><option value="sekundär">sekundär</option></select>
                    </div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Diagnose</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Krankenkassen</h3></div>
            @forelse ($resident->insurances as $i)
                <div class="chip"><b>{{ $i->healthInsurance->name }}</b> {{ $i->versichertennr }} @if ($i->ist_primaer)<span class="badge green" style="margin-left:auto">primär</span>@endif</div>
            @empty <p class="empty">Keine Kasse erfasst.</p> @endforelse
            <form wire:submit="addInsurance" style="margin-top:14px">
                <div class="form-row">
                    <div class="field"><label>Kasse</label>
                        <select wire:model="ins_id"><option value="">— wählen —</option>
                            @foreach ($insurances as $ins)<option value="{{ $ins->id }}">{{ $ins->name }}</option>@endforeach
                        </select>@error('ins_id')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field"><label>Versichertennr.</label><input type="text" wire:model="ins_nr" /></div>
                </div>
                <label style="display:flex;gap:8px;align-items:center;font-size:0.85em;margin-bottom:10px"><input type="checkbox" wire:model="ins_primary" style="width:auto" /> primäre Kasse</label>
                <button class="btn btn-ghost btn-sm">+ Kasse</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Betreuer:innen</h3></div>
            @forelse ($resident->custodians as $c)
                <div class="chip"><b>{{ $c->name }}</b> @if ($c->umfang)· {{ $c->umfang }}@endif @if ($c->kontakt)<span class="muted" style="margin-left:auto">{{ $c->kontakt }}</span>@endif</div>
            @empty <p class="empty">Keine Betreuung erfasst.</p> @endforelse
            <form wire:submit="addCustodian" style="margin-top:14px">
                <div class="field"><label>Name</label><input type="text" wire:model="cust_name" />@error('cust_name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="form-row">
                    <div class="field"><label>Umfang</label><input type="text" wire:model="cust_umfang" placeholder="z. B. Gesundheitsfürsorge" /></div>
                    <div class="field"><label>Kontakt</label><input type="text" wire:model="cust_kontakt" /></div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Betreuer:in</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Behandelnde Ärzt:innen</h3></div>
            @forelse ($resident->physicians as $p)
                <div class="chip"><b>{{ $p->name }}</b> @if ($p->fachrichtung)· {{ $p->fachrichtung }}@endif</div>
            @empty <p class="empty">Keine Ärzt:innen zugeordnet.</p> @endforelse
            <form wire:submit="attachPhysician" style="margin-top:14px">
                <div class="field"><label>Arzt/Ärztin</label>
                    <select wire:model="phys_id"><option value="">— wählen —</option>
                        @foreach ($physicians as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->fachrichtung }})</option>@endforeach
                    </select>@error('phys_id')<span class="err">{{ $message }}</span>@enderror
                </div>
                <button class="btn btn-ghost btn-sm">+ Zuordnen</button>
            </form>
        </div>
    </div>

    {{-- ===================== SIS ===================== --}}
    <div class="card">
        <div class="card-head"><h3>SIS®-Informationssammlung</h3></div>
        @php $sis = $resident->sisAssessments->first(); @endphp
        @if ($sis)
            <p class="muted" style="margin-top:0">Aktive Erhebung v{{ $sis->version }} vom {{ $sis->erstellt_am?->format('d.m.Y') }}@if ($sis->eingangsfrage) · „{{ $sis->eingangsfrage }}"@endif</p>
            <div class="chip-list">
                @foreach ($sis->topicFields as $tf)
                    <div class="chip"><b>{{ $tf->themenfeld->label() }}:</b> {{ $tf->freitext }}</div>
                @endforeach
                @if ($sis->riskItems->isNotEmpty())
                    <div class="chip">Risiken: @foreach ($sis->riskItems as $ri)<span class="badge red" style="margin-right:4px">{{ ucfirst($ri->risiko->value) }}</span>@endforeach</div>
                @endif
            </div>
        @else
            <p class="empty">Noch keine SIS-Erhebung. Lege unten die erste an.</p>
        @endif

        <div class="section-label" style="margin-top:18px">Neue SIS-Erhebung anlegen</div>
        <form wire:submit="createSis">
            <x-voice-field model="sis_eingangsfrage" label="Eingangsfrage / Sichtweise der/des Pflegebedürftigen" :rows="2" />
            @foreach ($topicFields as $f)
                <x-voice-field wire:key="vf-{{ $f->value }}" model="sis_felder.{{ $f->value }}" :label="$f->label()" :context="$f->label()" :rows="2" />
            @endforeach
            <div class="field"><label>Eingeschätzte Risiken</label>
                <div style="display:flex;flex-wrap:wrap;gap:10px">
                    @foreach ($riskTypes as $rt)
                        <label style="display:flex;gap:6px;align-items:center;font-size:0.88em"><input type="checkbox" wire:model="sis_risiken" value="{{ $rt->value }}" style="width:auto" /> {{ ucfirst($rt->value) }}</label>
                    @endforeach
                </div>
            </div>
            <button class="btn btn-primary">SIS anlegen</button>
        </form>
    </div>

    {{-- ===================== MASSNAHMEN ===================== --}}
    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h3>Maßnahmenplan</h3></div>
            @forelse ($resident->careMeasures as $m)
                <div class="chip"><div><b>{{ $m->themenfeld->label() }}</b><br>{{ $m->beschreibung }}@if ($m->ziel)<br><span class="muted">Ziel: {{ $m->ziel }}</span>@endif</div></div>
            @empty <p class="empty">Keine Maßnahmen geplant.</p> @endforelse
            <form wire:submit="addMeasure" style="margin-top:14px">
                <div class="field"><label>Lebensbereich</label>
                    <select wire:model="m_themenfeld">@foreach ($topicFields as $f)<option value="{{ $f->value }}">{{ $f->label() }}</option>@endforeach</select>
                </div>
                <x-voice-field model="m_beschreibung" label="Maßnahme" :context="'Maßnahme'" :rows="2" />
                <div class="field"><label>Ziel</label><input type="text" wire:model="m_ziel" /></div>
                <button class="btn btn-ghost btn-sm">+ Maßnahme</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Evaluation</h3></div>
            <form wire:submit="addEvaluation">
                <div class="field"><label>Maßnahme</label>
                    <select wire:model="e_measure"><option value="">— wählen —</option>
                        @foreach ($measures as $m)<option value="{{ $m->id }}">{{ $m->beschreibung }}</option>@endforeach
                    </select>@error('e_measure')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="form-row">
                    <div class="field"><label>Zielerreichung</label>
                        <select wire:model="e_zielerreichung"><option value="erreicht">erreicht</option><option value="teilweise">teilweise</option><option value="nicht">nicht</option></select>
                    </div>
                    <div class="field"><label>Anlass</label><input type="text" wire:model="e_anlass" placeholder="z. B. Quartalsevaluation" /></div>
                </div>
                <button class="btn btn-ghost btn-sm">+ Evaluation</button>
            </form>
        </div>
    </div>

    {{-- ===================== BERICHTE ===================== --}}
    <div class="card">
        <div class="card-head"><h3>Berichteblatt</h3></div>
        <form wire:submit="addReport">
            <div class="form-row-3">
                <div class="field"><label>Datum/Zeit</label><input type="datetime-local" wire:model="r_datum" />@error('r_datum')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Schicht</label><select wire:model="r_schicht"><option value="frueh">Früh</option><option value="spaet">Spät</option><option value="nacht">Nacht</option></select></div>
            </div>
            <x-voice-field model="r_text" label="Eintrag" :context="'Pflegebericht'" :rows="2" />
            <button class="btn btn-primary btn-sm">Bericht speichern</button>
        </form>
    </div>
</div>
