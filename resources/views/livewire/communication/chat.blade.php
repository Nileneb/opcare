<div wire:poll.30s class="chat-layout">

    {{-- Sidebar: Konversationsliste --}}
    <aside class="chat-sidebar">
        <div style="padding:12px 12px 8px;border-bottom:1px solid var(--line-cool,#eee)">
            <b style="font-size:1.05em">Nachrichten</b>
        </div>

        {{-- Konversationsliste --}}
        <div style="flex:1;overflow-y:auto">
            @forelse ($konversationen as $item)
                @php
                    $isAktiv = $aktivKonversationId === $item['konversation']->id;
                @endphp
                <button
                    type="button"
                    wire:click="oeffne({{ $item['konversation']->id }})"
                    style="width:100%;text-align:left;padding:10px 12px;border:none;background:{{ $isAktiv ? 'var(--c-accent-bg, #e8f0ff)' : 'transparent' }};cursor:pointer;border-bottom:1px solid var(--line-cool,#f0f0f0);display:flex;align-items:center;gap:8px"
                >
                    <span style="flex:1;min-width:0">
                        <span style="font-weight:{{ $item['ungelesen'] > 0 ? '700' : '400' }};font-size:.93em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block">{{ $item['anzeigeName'] }}</span>
                        @if ($item['letzte'])
                            <span class="muted" style="font-size:.78em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block">
                                {{ \Illuminate\Support\Str::limit($item['letzte']->istZurueckgezogen() ? '🚫 Zurückgezogen' : $item['letzte']->inhalt, 40) }}
                            </span>
                        @endif
                    </span>
                    @if ($item['ungelesen'] > 0)
                        <span class="badge red" style="flex-shrink:0;font-size:.75em">{{ $item['ungelesen'] }}</span>
                    @endif
                </button>
            @empty
                <p class="empty" style="padding:14px 12px;font-size:.9em">Noch keine Konversationen.</p>
            @endforelse
        </div>

        {{-- Neu-Bereich --}}
        <div style="border-top:1px solid var(--line-cool,#ddd);padding:10px 12px">
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:6px">
                <button type="button" wire:click="$set('neuModus','direkt')" class="btn btn-ghost btn-sm">+ Direkt</button>
                <button type="button" wire:click="$set('neuModus','gruppe')" class="btn btn-ghost btn-sm">+ Gruppe</button>
                <button type="button" wire:click="$set('neuModus','station')" class="btn btn-ghost btn-sm">+ Station</button>
                <button type="button" wire:click="ankuendigungOeffnen" class="btn btn-ghost btn-sm">Ankündigungen</button>
            </div>

            @if ($neuModus === 'direkt')
                <form wire:submit="dmStarten" style="display:flex;flex-direction:column;gap:6px">
                    <div class="field" style="margin-bottom:0">
                        <label>Kolleg:in wählen</label>
                        <select wire:model="dmPartner">
                            <option value="">Kolleg:in wählen…</option>
                            @foreach ($kollegen as $k)
                                <option value="{{ $k->id }}">{{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Öffnen</button>
                </form>
            @endif

            @if ($neuModus === 'gruppe')
                <form wire:submit="gruppeAnlegen" style="display:flex;flex-direction:column;gap:6px">
                    @error('gruppeTitel') <span class="field"><span class="err">{{ $message }}</span></span> @enderror
                    <div class="field" style="margin-bottom:0">
                        <label>Gruppen-Titel</label>
                        <input type="text" wire:model="gruppeTitel" placeholder="Gruppen-Titel" maxlength="120" />
                    </div>
                    <div class="field" style="margin-bottom:0">
                        <label>Mitglieder</label>
                        <select wire:model="gruppeMitglieder" multiple style="height:90px">
                            @foreach ($kollegen as $k)
                                <option value="{{ $k->id }}">{{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Gruppe anlegen</button>
                </form>
            @endif

            @if ($neuModus === 'station')
                <form wire:submit="stationBeitreten" style="display:flex;flex-direction:column;gap:6px">
                    <div class="field" style="margin-bottom:0">
                        <label>Station wählen</label>
                        <select wire:model="stationWahl">
                            <option value="">Station wählen…</option>
                            @foreach ($stationen as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Beitreten</button>
                </form>
            @endif
        </div>
    </aside>

    {{-- Thread-Bereich --}}
    <main class="chat-thread">
        @if (session('status'))
            <div class="alert alert-success" style="margin:8px 12px;flex-shrink:0">{{ session('status') }}</div>
        @endif

        @if ($aktiv)
            <div style="padding:10px 16px;border-bottom:1px solid var(--line-cool,#eee);flex-shrink:0">
                <b style="font-size:1em">
                    @php
                        $anzeigeName = '';
                        foreach ($konversationen as $item) {
                            if ($item['konversation']->id === $aktiv->id) {
                                $anzeigeName = $item['anzeigeName'];
                                break;
                            }
                        }
                    @endphp
                    {{ $anzeigeName ?: ($aktiv->titel ?? $aktiv->typ->label()) }}
                </b>
                <span class="muted" style="font-size:.82em;margin-left:8px">{{ $aktiv->typ->label() }}</span>
            </div>

            {{-- Nachrichtenliste --}}
            <div style="flex:1;overflow-y:auto;padding:12px 16px;display:flex;flex-direction:column;gap:8px" id="chat-thread">
                @forelse ($nachrichten as $n)
                    @php $eigen = $n->user_id === $ichId; @endphp
                    <div style="display:flex;flex-direction:column;align-items:{{ $eigen ? 'flex-end' : 'flex-start' }}">
                        <div style="max-width:72%;background:{{ $eigen ? 'var(--c-primary-bg,#dbeafe)' : 'var(--c-surface,#f5f5f5)' }};border-radius:10px;padding:8px 12px;{{ $n->istZurueckgezogen() ? 'opacity:.55' : '' }}">
                            @unless ($eigen)
                                <div style="font-size:.78em;font-weight:600;margin-bottom:2px">{{ $n->absender?->name }}</div>
                            @endunless
                            @if ($n->istZurueckgezogen())
                                <span class="muted" style="font-style:italic">🚫 Nachricht zurückgezogen</span>
                            @else
                                <div style="font-size:.92em;white-space:pre-wrap;word-break:break-word">{{ $n->inhalt }}</div>
                            @endif
                            <div style="font-size:.72em;color:var(--c-muted,#999);margin-top:3px;text-align:right">
                                {{ $n->created_at?->format('d.m. H:i') }}
                                @if ($eigen && ! $n->istZurueckgezogen() && $n->created_at?->gt(now()->subMinutes(15)))
                                    · <button type="button" wire:click="zuruckziehen({{ $n->id }})" style="background:none;border:none;cursor:pointer;color:var(--c-muted,#999);font-size:1em;padding:0" title="Zurückziehen">✕</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="empty" style="text-align:center;margin-top:32px">Noch keine Nachrichten.</p>
                @endforelse
            </div>

            {{-- Eingabe --}}
            @if ($darfSchreiben)
                <form wire:submit="senden" style="padding:10px 16px;border-top:1px solid var(--line-cool,#eee);flex-shrink:0;display:flex;gap:8px">
                    @error('entwurf') <span class="text-danger" style="font-size:.82em;display:block;margin-bottom:4px">{{ $message }}</span> @enderror
                    <textarea
                        wire:model="entwurf"
                        placeholder="Nachricht eingeben…"
                        rows="2"
                        maxlength="2000"
                        style="flex:1;resize:none;border:1px solid var(--line-cool,#ddd);border-radius:8px;padding:8px;font-size:.92em"
                        wire:keydown.ctrl.enter="senden"
                    ></textarea>
                    <button type="submit" class="btn btn-primary" style="align-self:flex-end">Senden</button>
                </form>
            @else
                <div class="muted" style="padding:10px 16px;border-top:1px solid var(--line-cool,#eee);font-size:.88em;flex-shrink:0;text-align:center">
                    Nur Lesen — kein Schreibrecht in diesem Kanal.
                </div>
            @endif
        @else
            <div style="flex:1;display:flex;align-items:center;justify-content:center">
                <p class="empty" style="text-align:center">Konversation links auswählen oder neue starten.</p>
            </div>
        @endif
    </main>
</div>
