<div>
    <a href="{{ route('bewohner.show', $resident) }}" class="back-link" wire:navigate>← {{ $resident->name }}</a>
    <div class="page-head">
        <div>
            <p class="kicker">Medikation</p>
            <h1>Stellplan — {{ $resident->name }}</h1>
            <p class="lead">Tagesgaben für {{ \Carbon\Carbon::parse($tag)->format('d.m.Y') }}</p>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-head"><h3>Gaben des Tages</h3></div>

        @forelse ($gaben as $g)
            @php
                $medName = $g->schedule?->prescription?->medProduct?->name
                    ?? $g->schedule?->prescription?->bhp_text
                    ?? '—';
                $isBtm = (bool) ($g->schedule?->prescription?->medProduct?->btm);
                $statusBadge = match ($g->status) {
                    \App\Domains\Medication\Enums\AdministrationStatus::Gegeben   => 'green',
                    \App\Domains\Medication\Enums\AdministrationStatus::Abgelehnt  => 'amber',
                    \App\Domains\Medication\Enums\AdministrationStatus::Ausgelassen => 'gray',
                    default => 'gray',
                };
            @endphp
            <div class="chip" style="align-items:flex-start">
                <div style="flex:1">
                    <b>{{ $g->soll_zeitpunkt->format('H:i') }}</b>
                    · {{ $medName }}
                    @if ($isBtm)<span class="badge red" style="margin-left:6px">BtM</span>@endif
                    <br>
                    <span class="muted">Dosis: {{ $g->dosis }} · {{ $g->tageszeit->label() }}</span>
                    <span class="badge {{ $statusBadge }}" style="margin-left:8px">{{ $g->status->value }}</span>
                    @if ($g->notiz)<br><span class="muted">{{ $g->notiz }}</span>@endif
                </div>
                @if ($g->status === $offen)
                    <div style="display:flex;gap:8px;flex-shrink:0">
                        <button class="btn btn-primary btn-sm" wire:click="quittieren({{ $g->id }})" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="quittieren({{ $g->id }})">✓ Geben</span>
                            <span wire:loading wire:target="quittieren({{ $g->id }})">…</span>
                        </button>
                        <button class="btn btn-ghost btn-sm" wire:click="ablehnen({{ $g->id }})" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="ablehnen({{ $g->id }})">✗ Ablehnen</span>
                            <span wire:loading wire:target="ablehnen({{ $g->id }})">…</span>
                        </button>
                    </div>
                @endif
            </div>
        @empty
            <p class="empty">Keine Gaben für heute geplant.</p>
        @endforelse
    </div>
</div>
