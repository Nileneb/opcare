<div class="sis-app" data-density="komfort" data-amp="strong"
     x-data="sisApp(@js($residents), @js($areas), @js($nurse))">

    {{-- ============================ TOP BAR ============================ --}}
    <header class="sis-top">
        <div class="sis-brand">
            <img src="{{ asset('sis/assets/logo.png') }}" alt="Bergische Diakonie" />
            <span class="sis-product">Pflegeplanung<small>SIS®</small></span>
        </div>
        <div class="sis-top-search">
            <x-sis-icon name="search" :size="18" />
            <input placeholder="Bewohner:in oder Zimmer suchen…" x-model="query" />
        </div>
        <div class="sis-shift">
            <div class="sis-shift-meta">
                <b x-text="nurse.name"></b>
                <span x-text="nurse.schicht + ' · Wohnbereich Aprath'"></span>
            </div>
            <div class="sis-avatar" style="width:40px;height:40px;background:var(--ink-700);color:#fff;font-size:14px"
                 x-text="nurse.initials"></div>
        </div>
    </header>

    <div class="sis-body">
        {{-- ============================ SIDEBAR ============================ --}}
        <aside class="sis-side">
            <div class="sis-side-head">
                <h2>Wohnbereich</h2>
                <span><span x-text="filtered().length"></span> Bewohner:innen</span>
            </div>
            <div class="sis-side-list">
                <template x-for="r in filtered()" :key="r.id">
                    <button class="sis-resident" :class="{ 'is-active': r.id === residentId }"
                            @click="selectResident(r.id)">
                        <div class="sis-avatar" :style="avatarStyle(r, 44)" x-text="r.initials"></div>
                        <div class="sis-resident-info">
                            <div class="sis-resident-name" x-text="r.name"></div>
                            <div class="sis-resident-sub">
                                Zimmer <span x-text="r.room"></span> · PG <span x-text="r.pflegegrad"></span>
                                <template x-if="r.akut"><span> · ❗</span></template>
                            </div>
                            <div class="amp-strip">
                                <template x-for="a in areas" :key="a.key">
                                    <i :style="{ '--c': statusColor((r.areas[a.key]||{}).status) }"></i>
                                </template>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </aside>

        {{-- ============================ MAIN ============================ --}}
        <main class="sis-main">
            {{-- ---- EBENE 1: DASHBOARD ---- --}}
            <div class="sis-main-inner sis-fadein" x-show="view === 'dashboard'">
                <div class="sis-rhead">
                    <div class="sis-avatar" :style="avatarStyle(resident(), 72)" x-text="resident().initials"></div>
                    <div class="sis-rhead-text">
                        <h1 x-text="resident().name"></h1>
                        <div class="sis-rhead-facts">
                            <span class="sis-fact"><x-sis-icon name="haushalt" :size="15" /> Zimmer <b x-text="resident().room"></b></span>
                            <span class="sis-fact">Pflegegrad <b x-text="resident().pflegegrad"></b></span>
                            <template x-if="resident().eingangsfrage">
                                <span class="sis-fact" x-text="'„' + resident().eingangsfrage + '“'"></span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Akut-Risiko --}}
                <template x-if="resident().akut">
                    <button class="sis-akut" @click="openArea(resident().akut.areaKey)">
                        <x-sis-icon name="alert" :size="22" />
                        <span x-text="resident().akut.text"></span>
                        <span class="sis-akut-go">
                            <span x-text="areaMeta(resident().akut.areaKey).kurz"></span>
                            <x-sis-icon name="arrowRight" :size="16" />
                        </span>
                    </button>
                </template>

                <p class="sis-seclabel">Lebensbereiche <span class="count">· 6 Bereiche des SIS®</span></p>
                <div class="sis-areas">
                    @foreach ($areas as $a)
                        <button class="sis-tile"
                                style="--tint: {{ $a['tint'] }}"
                                :style="{ '--status-c': statusColor(area('{{ $a['key'] }}').status) }"
                                @click="openArea('{{ $a['key'] }}')">
                            <div class="sis-tile-top">
                                <span class="sis-tile-icon" style="--tint: {{ $a['tint'] }}">
                                    <x-sis-icon name="{{ $a['icon'] }}" :size="24" />
                                </span>
                                <span class="amp-dot" :style="{ '--c': statusColor(area('{{ $a['key'] }}').status) }"></span>
                            </div>
                            <div>
                                <h3>{{ $a['name'] }}</h3>
                                <div class="sis-tile-sub">{{ $a['kurz'] }}</div>
                            </div>
                            <div class="sis-tile-foot">
                                <span class="sis-tile-pair">
                                    <span><b style="color:var(--amp-green)" x-text="countReal(area('{{ $a['key'] }}').ressourcen)"></b> Ressourcen</span>
                                    <span style="opacity:.5">·</span>
                                    <span><b :style="{ color: countReal(area('{{ $a['key'] }}').belastungen) ? 'var(--amp-red)' : 'var(--color-fg-subtle)' }"
                                             x-text="countReal(area('{{ $a['key'] }}').belastungen)"></b> Belastungen</span>
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>

                <p class="sis-seclabel">Offene Aufgaben heute
                    <span class="count">· <span x-text="tasks().filter(t => !doneTasks[t.id]).length"></span> offen</span>
                </p>
                <div class="sis-tasks">
                    <template x-for="t in tasks()" :key="t.id">
                        <div class="sis-task" :class="{ done: doneTasks[t.id] }">
                            <button class="sis-task-check" @click="toggleTask(t.id)" aria-label="Erledigt">
                                <template x-if="doneTasks[t.id]"><x-sis-icon name="check" :size="15" :stroke="2.5" /></template>
                            </button>
                            <div class="sis-task-text">
                                <span x-text="t.text"></span>
                                <small x-text="t.areaName"></small>
                            </div>
                            <span class="amp-dot" style="width:9px;height:9px" :style="{ '--c': statusColor(t.status) }"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ---- EBENE 2: LEBENSBEREICH-DETAIL (je Bereich ein Block) ---- --}}
            @foreach ($areas as $a)
                <div class="sis-main-inner sis-fadein" x-show="view === 'area' && areaKey === '{{ $a['key'] }}'">
                    <div class="sis-detail-head">
                        <button class="sis-back" @click="view = 'dashboard'">
                            <x-sis-icon name="chevronLeft" :size="17" /> <span x-text="resident().name"></span>
                        </button>
                        <span class="amp-badge"
                              :style="ampBadgeStyle(area('{{ $a['key'] }}').status)"
                              x-text="statusLabel(area('{{ $a['key'] }}').status)"></span>
                    </div>

                    <div class="sis-detail-title">
                        <span class="sis-tile-icon" style="--tint: {{ $a['tint'] }}">
                            <x-sis-icon name="{{ $a['icon'] }}" :size="30" />
                        </span>
                        <div class="sis-detail-title-text">
                            <h1>{{ $a['name'] }}</h1>
                            <p>Lebensbereich · zuletzt aktualisiert <span x-text="area('{{ $a['key'] }}').updated"></span></p>
                        </div>
                    </div>
                    <p class="sis-detail-frage">„{{ $a['frage'] }}"</p>

                    <p class="sis-seclabel">
                        Ressource <x-sis-icon name="arrowsLR" :size="15" style="color:var(--color-fg-subtle)" /> Belastung
                        <span class="count">· Stärke und Risiko gemeinsam betrachten</span>
                    </p>
                    <div class="sis-pair">
                        <div class="sis-pair-col" style="--col:var(--amp-green);--col-line:var(--amp-green-line);--col-fg:var(--green-800)">
                            <h4><span class="dot"></span> Ressourcen <span class="n" x-text="countReal(area('{{ $a['key'] }}').ressourcen)"></span></h4>
                            <div class="sis-chips">
                                <template x-for="(t, i) in real(area('{{ $a['key'] }}').ressourcen)" :key="i">
                                    <div class="sis-chip" style="--col:var(--amp-green)">
                                        <span class="chip-mark"><x-sis-icon name="check" :size="17" :stroke="2.2" /></span>
                                        <span x-text="t"></span>
                                    </div>
                                </template>
                                <template x-if="!real(area('{{ $a['key'] }}').ressourcen).length">
                                    <div class="sis-chip-empty">Aktuell nichts dokumentiert.</div>
                                </template>
                            </div>
                        </div>
                        <div class="sis-pair-col" style="--col:var(--amp-red);--col-line:var(--amp-red-line);--col-fg:#8C2A18">
                            <h4><span class="dot"></span> Belastungen & Risiken <span class="n" x-text="countReal(area('{{ $a['key'] }}').belastungen)"></span></h4>
                            <div class="sis-chips">
                                <template x-for="(t, i) in real(area('{{ $a['key'] }}').belastungen)" :key="i">
                                    <div class="sis-chip" style="--col:var(--amp-red)">
                                        <span class="chip-mark"><x-sis-icon name="alert" :size="17" :stroke="2.2" /></span>
                                        <span x-text="t"></span>
                                    </div>
                                </template>
                                <template x-if="!real(area('{{ $a['key'] }}').belastungen).length">
                                    <div class="sis-chip-empty">Aktuell nichts dokumentiert.</div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <p class="sis-seclabel">Pflegeziele & Maßnahmen <span class="count">· was wir anstreben und konkret tun</span></p>
                    <div class="sis-zm">
                        <div class="sis-panel">
                            <h4><x-sis-icon name="target" :size="19" class="ph-icon" /> Pflegeziele</h4>
                            <div class="sis-zlist">
                                <template x-for="(z, i) in real(area('{{ $a['key'] }}').ziele)" :key="i">
                                    <div class="sis-zitem">
                                        <span class="zi-mark"><x-sis-icon name="target" :size="13" :stroke="2" /></span>
                                        <span x-text="z"></span>
                                    </div>
                                </template>
                                <template x-if="!real(area('{{ $a['key'] }}').ziele).length">
                                    <div class="sis-chip-empty">Noch kein Ziel formuliert.</div>
                                </template>
                            </div>
                        </div>
                        <div class="sis-panel">
                            <h4><x-sis-icon name="check" :size="19" class="ph-icon" /> Maßnahmen</h4>
                            <div class="sis-zlist">
                                <template x-for="(m, i) in real(area('{{ $a['key'] }}').massnahmen)" :key="i">
                                    <div class="sis-zitem is-massnahme">
                                        <span class="zi-mark"><x-sis-icon name="check" :size="13" :stroke="2.5" /></span>
                                        <span x-text="m"></span>
                                    </div>
                                </template>
                                <template x-if="!real(area('{{ $a['key'] }}').massnahmen).length">
                                    <div class="sis-chip-empty">Noch keine Maßnahme geplant.</div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="sis-update-row">
                        <span class="sis-handzeichen" x-text="area('{{ $a['key'] }}').by"></span>
                        <span>Zuletzt dokumentiert von <span x-text="area('{{ $a['key'] }}').by"></span> · <span x-text="area('{{ $a['key'] }}').updated"></span></span>
                    </div>
                </div>
            @endforeach
        </main>
    </div>

    {{-- Mikrofon-FAB — nur im Lebensbereich-Detail --}}
    <button class="sis-fab" x-show="view === 'area' && !kiOpen" @click="kiOpen = true"
            aria-label="Per Sprache dokumentieren" title="Per Sprache dokumentieren">
        <span class="sis-fab-label">Dokumentieren</span>
        <x-sis-icon name="mic" :size="26" />
    </button>

    {{-- KI-Panel (Ebene 3) — Sprach→SIS-Workflow (Human-in-the-Loop) --}}
    <template x-if="kiOpen">
        <div class="sis-scrim" @click.self="kiOpen = false">
            <div class="sis-kipanel">
                <div class="sis-kihead">
                    <span class="ai-badge"><x-sis-icon name="sparkles" :size="15" /> KI-Assistent</span>
                    <h3 x-text="areaMeta(areaKey).name"></h3>
                    <button class="sis-iconbtn" @click="kiOpen = false" aria-label="Schließen"><x-sis-icon name="x" :size="18" /></button>
                </div>
                <div class="sis-kibody">
                    <p class="sis-kistep-label"><span class="num">1</span> Sprachnotiz aufnehmen</p>
                    <div class="sis-rec">
                        <button class="sis-rec-orb"><x-sis-icon name="mic" :size="34" /></button>
                        <div class="sis-rec-hint">Tippen und einfach erzählen — die Notiz wird lokal transkribiert (Whisper) und strukturiert (Ollama).</div>
                    </div>
                    <div class="sis-human-note">
                        <x-sis-icon name="info" :size="17" />
                        <span>Die KI macht nur Vorschläge. Du prüfst, korrigierst und gibst frei — nichts wird ungeprüft gespeichert (Human-in-the-Loop). Das Audio wird nach der Transkription gelöscht.</span>
                    </div>
                </div>
                <div class="sis-kifoot">
                    <span class="foot-info">Bereich: <span x-text="areaMeta(areaKey).name"></span></span>
                    <button class="sis-btn sis-btn-ghost" @click="kiOpen = false">Schließen</button>
                </div>
            </div>
        </div>
    </template>

    <script>
        function sisApp(residents, areas, nurse) {
            const SEVERITY = { handlung: 0, beobachten: 1, stabil: 2 };
            const STATUS = {
                stabil:     { label: 'Stabil',          c: 'var(--amp-green)', bg: 'var(--amp-green-bg)', line: 'var(--amp-green-line)', fg: 'var(--green-800)' },
                beobachten: { label: 'Beobachten',      c: 'var(--amp-amber)', bg: 'var(--amp-amber-bg)', line: 'var(--amp-amber-line)', fg: '#8A5A00' },
                handlung:   { label: 'Handlungsbedarf', c: 'var(--amp-red)',   bg: 'var(--amp-red-bg)',   line: 'var(--amp-red-line)',   fg: '#8C2A18' },
            };
            const meta = Object.fromEntries(areas.map(a => [a.key, a]));

            return {
                residents, areas, nurse,
                residentId: residents.length ? residents[0].id : null,
                view: 'dashboard',
                areaKey: areas[0].key,
                query: '',
                kiOpen: false,
                doneTasks: {},

                resident() { return this.residents.find(r => r.id === this.residentId) || this.residents[0] || { areas: {}, name: '', room: '', pflegegrad: '', initials: '' }; },
                area(key) { return (this.resident().areas || {})[key] || { status: 'stabil', ressourcen: [], belastungen: [], ziele: [], massnahmen: [], updated: '—', by: '' }; },
                areaMeta(key) { return meta[key] || { name: '', kurz: '' }; },
                filtered() {
                    const q = this.query.trim().toLowerCase();
                    return this.residents.filter(r => !q || r.name.toLowerCase().includes(q) || String(r.room).includes(q));
                },
                real(list) { return (list || []).filter(x => x && x !== '—'); },
                countReal(list) { return this.real(list).length; },
                statusColor(s) { return (STATUS[s] || STATUS.stabil).c; },
                statusLabel(s) { return (STATUS[s] || STATUS.stabil).label; },
                ampBadgeStyle(s) { const m = STATUS[s] || STATUS.stabil; return { '--bg': m.bg, '--fg': m.fg, '--line': m.line, '--c': m.c, background: m.bg, color: m.fg, borderColor: m.line }; },
                worstStatus(r) {
                    let worst = 'stabil';
                    Object.values(r.areas || {}).forEach(a => { if (SEVERITY[a.status] < SEVERITY[worst]) worst = a.status; });
                    return worst;
                },
                avatarStyle(r, size) {
                    return { width: size + 'px', height: size + 'px', background: r.avatarBg || 'var(--paper-2)', fontSize: Math.round(size * 0.34) + 'px' };
                },
                selectResident(id) { this.residentId = id; this.view = 'dashboard'; this.kiOpen = false; },
                openArea(key) { this.areaKey = key; this.view = 'area'; },
                toggleTask(id) { this.doneTasks[id] = !this.doneTasks[id]; },
                tasks() {
                    const r = this.resident();
                    const out = [];
                    this.areas.forEach(m => {
                        const ar = (r.areas || {})[m.key];
                        if (!ar || ar.status === 'stabil') return;
                        this.real(ar.massnahmen).slice(0, ar.status === 'handlung' ? 2 : 1).forEach((t, i) => {
                            out.push({ id: m.key + '-' + i, areaName: m.name, status: ar.status, text: t });
                        });
                    });
                    out.sort((a, b) => SEVERITY[a.status] - SEVERITY[b.status]);
                    return out.slice(0, 6);
                },
            };
        }
    </script>
</div>
