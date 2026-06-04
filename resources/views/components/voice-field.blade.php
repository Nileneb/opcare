@props([
    'model',
    'label' => null,
    'rows' => 2,
    'context' => null,
    'placeholder' => '',
])
{{-- Textfeld mit 🎙 Diktieren + ✨ KI-Optimieren. Bindet an Livewire via wire:model="$model". --}}
<div class="field" x-data="voiceField({
    endpointTranscribe: '{{ route('speech.transcribe') }}',
    endpointOptimize: '{{ route('speech.optimize') }}',
    context: {{ \Illuminate\Support\Js::from($context) }},
})">
    @if ($label)
        <label>{{ $label }}</label>
    @endif
    <div class="voice-row">
        <textarea
            x-ref="input"
            wire:model="{{ $model }}"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            {{ $attributes->merge(['class' => 'voice-textarea']) }}></textarea>
        <div class="voice-actions">
            <button type="button" class="voice-btn" :class="{ 'is-rec': recording }" @click="toggle()" :disabled="busy" title="Diktieren">
                <span x-show="!recording">🎙</span>
                <span x-show="recording" x-cloak>⏹</span>
            </button>
            <button type="button" class="voice-btn voice-btn-ai" @click="optimize()" :disabled="busy || recording" title="Mit KI optimieren">✨</button>
        </div>
    </div>
    <small class="voice-status muted" x-text="status"></small>
    @error($model)<span class="err">{{ $message }}</span>@enderror
</div>
