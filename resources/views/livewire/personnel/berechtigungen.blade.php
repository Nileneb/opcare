<div>
    <div class="page-head">
        <div><p class="kicker">Personal · Berechtigungen</p><h1>Berechtigungsmatrix &amp; Delegation</h1>
            <p class="lead">Wer darf welche Tätigkeit (Qualifikation + Kompetenz + Delegation, § 4 PflBG / § 132a SGB V).</p></div>
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Berechtigungsmatrix je Mitarbeiter:in</h3></div>
        <div class="field"><label>Mitarbeiter:in</label>
            <select wire:model.live="selectedUser"><option value="">– wählen –</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
        </div>
        @if ($selectedUser)
            <table class="data-table">
                <thead><tr><th>Tätigkeit</th><th>Bereich</th><th>Anforderung</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($taetigkeiten as $t)
                        @php($grund = $matrix[$t->id] ?? null)
                        <tr>
                            <td><b>{{ $t->label }}</b></td>
                            <td>{{ ucfirst($t->bereich) }}</td>
                            <td class="muted" style="font-size:.82em">
                                @if ($t->vorbehaltsaufgabe)Vorbehalt §4 PflBG@elseif ($t->nur_fachkraft)Fachkraft@else Hilfskraft+@endif
                                @if ($t->erforderlicheKompetenz) · {{ $t->erforderlicheKompetenz->name }}@endif
                                @if ($t->arzt_anordnung_noetig) · Delegation@endif
                            </td>
                            <td>
                                @if ($grund === null)<span class="badge green">darf</span>
                                @else<span class="badge red" title="{{ $grund }}">✕ {{ $grund }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Mitarbeiter:in wählen, um die Berechtigungen zu sehen.</p>
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h3>Delegationen</h3><span class="badge gray">{{ $delegationen->count() }} aktiv</span></div>
        @forelse ($delegationen as $d)
            <div class="qm-anf" style="padding:6px 0;border-bottom:1px solid var(--line-cool)">
                <span class="badge {{ $d->ampel() }}">●</span>
                <b>{{ $d->taetigkeit?->label }}</b> → {{ $d->nehmer?->name }}
                <span class="muted">angeordnet von {{ $d->anordner_name }}</span>
                @if ($d->gueltig_bis)<span class="muted">· bis {{ $d->gueltig_bis->format('d.m.Y') }}</span>@endif
                <button class="btn btn-ghost btn-sm" style="margin-left:auto" wire:click="widerrufen({{ $d->id }})" wire:confirm="Delegation widerrufen?">widerrufen</button>
            </div>
        @empty
            <p class="empty">Keine aktiven Delegationen.</p>
        @endforelse

        <form wire:submit="delegieren" style="margin-top:12px;border-top:1px solid var(--line-cool);padding-top:12px">
            <p class="kicker">Tätigkeit delegieren (Arzt → Pflege / Betreiber → befähigte Person)</p>
            <div class="form-row-2">
                <div class="field"><label>Tätigkeit</label>
                    <select wire:model="d_taetigkeit"><option value="">– wählen –</option>@foreach ($taetigkeiten as $t)<option value="{{ $t->id }}">{{ $t->label }}</option>@endforeach</select>
                    @error('d_taetigkeit')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>An (durchführende Person)</label>
                    <select wire:model="d_nehmer"><option value="">– wählen –</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                    @error('d_nehmer')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-row-3">
                <div class="field"><label>Anordnende:r (Arzt/Betreiber)</label><input type="text" wire:model="d_anordner" />@error('d_anordner')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Gültig bis (optional)</label><input type="date" wire:model="d_gueltig_bis" />@error('d_gueltig_bis')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Nachweis/Notiz</label><input type="text" wire:model="d_notiz" placeholder="z. B. Spritzenschein, Einweisung" /></div>
            </div>
            <button class="btn btn-primary btn-sm">+ Delegation</button>
        </form>
    </div>
</div>
