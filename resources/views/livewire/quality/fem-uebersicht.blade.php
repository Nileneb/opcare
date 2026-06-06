<div>
    <div class="page-head">
        <div><p class="kicker">Qualität · Bewohnerschutz</p><h1>Freiheitsentziehende Maßnahmen (§ 1831 BGB)</h1>
            <p class="lead">Genehmigungs- und Fristen-Workflow mit Ampel; mildere Mittel dokumentationspflichtig.</p></div>
        @if ($handlungsbedarf > 0)
            <span class="badge red" style="align-self:center">{{ $handlungsbedarf }} mit Handlungsbedarf</span>
        @else
            <span class="badge green" style="align-self:center">keine offenen Fristen</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="grid-2" style="align-items:start;gap:18px">
        <div class="card">
            <div class="card-head"><h3>FEM-Fälle</h3><span class="badge gray">{{ $faelle->count() }}</span></div>
            @forelse ($faelle as $f)
                <div class="qm-anf" style="padding:6px 0;border-bottom:1px solid var(--line-cool)">
                    <button class="btn {{ $selected === $f->id ? 'btn-primary' : 'btn-ghost' }} btn-sm" wire:click="$set('selected', {{ $f->id }})">
                        {{ $f->resident?->name }} · {{ $f->art->label() }}
                    </button>
                    <span class="badge {{ $f->ampel() }}">{{ $f->status() }}</span>
                    @if ($f->gueltig_bis && $f->aktiv())<span class="muted" style="font-size:.8em">bis {{ $f->gueltig_bis->format('d.m.Y') }}</span>@endif
                </div>
            @empty
                <p class="empty">Keine FEM erfasst.</p>
            @endforelse

            <form wire:submit="anlegen" style="margin-top:12px;border-top:1px solid var(--line-cool);padding-top:12px">
                <p class="kicker">Neue FEM anlegen</p>
                <div class="field"><label>Bewohner:in</label>
                    <select wire:model="f_resident"><option value="">– wählen –</option>@foreach ($bewohner as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    @error('f_resident')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="form-row-2">
                    <div class="field"><label>Art</label><select wire:model="f_art">@foreach ($arten as $a)<option value="{{ $a->value }}">{{ $a->label() }}</option>@endforeach</select></div>
                    <div class="field"><label>Anordnender Arzt</label><input type="text" wire:model="f_arzt" />@error('f_arzt')<span class="err">{{ $message }}</span>@enderror</div>
                </div>
                <div class="field"><label>Anlass / Gefährdung</label><input type="text" wire:model="f_anlass" placeholder="z. B. wiederholte nächtliche Stürze" />@error('f_anlass')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Geprüfte mildere Mittel</label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        @foreach ($milderOptionen as $m)<label style="font-weight:400;white-space:nowrap"><input type="checkbox" value="{{ $m }}" wire:model="f_mildere" /> {{ $m }}</label>@endforeach
                    </div>
                </div>
                <div class="field"><label>Warum reichen mildere Mittel nicht? (Pflicht)</label><input type="text" wire:model="f_mildere_begruendung" />@error('f_mildere_begruendung')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Status</label><select wire:model.live="f_einwilligung">@foreach ($einwilligungen as $e)<option value="{{ $e->value }}">{{ $e->label() }}</option>@endforeach</select></div>
                @if ($f_einwilligung === 'genehmigt')
                    <div class="form-row-3">
                        <div class="field"><label>Aktenzeichen</label><input type="text" wire:model="f_aktenzeichen" />@error('f_aktenzeichen')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Beschluss am</label><input type="date" wire:model="f_beschluss_am" />@error('f_beschluss_am')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Gültig bis</label><input type="date" wire:model="f_gueltig_bis" />@error('f_gueltig_bis')<span class="err">{{ $message }}</span>@enderror</div>
                    </div>
                    <div class="field"><label>Gericht</label><input type="text" wire:model="f_gericht" placeholder="Amtsgericht …" /></div>
                @endif
                <button class="btn btn-ghost btn-sm">+ FEM</button>
            </form>
        </div>

        <div>
            @if ($fall)
                <div class="card">
                    <div class="card-head"><h3>{{ $fall->resident?->name }} · {{ $fall->art->label() }}</h3>
                        <span class="badge {{ $fall->ampel() }}">{{ $fall->status() }}</span>
                    </div>
                    <p><b>Anlass:</b> {{ $fall->anlass }}</p>
                    <p class="muted"><b>Mildere Mittel geprüft:</b> {{ $fall->mildere_mittel ? implode(', ', $fall->mildere_mittel) : '—' }} · {{ $fall->mildere_begruendung }}</p>
                    <p class="muted"><b>Anordnung:</b> {{ $fall->anordnung_arzt }} ({{ $fall->anordnung_am?->format('d.m.Y H:i') }})
                        @if ($fall->aktenzeichen) · <b>Az.</b> {{ $fall->aktenzeichen }}@endif
                        @if ($fall->gueltig_bis) · <b>gültig bis</b> {{ $fall->gueltig_bis->format('d.m.Y') }}@endif</p>
                    @if ($fall->status() === 'ueberpruefung_faellig')<div class="flash" style="background:#fef3c7">Befristung läuft in ≤ 30 Tagen ab — Verlängerung einleiten.</div>@endif
                    @if ($fall->status() === 'abgelaufen')<div class="flash" style="background:#fee2e2">Genehmigung abgelaufen — FEM darf nicht fortgeführt werden!</div>@endif

                    <div class="form-row-2" style="margin-top:10px">
                        <div>
                            <p class="kicker">Dokumente (Attest / Beschluss)</p>
                            @foreach ($dokumente as $d)<div class="muted" style="font-size:.85em">📎 {{ $d->name }}</div>@endforeach
                            <form wire:submit="dokumentHochladen" style="margin-top:6px">
                                <input type="file" wire:model="dokument" />@error('dokument')<span class="err">{{ $message }}</span>@enderror
                                <button class="btn btn-ghost btn-sm">+ Anhängen</button>
                            </form>
                        </div>
                        @if ($fall->aktiv())
                            <div>
                                <p class="kicker">Beenden</p>
                                <form wire:submit="beenden">
                                    <input type="text" wire:model="beend_grund" placeholder="Grund (Indikation entfallen …)" />@error('beend_grund')<span class="err">{{ $message }}</span>@enderror
                                    <button class="btn btn-ghost btn-sm">FEM beenden</button>
                                </form>
                            </div>
                        @else
                            <div><span class="badge gray">beendet {{ $fall->beendet_am?->format('d.m.Y H:i') }}</span><br><span class="muted">{{ $fall->beendigungsgrund }}</span></div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><h3>Überwachungsprotokoll</h3></div>
                    @if ($fall->aktiv())
                        <form wire:submit="protokollieren" class="form-row-3" style="align-items:end">
                            <div class="field"><label>Typ</label><select wire:model="p_typ"><option value="kontrolle">Kontrolle</option><option value="vitalzeichen">Vitalzeichen</option><option value="sonstiges">Sonstiges</option></select></div>
                            <div class="field"><label>Befund</label><input type="text" wire:model="p_befund" placeholder="z. B. ruhig, Haut o. B." /></div>
                            <div class="field"><label style="font-weight:400"><input type="checkbox" wire:model="p_indikation" /> Indikation noch gegeben</label>
                                <button class="btn btn-primary btn-sm">+ Eintrag</button></div>
                        </form>
                    @endif
                    <table class="data-table" style="margin-top:10px">
                        <thead><tr><th>Zeitpunkt</th><th>Typ</th><th>Befund</th><th>Indikation</th><th>von</th></tr></thead>
                        <tbody>
                            @forelse ($protokolle as $p)
                                <tr>
                                    <td>{{ $p->zeitpunkt->format('d.m.Y H:i') }}</td>
                                    <td>{{ ucfirst($p->typ) }}</td>
                                    <td>{{ $p->befund }}</td>
                                    <td>@if ($p->indikation_gegeben === true)<span class="badge green">ja</span>@elseif ($p->indikation_gegeben === false)<span class="badge red">nein</span>@endif</td>
                                    <td class="muted" style="font-size:.8em">{{ $p->dokumentierer?->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><p class="empty">Noch kein Eintrag.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card"><p class="empty">Fall links wählen oder anlegen.</p></div>
            @endif
        </div>
    </div>
</div>
