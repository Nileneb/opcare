<div>
    @if ($tenants->isNotEmpty())
        <select class="btn btn-ghost btn-sm" wire:change="switchTo($event.target.value)">
            @foreach ($tenants as $t)<option value="{{ $t->id }}" @selected($t->id === $current)>{{ $t->name }}</option>@endforeach
        </select>
    @endif
</div>
