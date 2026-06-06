<div>
    <div class="page-head">
        <div><p class="kicker">Qualität · Hygiene & Infektionsschutz</p><h1>Hygiene & MRE-Surveillance</h1>
            <p class="lead">Einrichtungsspezifischer Hygieneplan (§ 23 Abs. 5 IfSG) mit Revisions-Ampel und die
                fortlaufende Aufzeichnung resistenter Erreger und nosokomialer Infektionen (§ 23 Abs. 4 IfSG).
                Meldepflichtige Erreger (§§ 6/7 IfSG) werden bis zur dokumentierten Meldung rot geführt.</p></div>
        @if ($offeneMeldungen > 0)
            <span class="badge red" style="align-self:center">{{ $offeneMeldungen }} Meldung(en) offen</span>
        @elseif ($aktiveBefunde > 0)
            <span class="badge amber" style="align-self:center">{{ $aktiveBefunde }} aktive Befunde</span>
        @else
            <span class="badge green" style="align-self:center">keine aktiven Befunde</span>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Hygieneplan (§ 23 IfSG)</h3></div>
        <table class="data">
            <thead><tr><th>Titel</th><th>Version</th><th>Freigabe</th><th>Nächste Revision</th><th></th></tr></thead>
            <tbody>
                @forelse ($plaene as $p)
                    <tr>
                        <td><b>{{ $p->titel }}</b></td>
                        <td>{{ $p->version }}</td>
                        <td>
                            @if ($p->freigegeben_am)
                                <span class="badge green">{{ $p->freigegeben_am->format('d.m.Y') }}</span>
                            @else
                                <span class="badge red">Entwurf</span>
                                <button class="btn btn-ghost btn-sm" wire:click="planFreigeben({{ $p->id }})">freigeben</button>
                            @endif
                        </td>
                        <td>@if ($p->naechsteRevision())<span class="badge {{ $p->ampel() }}">{{ $p->naechsteRevision()->format('d.m.Y') }}</span>@else<span class="muted">—</span>@endif</td>
                        <td><button class="btn btn-ghost btn-sm" wire:click="planLoeschen({{ $p->id }})" wire:confirm="Hygieneplan entfernen?">entfernen</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Noch kein Hygieneplan hinterlegt.</td></tr>
                @endforelse
            </tbody>
        </table>
        <form wire:submit="planAnlegen" style="margin-top:12px">
            <div class="form-row-3">
                <div class="field"><label>Titel *</label><input type="text" wire:model="p_titel" placeholder="z. B. Hygieneplan Wohnbereich 1" />@error('p_titel')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Version</label><input type="text" wire:model="p_version" />@error('p_version')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Revisions-Intervall (Monate)</label><input type="number" wire:model="p_intervall" />@error('p_intervall')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="field"><label>Inhalt / Verweis</label><textarea wire:model="p_inhalt" rows="2" placeholder="Geltungsbereich, Reinigungs-/Desinfektionsplan, Maßnahmen …"></textarea></div>
            <button class="btn btn-primary btn-sm">+ Hygieneplan (Entwurf)</button>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h3>MRE-/Infektions-Surveillance (§ 23 Abs. 4 IfSG)</h3></div>
        <table class="data">
            <thead><tr><th>Bewohner:in</th><th>Erreger</th><th>Art</th><th>Festgestellt</th><th>Meldung</th><th></th></tr></thead>
            <tbody>
                @forelse ($befunde as $b)
                    <tr>
                        <td><b>{{ $b->resident->name }}</b></td>
                        <td><span class="badge {{ $b->ampel() }}">{{ $b->erreger->label() }}</span>@if ($b->erreger->istMre())<br><span class="muted" style="font-size:.75em">MRE · KRINKO</span>@endif</td>
                        <td>{{ $b->art->label() }}</td>
                        <td>{{ $b->festgestellt_am->format('d.m.Y') }}@if ($b->aufgehoben_am)<br><span class="muted" style="font-size:.75em">aufgehoben {{ $b->aufgehoben_am->format('d.m.Y') }}</span>@endif</td>
                        <td>
                            @if ($b->meldepflichtig)
                                @if ($b->gemeldet_am)
                                    <span class="badge green">gemeldet {{ $b->gemeldet_am->format('d.m.Y') }}</span>
                                @else
                                    <span class="badge red">meldepflichtig</span>
                                    <button class="btn btn-ghost btn-sm" wire:click="befundGemeldet({{ $b->id }})">gemeldet</button>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($b->aktiv())
                                <button class="btn btn-ghost btn-sm" wire:click="befundAufheben({{ $b->id }})" wire:confirm="Befund als saniert/genesen aufheben?">aufheben</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Keine Befunde erfasst.</td></tr>
                @endforelse
            </tbody>
        </table>
        <form wire:submit="befundErfassen" style="margin-top:12px">
            <div class="form-row-3">
                <div class="field"><label>Bewohner:in *</label><select wire:model="b_resident"><option value="">– wählen –</option>@foreach ($residents as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>@error('b_resident')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Erreger</label><select wire:model.live="b_erreger">@foreach ($erregerCases as $e)<option value="{{ $e->value }}">{{ $e->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Art</label><select wire:model="b_art">@foreach ($artCases as $a)<option value="{{ $a->value }}">{{ $a->label() }}</option>@endforeach</select></div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Festgestellt am *</label><input type="date" wire:model="b_festgestellt" />@error('b_festgestellt')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field" style="grid-column:span 2"><label>Maßnahmen (Isolation, Sanierung …)</label><input type="text" wire:model="b_massnahmen" /></div>
            </div>
            <label class="muted" style="display:flex;align-items:center;gap:4px;font-weight:normal;margin-bottom:8px"><input type="checkbox" wire:model="b_meldepflichtig" /> meldepflichtig an das Gesundheitsamt (§§ 6/7 IfSG)</label>
            <button class="btn btn-primary btn-sm">+ Befund erfassen</button>
        </form>
    </div>
</div>
