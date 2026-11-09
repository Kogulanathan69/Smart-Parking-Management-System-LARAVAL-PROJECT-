// ParkFlow end-to-end journeys (desktop + mobile). Exit code 0 = all passed.
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

// require() honours NODE_PATH, so a globally installed Playwright works without adding a dependency.
const { chromium } = createRequire(import.meta.url)('playwright');

const BASE = process.env.E2E_BASE_URL || 'http://127.0.0.1:8000';
const PW = process.env.E2E_PASSWORD;
if (!PW) { console.error('Set E2E_PASSWORD (the SEED_DEMO_PASSWORD of the disposable DB).'); process.exit(2); }
const here = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(here, 'output'); mkdirSync(OUT, { recursive: true });
const FIX = (f) => path.join(here, 'fixtures', f);
const rand = () => Math.random().toString(36).slice(2, 6).toUpperCase();

const results = [];
function ok(cond, msg) { if (!cond) throw new Error(msg); }

const states = {}; // one sign-in per role, reused (the product rate-limits logins to 5/min)
async function session(browser, role, viewport = { width: 1366, height: 900 }) {
    const ctx = await browser.newContext({ viewport, storageState: role && states[role] ? states[role] : undefined });
    const page = await ctx.newPage();
    page.errors = [];
    page.on('pageerror', (e) => page.errors.push(`pageerror: ${e.message}`));
    page.on('console', (m) => m.type() === 'error' && page.errors.push(`console: ${m.text()}`));
    page.on('response', (r) => { if (r.url().includes('/api/') && r.status() >= 500) page.errors.push(`HTTP ${r.status()} ${r.url()}`); });
    if (role && states[role]) {
        await page.goto(`${BASE}/dashboard`);
    } else if (role) {
        await page.goto(`${BASE}/login`);
        await page.fill('#f-email', `${role}@smart-parking.test`);
        await page.fill('#f-password', PW);
        await Promise.all([page.waitForURL(/dashboard/), page.click('button[type=submit]')]);
        states[role] = await ctx.storageState();
    }
    await page.waitForLoadState('networkidle');
    return { ctx, page };
}
const shot = (page, name) => page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: true });
const noOverflow = (page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);

async function test(name, fn) {
    const t0 = Date.now();
    try { await fn(); results.push({ name, ok: true, ms: Date.now() - t0 }); console.log(`  ✓ ${name}`); }
    catch (e) { results.push({ name, ok: false, error: e.message }); console.log(`  ✗ ${name}\n      ${e.message}`); }
}

const browser = await chromium.launch();
const engineOnline = await (async () => { try { const r = await fetch(`${BASE}/api/v1/anpr/health`); return (await r.json()).anpr_engine === 'available'; } catch { return false; } })();
console.log(`ParkFlow E2E against ${BASE} (ANPR engine ${engineOnline ? 'online' : 'offline'})`);
let checkedInPlate = null;

await test('login routes each role to its dashboard; wrong password is refused', async () => {
    for (const [role, url] of [['super_admin', '/super-admin/dashboard'], ['admin', '/admin/dashboard'], ['staff', '/staff/dashboard'], ['auditor', '/auditor/dashboard'], ['customer', '/customer/dashboard']]) {
        const { ctx, page } = await session(browser, role);
        ok(page.url().endsWith(url), `${role} landed on ${page.url()}`);
        ok(page.errors.length === 0, `${role}: ${page.errors.join('; ')}`);
        await ctx.close();
    }
    const { ctx, page } = await session(browser, null);
    await page.goto(`${BASE}/login`); await page.fill('#f-email', 'staff@smart-parking.test'); await page.fill('#f-password', 'wrong-password');
    await page.click('button[type=submit]'); await page.waitForSelector('.field-error');
    ok(page.url().includes('/login'), 'stayed on login'); await ctx.close();
});

