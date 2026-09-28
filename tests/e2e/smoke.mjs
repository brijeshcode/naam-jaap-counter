// End-to-end smoke test driven through installed Edge (no browser download).
// Requires the PHP dev server running on http://localhost:8000 and MySQL up.
import { chromium } from 'playwright';
import assert from 'node:assert';

const BASE = process.env.NJ_BASE || 'http://localhost:8000';
const user = 'e2e_' + Date.now();

const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage();
page.on('dialog', d => d.accept());   // auto-accept confirm() dialogs

const txt = async sel => (await page.locator(`[data-testid="${sel}"]`).innerText()).trim();

try {
  await page.goto(BASE + '/index.php');
  await page.waitForLoadState('networkidle');

  // Register a fresh user
  await page.click('[data-testid="auth-toggle"]');            // switch to register mode
  await page.fill('input[type="text"]', user);
  await page.fill('input[type="password"]', 'pw123456');
  await page.click('[data-testid="auth-submit"]');
  await page.waitForSelector('[data-testid="tap"]');          // app view rendered
  await page.waitForTimeout(300);

  assert.equal(await txt('total'), '0', 'total starts at 0');

  // Tap 3 times
  for (let i = 0; i < 3; i++) await page.click('[data-testid="tap"]');
  assert.equal(await txt('current'), '3', 'current=3 after 3 taps');
  assert.equal(await txt('total'), '3', 'total=3 after 3 taps');

  // Add 108 via manual entry -> mala rollover
  await page.fill('[data-testid="manual"]', '108');
  await page.click('[data-testid="add"]');
  await page.waitForTimeout(200);
  assert.equal(await txt('total'), '111', 'total=111 after +108');
  assert.equal(await txt('malas'), '1', 'malas=1 after crossing 108');
  assert.equal(await txt('current'), '3', 'current=3 (111 % 108)');

  // today's stats on the home screen
  assert.equal(await txt('today-total'), '111', "today's jaap = 111");
  assert.equal(await txt('today-malas'), '1', "today's malas = 1");

  // Active beads should equal current (3)
  const active = await page.locator('.bead.active').count();
  assert.equal(active, 3, 'exactly 3 beads lit');

  // Reset today -> today cleared (all counts were today) => total 0
  await page.click('[data-testid="reset"]');
  await page.waitForTimeout(300);
  assert.equal(await txt('total'), '0', 'total=0 after reset today');
  assert.equal(await txt('today-total'), '0', "today's jaap = 0 after reset");
  assert.equal(await txt('today-malas'), '0', "today's malas = 0 after reset");

  // Reload -> server pull should still show 0 (reset persisted)
  await page.reload();
  await page.waitForLoadState('networkidle');
  await page.waitForSelector('[data-testid="total"]');
  await page.waitForTimeout(500);
  assert.equal(await txt('total'), '0', 'total still 0 after reload (persisted)');

  await page.screenshot({ path: 'tests/e2e/smoke.png', fullPage: true });
  console.log('e2e smoke OK (user ' + user + ')');
} finally {
  await browser.close();
}
