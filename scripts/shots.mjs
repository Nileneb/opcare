// Headless-Screenshots der OPCare-App (Login + Schlüsselseiten).
// Nutzung:  node scripts/shots.mjs [baseURL]
//   baseURL default http://localhost:8099  (dev: php artisan serve --port=8099)
// Login: admin@opcare.local / password (Demo-Seed). Ausgabe: storage/app/shots/*.png
//
// MFA-Pflicht (Track B): das verpflichtende TOTP-Gate umgehen wir per Recovery-Code statt zeitabhängigem
// TOTP. Vorbereitung (einmal je Seed): den Demo-Admin 2FA-confirmt setzen + feste Recovery-Codes hinterlegen
//   php artisan tinker --execute='$u=App\Domains\Identity\Models\User::where("email","admin@opcare.local")->first();
//     $u->forceFill(["two_factor_secret"=>app(App\Domains\Identity\Support\TwoFactorAuthenticator::class)->generateSecret(),
//       "two_factor_recovery_codes"=>["SHOTS-1","SHOTS-2"],"two_factor_confirmed_at"=>now()])->save();'
// Dann:  MFA_RECOVERY=SHOTS-1 node scripts/shots.mjs <baseURL>   (jeder Code ist Einmal-Gebrauch)
// Eigene DB/URL ohne .env zu berühren: .env.shots anlegen + APP_ENV=shots php artisan serve (ServeCommand
// reicht nur APP_ENV durch, nicht DB_DATABASE).
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const base = process.argv[2] || 'http://localhost:8099';
const outDir = 'storage/app/shots';
mkdirSync(outDir, { recursive: true });

const pages = [
    ['login', '/login', false],
    ['overview', '/', true],
    ['bewohner', '/bewohner', true],
    ['bewohner-detail', '/bewohner/1', true],
    ['medikation', '/bewohner/1/medikation', true],
    ['controlling', '/controlling', true],
    ['betreuung', '/betreuung', true],
    ['praevention', '/praevention', true],
    ['qm-checkliste', '/qualitaet/qm-checkliste', true],
    ['dienstplan', '/dienstplan', true],
    ['tauschboerse', '/tauschboerse', true],
    ['arbeitsrecht', '/arbeitsrecht', true],
    ['zeiterfassung', '/zeiterfassung', true],
    ['haustechnik', '/haustechnik', true],
    ['medizinprodukte', '/medizinprodukte', true],
    ['kueche', '/kueche', true],
    ['buchhaltung', '/buchhaltung', true],
    ['taschengeld', '/taschengeld', true],
    ['skill-baum', '/personal/kompetenzen', true],
    ['berechtigungen', '/personal/berechtigungen', true],
    ['beauftragte', '/personal/beauftragte', true],
    ['btm-nachweis', '/medikation/btm', true],
    ['fem', '/qualitaet/fem', true],
    ['beschwerden', '/qualitaet/beschwerden', true],
    ['gremien', '/qualitaet/gremien', true],
    ['arbeitsschutz-nachweise', '/arbeitsschutz/nachweise', true],
    ['mitarbeitende', '/admin/benutzer', true],
    ['qdvs', '/qdvs', true],
    ['einrichtung', '/einrichtung', true],
    ['sis-board', '/pflegeplanung', true],
];

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 960 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();

// Login (Livewire-Formular)
await page.goto(base + '/login', { waitUntil: 'networkidle' });
await page.fill('#email', 'admin@opcare.local');
await page.fill('#password', 'password');
await Promise.all([
    page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 15000 }).catch(() => {}),
    page.click('button[type=submit]'),
]);
await page.waitForTimeout(800);

// MFA-Pflicht (Track B): ist ein 2FA-confirmter Demo-User vorbereitet, lösen wir die Challenge mit einem
// Recovery-Code (env MFA_RECOVERY). So bleiben die Screenshots hinter dem verpflichtenden TOTP-Gate möglich.
if (page.url().includes('/two-factor/challenge') && process.env.MFA_RECOVERY) {
    await page.fill('#code', process.env.MFA_RECOVERY);
    await Promise.all([
        page.waitForURL((u) => !u.pathname.includes('/two-factor'), { timeout: 15000 }).catch(() => {}),
        page.click('button[type=submit]'),
    ]);
    await page.waitForTimeout(800);
}

for (const [name, path, auth] of pages) {
    try {
        await page.goto(base + path, { waitUntil: 'networkidle', timeout: 20000 });
        await page.waitForTimeout(600);
        try {
            await page.screenshot({ path: `${outDir}/${name}.png`, fullPage: true });
        } catch {
            // Sehr hohe Seiten sprengen die Chromium-Canvas-Grenze (~32767px) → Viewport-Fallback.
            await page.screenshot({ path: `${outDir}/${name}.png`, fullPage: false });
            console.log(`     ${name}: fullPage zu groß → Viewport-Screenshot`);
        }
        console.log(`OK   ${name}  (${path})  ->  ${page.url()}`);
    } catch (e) {
        console.log(`FAIL ${name}  (${path}): ${e.message}`);
    }
}

// Personalakte: von der Mitarbeiterliste auf die erste Akte durchklicken (braucht eine User-ID).
try {
    await page.goto(base + '/admin/benutzer', { waitUntil: 'networkidle', timeout: 20000 });
    await page.click('a[href*="/admin/mitarbeitende/"]');
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${outDir}/personalakte.png`, fullPage: true }).catch(() =>
        page.screenshot({ path: `${outDir}/personalakte.png`, fullPage: false }));
    console.log(`OK   personalakte  ->  ${page.url()}`);
} catch (e) {
    console.log(`FAIL personalakte: ${e.message}`);
}

await browser.close();
