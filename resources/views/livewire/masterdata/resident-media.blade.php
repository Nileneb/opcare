<div class="card scroll-target" id="dokumente">
    <div class="card-head"><h3>Dokumente &amp; Fotos</h3><span class="badge gray">{{ $dokumente->count() }}</span></div>
    @if (session('media_status'))<div class="flash">{{ session('media_status') }}</div>@endif

    @forelse ($dokumente as $m)
        @php($kat = \App\Domains\Masterdata\Enums\DokumentKategorie::tryFrom($m->getCustomProperty('kategorie', 'sonstiges')))
        <div class="qm-anf" style="padding:7px 0;border-bottom:1px solid var(--line-cool)">
            <b>{{ $m->name }}</b>
            <span class="badge {{ $kat?->badge() ?? 'gray' }}">{{ $kat?->label() ?? 'Sonstiges' }}</span>
            <span class="muted">{{ $m->human_readable_size }}</span>
            @if ($m->getCustomProperty('retention_until'))<span class="badge gray" title="Aufbewahrung bis (§ 630f BGB)">📅 {{ \Illuminate\Support\Carbon::parse($m->getCustomProperty('retention_until'))->format('d.m.Y') }}</span>@endif
            @if ($m->getCustomProperty('einwilligung_von'))<span class="badge amber" title="Foto-Einwilligung">✓ Einwilligung: {{ $m->getCustomProperty('einwilligung_von') }}</span>@endif
            <span style="margin-left:auto;display:inline-flex;gap:6px">
                <a class="btn btn-ghost btn-sm" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $m->id]) }}">↓ Download</a>
                <button class="btn btn-ghost btn-sm" wire:click="teilenStart({{ $m->id }})">Freigeben</button>
                <button class="btn btn-ghost btn-sm" wire:click="loeschen({{ $m->id }})" wire:confirm="Dokument löschen?">✕</button>
            </span>
        </div>

        @if ($teilenMedia === $m->id)
            <div style="background:var(--bg-cool);border-radius:8px;padding:12px;margin:8px 0">
                <p class="kicker">Freigabe „bei Bedarf" — protokolliert (DSGVO)</p>
                @if ($shareLink)
                    <div class="flash">Freigabe-Link (läuft ab): <input type="text" readonly value="{{ $shareLink }}" style="width:100%;font-family:monospace;font-size:11px" onclick="this.select()" /></div>
                @else
                    <div class="form-row-3">
                        <div class="field"><label>Empfängertyp</label>
                            <select wire:model="teilen_typ">
                                <option value="physician">behandelnde:r Arzt/Ärztin</option>
                                <option value="relative">Angehörige:r (Einwilligung nötig)</option>
                                <option value="authority">Heimaufsicht/Behörde</option>
                                <option value="internal">intern</option>
                            </select>
                        </div>
                        <div class="field"><label>Empfänger:in</label><input type="text" wire:model="teilen_empfaenger" />@error('teilen_empfaenger')<span class="err">{{ $message }}</span>@enderror</div>
                        <div class="field"><label>Gültig (Minuten)</label><input type="number" wire:model="teilen_minuten" /></div>
                    </div>
                    <button class="btn btn-primary btn-sm" wire:click="teilenSpeichern">Link erzeugen</button>
                @endif
            </div>
        @endif
    @empty
        <p class="empty">Noch keine Dokumente oder Fotos hinterlegt.</p>
    @endforelse

    <form wire:submit="speichern" style="margin-top:14px;border-top:1px solid var(--line-cool);padding-top:14px">
        <p class="kicker">Hochladen</p>
        <div class="form-row-3">
            <div class="field"><label>Datei</label><input type="file" wire:model="datei" />@error('datei')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="field"><label>Kategorie</label><select wire:model.live="kategorie">@foreach ($kategorien as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach</select></div>
            @if (\App\Domains\Masterdata\Enums\DokumentKategorie::from($kategorie)->brauchtEinwilligung())
                <div class="field"><label>Einwilligung erteilt von</label><input type="text" wire:model="einwilligung" placeholder="Bewohner:in / gesetzl. Betreuer:in" />@error('einwilligung')<span class="err">{{ $message }}</span>@enderror</div>
            @endif
        </div>
        <div wire:loading wire:target="datei" class="muted">Lädt hoch …</div>
        <button class="btn btn-ghost btn-sm">+ Dokument</button>
    </form>
</div>
