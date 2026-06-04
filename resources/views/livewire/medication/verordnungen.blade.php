<div>
    <div class="page-head">
        <div><p class="kicker">Medikation</p><h1>Verordnungen</h1><p class="lead">{{ $resident->name }}</p></div>
        @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']))
            <a class="btn btn-primary" href="{{ route('medikation.verordnung-anlegen', $resident) }}" wire:navigate>Neue Verordnung</a>
        @endif
    </div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Aktiv</h3></div>
        <table class="data"><thead><tr><th>Präparat / BHP</th><th>Turnus</th><th>Arzt</th><th></th></tr></thead>
            <tbody>
                @forelse ($aktive as $rx)
                    <tr>
                        <td><b>{{ $rx->medProduct?->name ?? $rx->bhp_text }}</b>@if ($rx->medProduct?->btm)<span class="badge badge-warn">BtM</span>@endif</td>
                        <td>
                            @foreach ($rx->schedules as $s)
                                <div class="muted">{{ ucfirst($s->frequenz instanceof \BackedEnum ? $s->frequenz->value : $s->frequenz) }}
                                    @if ($rx->bei_bedarf)
                                        — <button class="btn btn-link" wire:click="bedarfGeben({{ $s->id }}, 1)">Bedarf geben</button>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td>{{ $rx->physician?->name }}</td>
                        <td>
                            @if (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']))
                                <button class="btn btn-link" wire:click="absetzen({{ $rx->id }})"
                                    wire:confirm="Verordnung wirklich absetzen?">Absetzen</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Keine aktiven Verordnungen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($beendete->isNotEmpty())
        <div class="card">
            <div class="card-head"><h3>Beendet / abgesetzt</h3></div>
            <table class="data"><tbody>
                @foreach ($beendete as $rx)
                    <tr><td>{{ $rx->medProduct?->name ?? $rx->bhp_text }}</td>
                        <td class="muted">abgesetzt am {{ optional($rx->abgesetzt_am)->format('d.m.Y') }}</td></tr>
                @endforeach
            </tbody></table>
        </div>
    @endif
</div>
