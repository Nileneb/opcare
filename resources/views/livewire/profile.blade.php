<div>
    <div class="page-head">
        <div>
            <p class="kicker">Konto</p>
            <h1>Profil</h1>
            <p class="lead">Persönliche Daten und Passwort verwalten.</p>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h3>Profildaten</h3></div>
            @if (session('profile_status'))<div class="flash">{{ session('profile_status') }}</div>@endif
            <form wire:submit="updateProfile">
                <div class="field">
                    <label>Name</label>
                    <input type="text" wire:model="name" required />
                    @error('name') <span class="err">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label>E-Mail</label>
                    <input type="email" wire:model="email" required />
                    @error('email') <span class="err">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
        </div>

        <div class="card">
            <div class="card-head"><h3>Passwort ändern</h3></div>
            @if (session('password_status'))<div class="flash">{{ session('password_status') }}</div>@endif
            <form wire:submit="updatePassword">
                <div class="field">
                    <label>Aktuelles Passwort</label>
                    <input type="password" wire:model="current_password" autocomplete="current-password" />
                    @error('current_password') <span class="err">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label>Neues Passwort</label>
                    <input type="password" wire:model="password" autocomplete="new-password" />
                    @error('password') <span class="err">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label>Neues Passwort bestätigen</label>
                    <input type="password" wire:model="password_confirmation" autocomplete="new-password" />
                </div>
                <button type="submit" class="btn btn-primary">Passwort ändern</button>
            </form>
        </div>
    </div>
</div>