await test('staff completes an ANPR Check-In (verify → details → confirm)', async () => {
    const { ctx, page } = await session(browser, 'staff');
    await page.goto(`${BASE}/ops/check-in`);
    if (engineOnline) {
        await page.setInputFiles('#cap-file', FIX('synthetic-plate-kx4455.jpg'));
        await page.click('text=Recognise plate');
        await page.waitForSelector('#rev-h', { state: 'visible', timeout: 30000 });
        await shot(page, 'checkin-review');
        await page.click('button:has-text("Confirm plate")');
        checkedInPlate = 'KX4455';
    } else {
        checkedInPlate = `PF${rand()}1`;
        await page.click('[role=tab]:has-text("Type plate manually")');
        await page.fill('#man-plate', checkedInPlate); await page.selectOption('#man-reason', 'engine_unavailable');
        await page.click('button:has-text("Continue with this plate")');
    }
    await page.waitForSelector('#det-h', { state: 'visible', timeout: 15000 });
    ok(!(await page.locator('text=already parked').isVisible()), 'vehicle already parked — clean the test DB or check it out first');
    if (await page.locator('#e-cat').isEnabled()) await page.selectOption('#e-cat', { label: 'Car' });
    await page.waitForTimeout(500);
    if (await page.locator('#e-fuel').isEnabled()) await page.selectOption('#e-fuel', 'petrol');
    await page.locator('label:has-text("Zone B") input[type=radio]').check();
    await page.selectOption('#e-pkg', { index: 1 });
    await page.check('text=I have checked that the plate');
    await page.click('button:has-text("Confirm Check-In")');
    await page.waitForSelector('section[aria-live] >> text=Checked in', { timeout: 15000 });
    await shot(page, 'checkin-done');
    ok(page.errors.length === 0, page.errors.join('; '));
    await ctx.close();
});

await test('staff completes Check-Out with cash; receipt and simulated gate are shown', async () => {
    ok(checkedInPlate, 'needs the Check-In journey');
    const { ctx, page } = await session(browser, 'staff');
    await page.goto(`${BASE}/ops/check-out`);
    await page.click('[role=tab]:has-text("Type plate manually")');
    await page.fill('#man-plate', checkedInPlate); await page.selectOption('#man-reason', 'camera_offline');
    await page.click('button:has-text("Continue with this plate")');
    await page.waitForSelector('text=Session match', { state: 'visible', timeout: 15000 });
    await page.waitForSelector('aside .text-3xl');
    await page.fill('#pay-t', '100000');
    await page.click('button:has-text("Settle & authorise exit")');
    await page.click('[role=alertdialog] button:has-text("Settle")');
    await page.waitForSelector('text=Exit authorised by the server', { timeout: 15000 });
    ok(await page.locator('text=Gate Authorized').first().isVisible(), 'gate label missing');
    ok(await page.locator('text=Simulated — no gate hardware').isVisible(), 'simulated gate note missing');
    ok(await page.locator('text=ParkFlow parking receipt').isVisible(), 'receipt missing');
    await shot(page, 'checkout-result');
    ok(page.errors.length === 0, page.errors.join('; '));
    await ctx.close();
});

await test('an unreadable photo cannot be confirmed; staff must correct or identify manually', async () => {
    if (!engineOnline) return;
    const { ctx, page } = await session(browser, 'staff');
    await page.goto(`${BASE}/ops/check-in`);
    await page.setInputFiles('#cap-file', FIX('synthetic-no-plate.jpg'));
    await page.click('text=Recognise plate');
    await page.waitForSelector('#rev-h', { state: 'visible', timeout: 30000 });
    ok(!(await page.locator('label:has-text("Confirm") input[value=confirm]').isVisible()), 'Confirm must not be offered for an unreadable reading');
    await page.click('button:has-text("Reject reading")').catch(() => {});
    await page.locator('label:has-text("Reject") input').check();
    await page.selectOption('#v-reason', 'unreadable');
    await page.click('button:has-text("Reject reading")');
    await page.waitForSelector('text=Reading rejected');
    await ctx.close();
});

await test('admin publishes settings change through the confirm dialog and sees honest system status', async () => {
    const { ctx, page } = await session(browser, 'admin');
    await page.goto(`${BASE}/manage/system`);
    await page.waitForSelector('text=Gate / barrier');
    ok(await page.locator('tr:has-text("Gate / barrier") >> text=Simulated / manual').isVisible(), 'gate must be labelled simulated');
    await page.goto(`${BASE}/manage/penalty-types`);
    await page.click('button:has-text("New penalty type")');
    const code = `e2e_${rand().toLowerCase()}`;
    await page.locator('[role=dialog] input').first().fill(code);
    await page.locator('[role=dialog] label:has-text("Name") + input, [role=dialog] input[id^="x-name"]').first().fill('E2E late return');
    await page.fill('#pt-a', '250.50');
    await page.click('[role=dialog] button:has-text("Save")');
    await page.waitForSelector(`text=${code}`);
    ok(await page.locator(`tr:has-text("${code}") >> text=LKR 250.50`).isVisible(), 'amount not shown as LKR 250.50');
    ok(page.errors.length === 0, page.errors.join('; '));
    await ctx.close();
});

