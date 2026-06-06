<div>
    <div class="page-head">
        <div><p class="kicker">Personal · Qualifikation</p><h1>Skill-Baum &amp; Kompetenzen</h1>
            <p class="lead">Kompetenz-Katalog mit Voraussetzungen; erworbene Kompetenzen je Mitarbeiter:in mit Fälligkeits-Ampel.</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="grid-2" style="align-items:start;gap:18px">
        <div class="card">
            <div class="card-head"><h3>Kompetenz-Katalog</h3></div>
            @foreach ($typen as $typ)
                @php($gruppe = $kompetenzenNachTyp[$typ->value] ?? collect())
                @if ($gruppe->isNotEmpty())
                    <p class="kicker" style="margin:10px 0 4px"><span class="badge {{ $typ->badge() }}">{{ $typ->label() }}</span></p>
                    @foreach ($gruppe as $k)
                        <div class="qm-anf" style="padding:5px 0;border-bottom:1px solid var(--line-cool)">
                            <b>{{ $k->name }}</b>
                            @if ($k->ist_fachkraft)<span class="badge green" title="begründet Fachkraft-Status">Fachkraft</span>@endif
                            @if ($k->rechtsbasis)<span class="muted" style="font-size:.8em">· {{ $k->rechtsbasis }}</span>@endif
                            @if ($k->umfang_stunden)<span class="muted" style="font-size:.8em">· {{ $k->umfang_stunden }} h</span>@endif
                            @if ($k->gueltigkeit_monate)<span class="badge gray" title="Gültigkeit">{{ $k->gueltigkeit_monate }} Mon.</span>@endif
                            @if ($k->auffrischung_monate)<span class="badge gray" title="Auffrischung">↻ {{ $k->auffrischung_monate }} Mon.</span>@endif
                            @if ($k->voraussetzungen->isNotEmpty())<br><span class="muted" style="font-size:.78em">setzt voraus: {{ $k->voraussetzungen->pluck('name')->implode(', ') }}</span>@endif
                        </div>
                    @endforeach
                @endif
            @endforeach
        </div>

        <div class="card">
            <div class="card-head"><h3>Kompetenzen je Mitarbeiter:in</h3></div>
            <div class="field"><label>Mitarbeiter:in</label>
                <select wire:model.live="selectedUser"><option value="">– wählen –</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
            </div>
            @if ($selectedUser)
                @forelse ($erworben as $mk)
                    <div class="qm-anf" style="padding:6px 0;border-bottom:1px solid var(--line-cool)">
                        <span class="badge {{ $mk->ampel() }}" title="{{ $mk->status() }}">●</span>
                        <b>{{ $mk->kompetenz?->name }}</b>
                        <span class="muted">seit {{ $mk->erworben_am->format('d.m.Y') }}</span>
                        @if ($mk->gueltig_bis)<span class="muted">· gültig bis {{ $mk->gueltig_bis->format('d.m.Y') }}</span>@endif
                        <button class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="entziehen({{ $mk->id }})" wire:confirm="Kompetenz entziehen?">✕</button>
                    </div>
                @empty
                    <p class="empty">Noch keine Kompetenzen erfasst.</p>
                @endforelse

                <form wire:submit="erteilen" style="margin-top:12px;border-top:1px solid var(--line-cool);padding-top:12px">
                    <p class="kicker">Kompetenz erteilen</p>
                    <div class="form-row-2">
                        <div class="field"><label>Kompetenz</label>
                            <select wire:model="g_kompetenz"><option value="">– wählen –</option>
                                @foreach ($kompetenzenNachTyp as $gruppe)@foreach ($gruppe as $k)<option value="{{ $k->id }}">{{ $k->name }}</option>@endforeach @endforeach
                            </select>
                            @error('g_kompetenz')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field"><label>Erworben am</label><input type="date" wire:model="g_datum" />@error('g_datum')<span class="err">{{ $message }}</span>@enderror</div>
                    </div>
                    <button class="btn btn-primary btn-sm">+ Kompetenz</button>
                </form>
            @else
                <p class="empty">Mitarbeiter:in wählen.</p>
            @endif
        </div>
    </div>
</div>
