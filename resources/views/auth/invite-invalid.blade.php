<x-layouts.guest title="Einladung ungültig">
    <div class="auth-card">
        <h1>Einladung ungültig</h1>
        <p class="sub">{{ $message }}</p>
        <p>Wenn Sie glauben, dass dies ein Fehler ist, wenden Sie sich bitte an Ihre Personalabteilung.</p>
        <p><a href="{{ route('login') }}" class="btn btn-secondary btn-block">Zur Anmeldeseite</a></p>
    </div>
</x-layouts.guest>
