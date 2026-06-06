<div wire:poll.30s style="position:relative">
    <button class="btn btn-ghost btn-sm" wire:click="umschalten" title="Benachrichtigungen" style="position:relative">
        🔔
        @if ($anzahl > 0)<span class="badge red" style="position:absolute;top:-6px;right:-6px;font-size:.7em">{{ $anzahl }}</span>@endif
    </button>
    @if ($offen)
        <div style="position:absolute;right:0;top:110%;z-index:50;width:320px;background:var(--c-surface,#fff);border:1px solid var(--line-cool,#ddd);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:10px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <b style="flex:1">Benachrichtigungen</b>
                @if ($anzahl > 0)<button class="btn btn-ghost btn-sm" wire:click="alleGelesen">alle gelesen</button>@endif
            </div>
            @forelse ($ungelesen as $n)
                <a href="{{ $n->data['url'] ?? '#' }}" wire:click="gelesen('{{ $n->id }}')" wire:navigate
                   style="display:block;padding:8px;border-radius:8px;text-decoration:none;color:inherit;border-bottom:1px solid var(--line-cool,#eee)">
                    <b style="font-size:.9em">{{ $n->data['titel'] ?? 'Hinweis' }}</b>
                    <div class="muted" style="font-size:.82em">{{ $n->data['text'] ?? '' }}</div>
                    <div class="muted" style="font-size:.72em">{{ $n->created_at?->diffForHumans() }}</div>
                </a>
            @empty
                <p class="empty" style="margin:6px 0">Keine neuen Benachrichtigungen.</p>
            @endforelse
        </div>
    @endif
</div>
