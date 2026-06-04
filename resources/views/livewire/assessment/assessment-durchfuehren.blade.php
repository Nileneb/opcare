<div>
    <div class="page-head"><div><p class="kicker">Assessment</p><h1>{{ $instrument->name }}</h1>
        <p class="lead">für {{ $resident->name }}</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <form wire:submit="speichern">
        <div class="card">
            @foreach ($instrument->items as $item)
                <div class="field">
                    <label>{{ $item->label }}</label>
                    @if ($item->hilfetext)<small class="muted">{{ $item->hilfetext }}</small>@endif
                    <select wire:model="answers.{{ $item->id }}">
                        <option value="">– wählen –</option>
                        @foreach ($item->options as $opt)
                            <option value="{{ $opt->id }}">{{ $opt->label }} ({{ $opt->punkte }})</option>
                        @endforeach
                    </select>
                    @error("answers.{$item->id}")<span class="err">{{ $message }}</span>@enderror
                </div>
            @endforeach
        </div>

        <div class="card">
            <x-voice-field model="notiz" label="Notiz / Begründung" :rows="2" />
        </div>

        <button class="btn btn-primary">Assessment abschließen</button>
    </form>
</div>
