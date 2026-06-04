<div>
    <div class="page-head"><div><p class="kicker">Medikation</p><h1>Verordnung anlegen</h1>
        <p class="lead">für {{ $resident->name }}</p></div></div>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    <form wire:submit="speichern">
        <div class="card">
            <div class="card-head"><h3>Präparat / Anweisung</h3></div>
            <div class="form-row">
                <div class="field"><label>Präparat</label>
                    <select wire:model="medProductId">
                        <option value="">– kein Präparat (BHP-Freitext) –</option>
                        @foreach ($produkte as $p)<option value="{{ $p->id }}">{{ $p->name }} {{ $p->staerke }}</option>@endforeach
                    </select>@error('medProductId')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div class="field"><label>Arzt</label>
                    <select wire:model="physicianId"><option value="">–</option>
                        @foreach ($aerzte as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <x-voice-field model="bhpText" label="BHP-Anweisung / Freitext" :rows="2"
                context="Behandlungspflege-Anweisung für die Pflegekraft, präzise und knapp." />
        </div>

        <div class="card">
            <div class="card-head"><h3>Stellplan</h3></div>
            <div class="form-row">
                <div class="field"><label>Turnus</label>
                    <select wire:model.live="frequenz">
                        @foreach ($frequenzen as $f)<option value="{{ $f->value }}">{{ ucfirst($f->value) }}</option>@endforeach
                    </select>
                </div>
                @if ($frequenz === 'woechentlich')
                    <div class="field"><label>Wochentage (ISO 1–7)</label>
                        <div class="weekdays">
                            @foreach (['1'=>'Mo','2'=>'Di','3'=>'Mi','4'=>'Do','5'=>'Fr','6'=>'Sa','7'=>'So'] as $iso => $lbl)
                                <label><input type="checkbox" wire:model="wochentage" value="{{ $iso }}" /> {{ $lbl }}</label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="form-row">
                @foreach ($slots as $slot)
                    <div class="field field-narrow">
                        <label>{{ $slot->label() }}</label>
                        <input type="number" step="0.25" min="0" wire:model="dosis.{{ $slot->value }}" />
                    </div>
                @endforeach
            </div>
            <div class="form-row">
                <div class="field check"><label><input type="checkbox" wire:model.live="beiBedarf" /> Bedarfsmedikation</label></div>
                <div class="field"><label>Max. Gaben/Tag (Bedarf)</label><input type="number" step="0.5" min="0" wire:model="maxAnzahlTaeglich" /></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Gültigkeit &amp; Erstbestand</h3></div>
            <div class="form-row">
                <div class="field"><label>Gültig ab</label><input type="date" wire:model="gueltigVon" />@error('gueltigVon')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Gültig bis</label><input type="date" wire:model="gueltigBis" />@error('gueltigBis')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Vorlauf Gaben (Tage)</label><input type="number" min="0" max="60" wire:model="vorlaufTage" /></div>
            </div>
            <div class="form-row">
                <div class="field"><label>Bestand Menge</label><input type="number" step="1" min="0" wire:model="bestandMenge" /></div>
                <div class="field"><label>Charge</label><input wire:model="bestandCharge" /></div>
                <div class="field"><label>Verfall</label><input type="date" wire:model="bestandVerfall" /></div>
            </div>
            <x-voice-field model="hinweis" label="Hinweis" :rows="2" />
        </div>

        <button class="btn btn-primary">Verordnung speichern</button>
    </form>
</div>
