<div wire:poll.60s>
    @if ($istStaff)
        <a href="{{ route('chat') }}" class="btn btn-ghost btn-sm" title="Chat" style="position:relative;text-decoration:none">
            💬
            @if ($anzahl > 0)
                <span class="badge red" style="position:absolute;top:-6px;right:-6px;font-size:.7em">{{ $anzahl }}</span>
            @endif
        </a>
    @endif
</div>
