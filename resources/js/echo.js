import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// WHY: Reverb spricht das Pusher-Protokoll. Ohne gesetzten VITE_REVERB_APP_KEY (z. B. wenn
// BROADCAST_CONNECTION=null läuft) wird Echo NICHT initialisiert — Livewire-Echo-Listener
// werden dann still zu No-ops und die Komponenten fallen auf ihren wire:poll-Fallback zurück,
// statt mit einer kaputten WS-Verbindung Fehler zu werfen.
const key = import.meta.env.VITE_REVERB_APP_KEY;

if (key) {
    // WHY: Vite inlined VITE_*-Werte als STRING. pusher-js braucht hier eine Zahl — mit "8080"
    // statt 8080 baut es keine gültige WS-URL und bleibt stumm auf 'connecting' hängen.
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 80);

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: port,
        wssPort: port,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
