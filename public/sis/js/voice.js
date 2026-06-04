/* ==========================================================================
   OPCare — Querschnitts-Sprachfunktion (Alpine-Factory)
   Diktieren (🎙) + KI-Optimieren (✨) für JEDES Textfeld. Einmal gebaut,
   überall via <x-voice-field> eingesetzt. Synchron gegen /speech/*.
   ========================================================================== */
window.voiceField = function (opts) {
    return {
        recording: false,
        busy: false,
        status: '',
        _rec: null,
        _chunks: [],

        _csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },

        async toggle() {
            if (this.recording) {
                this._rec && this._rec.stop();
                return;
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.status = 'Mikrofon nicht verfügbar (nur über HTTPS/localhost).';
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this._chunks = [];
                this._rec = new MediaRecorder(stream);
                this._rec.ondataavailable = (e) => { if (e.data && e.data.size) this._chunks.push(e.data); };
                this._rec.onstop = async () => {
                    stream.getTracks().forEach((t) => t.stop());
                    this.recording = false;
                    await this._send();
                };
                this._rec.start();
                this.recording = true;
                this.status = 'Aufnahme läuft – erneut tippen zum Stoppen …';
            } catch (e) {
                this.status = 'Mikrofon-Zugriff verweigert.';
            }
        },

        async _send() {
            this.busy = true;
            this.status = 'Transkribiere …';
            const blob = new Blob(this._chunks, { type: this._rec?.mimeType || 'audio/webm' });
            const fd = new FormData();
            fd.append('audio', blob, 'note.webm');
            if (opts.context) fd.append('context', opts.context);
            try {
                const res = await fetch(opts.endpointTranscribe, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this._csrf(), Accept: 'application/json' },
                    body: fd,
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                this._append((data.text || '').trim());
                this.status = data.text ? '' : 'Keine Sprache erkannt.';
            } catch (e) {
                this.status = 'Transkription fehlgeschlagen.';
            }
            this.busy = false;
        },

        async optimize() {
            const el = this.$refs.input;
            const text = (el.value || '').trim();
            if (!text) { this.status = 'Erst Text eingeben/diktieren.'; return; }
            this.busy = true;
            this.status = 'Optimiere …';
            try {
                const res = await fetch(opts.endpointOptimize, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf(), Accept: 'application/json' },
                    body: JSON.stringify({ text: text, context: opts.context || null }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                this._set(data.text || text);
                this.status = '✨ optimiert';
            } catch (e) {
                this.status = 'Optimierung fehlgeschlagen.';
            }
            this.busy = false;
        },

        _append(text) {
            if (!text) return;
            const el = this.$refs.input;
            el.value = el.value && el.value.trim() ? el.value.trim() + ' ' + text : text;
            el.dispatchEvent(new Event('input', { bubbles: true })); // Livewire-Sync
            el.focus();
        },

        _set(text) {
            const el = this.$refs.input;
            el.value = text;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },
    };
};
