// Headless-Screenshots der OPCare-App (Login + Schlüsselseiten).
// Nutzung:  node scripts/shots.mjs [baseURL]
//   baseURL default http://localhost:8099  (dev: php artisan serve --port=8099)
// Login: admin@opcare.local / password (Demo-Seed). Ausgabe: storage/app/shots/*.png
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

for (const [name, path, auth] of pages) {
    try {
        await page.goto(base + path, { waitUntil: 'networkidle', timeout: 20000 });
        await page.waitForTimeout(600);
        await page.screenshot({ path: `${outDir}/${name}.png`, fullPage: true });
        console.log(`OK   ${name}  (${path})  ->  ${page.url()}`);
    } catch (e) {
        console.log(`FAIL ${name}  (${path}): ${e.message}`);
    }
}

await browser.close();
