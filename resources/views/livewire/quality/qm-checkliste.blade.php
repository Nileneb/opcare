<div>
    <div class="page-head">
        <div><p class="kicker">Qualität · Compliance</p><h1>QM-Norm-Checkliste</h1>
            <p class="lead">Normanforderungen nach Bereich — Status, Nachweis und Zuständigkeit pflegen.
                Keine Rechtsberatung; Startkatalog je Einrichtung erweiterbar.</p></div>
        <div><a href="{{ route('controlling') }}" class="btn btn-ghost" wire:navigate>← Controlling</a></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    @php $proz = $totalGesamt ? round($erledigtGesamt / $totalGesamt * 100) : 0; @endphp
    <div class="card">
        <div class="card-head"><h3>Erfüllungsgrad gesamt</h3><span class="badge {{ $proz == 100 ? 'green' : ($proz >= 50 ? 'amber' : 'red') }}">{{ $erledigtGesamt }} / {{ $totalGesamt }} ({{ $proz }} %)</span></div>
        <div class="qm-bar"><span style="width:{{ $proz }}%"></span></div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Eigene Anforderung ergänzen</h3></div>
        <form wire:submit="anlegen">
            <div class="form-row-3">
                <div class="field"><label>Bereich</label>
                    <select wire:model="neu_bereich">
                        <option value="">– wählen –</option>
                        @foreach ($bereiche as $b)<option value="{{ $b->value }}">{{ $b->label() }}</option>@endforeach
                    </select>@error('neu_bereich')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Norm/Quelle</label><input type="text" wire:model="neu_norm" placeholder="z. B. § 36 IfSG" />@error('neu_norm')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="field"><label>Anforderung</label><input type="text" wire:model="neu_anforderung" placeholder="Was ist nachzuweisen?" />@error('neu_anforderung')<span class="err">{{ $message }}</span>@enderror</div>
            <button class="btn btn-ghost btn-sm">+ Anforderung</button>
        </form>
    </div>

    @foreach ($gruppen as $g)
        @php $bp = $g['total'] ? round($g['erledigt'] / $g['total'] * 100) : 0; @endphp
        <div class="card">
            <div class="card-head">
                <h3>{{ $g['bereich']->label() }} <span class="badge gray">{{ $g['bereich']->ebene() }}</span></h3>
                <span class="badge {{ $bp == 100 ? 'green' : ($bp >= 50 ? 'amber' : 'red') }}">{{ $g['erledigt'] }} / {{ $g['total'] }}</span>
            </div>
            <div class="qm-bar"><span style="width:{{ $bp }}%"></span></div>

            @foreach ($g['items'] as $r)
                <div class="qm-item">
                    <div class="qm-anf">
                        <span class="badge gray">{{ $r->norm }}</span>
                        <span>{{ $r->anforderung }}</span>
                        @if ($r->gesetz_url)<a href="{{ $r->gesetz_url }}" target="_blank" rel="noopener" class="plan-law">Gesetzestext ↗</a>@endif
                        @unless ($r->schluessel)<button class="btn btn-ghost btn-sm" wire:click="entfernen({{ $r->id }})" wire:confirm="Eigene Anforderung entfernen?" style="margin-left:auto">✕</button>@endunless
                    </div>
                    <div class="form-row-3">
                        <div class="field"><label>Status</label>
                            <select wire:model="edits.{{ $r->id }}.status">
                                @foreach ($statusOptionen as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field"><label>Zuständig</label><input type="text" wire:model="edits.{{ $r->id }}.zustaendig" /></div>
                        <div class="field"><label>Fällig am</label><input type="date" wire:model="edits.{{ $r->id }}.faellig_am" /></div>
                    </div>
                    <div class="field"><label>Nachweis / Notiz</label><textarea wire:model="edits.{{ $r->id }}.nachweis" rows="1"></textarea></div>
                    <button class="btn btn-primary btn-sm" wire:click="speichern({{ $r->id }})">Speichern</button>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