await test('auditor sees reports but no operational write controls; write APIs are refused', async () => {
    const { ctx, page } = await session(browser, 'auditor');
    for (const p of ['/reports/payments', '/reports/sessions', '/audit/logs', '/audit/anpr-corrections', '/audit/recognition-history']) {
        await page.goto(BASE + p); await page.waitForTimeout(600);
        for (const label of ['Settle', 'Check-In', 'Confirm plate', 'Retry gate', 'Approve', 'Waive', 'Mark paid']) {
            ok(!(await page.locator(`main button:has-text("${label}")`).count()), `${p} shows a "${label}" button to an auditor`);
        }
    }
    const status = await page.evaluate(async () => {
        const t = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        const r = await fetch('/api/v1/staff/entries', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': t }, body: '{}' });
        return r.status;
    });
    ok(status === 403, `auditor POST /staff/entries returned ${status}`);
    await ctx.close();
});

await test('customer sees own parking info, adds a vehicle, and cannot open staff pages', async () => {
    const { ctx, page } = await session(browser, 'customer');
    await page.goto(`${BASE}/my/parking-info`); await page.waitForSelector('text=Published prices');
    ok(await page.locator('td:has-text("LKR")').first().isVisible(), 'prices not shown in LKR');
    await page.goto(`${BASE}/my/vehicles`); await page.click('button:has-text("Add vehicle")');
    await page.fill('#mv-p', `CP E2E-${Math.floor(1000 + Math.random() * 8999)}`);
    await page.selectOption('#mv-c', { label: 'Car' }); await page.selectOption('#mv-f2', 'hybrid');
    await page.click('[role=dialog] button:has-text("Save")'); await page.waitForSelector('text=Created.');
    const r = await page.goto(`${BASE}/ops/check-in`);
    ok(r.status() === 403, `customer opened /ops/check-in (${r.status()})`);
    ok(page.errors.filter((e) => !e.includes('403')).length === 0, page.errors.join('; '));
    await ctx.close();
});

await test('responsive: key pages have no horizontal overflow at 320–1920 px; mobile menu works', async () => {
    // One sign-in, resized: the login rate limit (5/min) is part of the product and must not be tripped by tests.
    const { ctx, page } = await session(browser, 'staff');
    for (const width of [320, 375, 768, 1366, 1920]) {
        await page.setViewportSize({ width, height: 820 });
        for (const p of ['/staff/dashboard', '/ops/check-in', '/ops/check-out', '/ops/sessions', '/ops/anpr']) {
            await page.goto(BASE + p); await page.waitForLoadState('networkidle');
            ok(await noOverflow(page), `overflow at ${width}px on ${p}`);
        }
        if (width < 1024) {
            await page.click('button[aria-label="Open menu"]');
            ok(await page.locator('#sidebar >> text=Check-Out').isVisible(), 'mobile menu did not open');
            await page.keyboard.press('Escape');
        }
        await shot(page, `staff-anpr-queue-${width}`);
    }
    await ctx.close();
});

await test('keyboard: skip link and dialog Escape', async () => {
    const { ctx, page } = await session(browser, 'admin');
    await page.goto(`${BASE}/manage/users`); await page.waitForLoadState('networkidle');
    await page.keyboard.press('Tab');
    ok((await page.evaluate(() => document.activeElement?.textContent?.trim())) === 'Skip to content', 'first Tab should reach the skip link');
    await page.locator('main button:has-text("Create account")').first().click();
    await page.locator('[role=dialog]').waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    ok(await page.locator('[role=dialog]').isVisible(), 'dialog did not open');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    ok(!(await page.locator('[role=dialog]').isVisible()), 'Escape did not close the dialog');
    await ctx.close();
});

await browser.close();
const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} journeys passed`);
process.exit(failed.length ? 1 : 0);
