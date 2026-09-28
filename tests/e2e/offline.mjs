// Offline accumulation + reconnect sync, driven through installed Edge.
import { chromium } from 'playwright';
import assert from 'node:assert';

const BASE = process.env.NJ_BASE || 'http://localhost:8000';
const user = 'off_' + Date.now();

const browser = await chromium.launch({ channel: 'msedge', headless: true });
const context = await browser.newContext();
const page = await context.newPage();
page.on('dialog', d => d.accept());
const txt = async sel => (await page.locator(`[data-testid="${sel}"]`).innerText()).trim();

try {
  await page.goto(BASE + '/index.php');
  await page.waitForLoadState('networkidle');

  // Register
  await page.click('[data-testid="auth-toggle"]');
  await page.fill('input[type="text"]', user);
  await page.fill('input[type="password"]', 'pw123456');
  await page.click('[data-testid="auth-submit"]');
  await page.waitForSelector('[data-testid="tap"]');
  await page.waitForTimeout(300);

  // Go OFFLINE, tap 5 times
  await context.setOffline(true);
  for (let i = 0; i < 5; i++) await page.click('[data-testid="tap"]');
  assert.equal(await txt('total'), '5', 'local total=5 while offline');

  // Confirm the server does NOT yet have it (fetch would fail offline anyway) —
  // come back ONLINE and force a sync via the refresh button.
  await context.setOffline(false);
  await page.click('[data-testid="refresh"]');
  await page.waitForTimeout(600);

  // Reload from server: pull must show the 5 that were accumulated offline.
  await page.reload();
  await page.waitForLoadState('networkidle');
  await page.waitForSelector('[data-testid="total"]');
  await page.waitForTimeout(500);
  assert.equal(await txt('total'), '5', 'server persisted the 5 offline taps after reconnect');

  console.log('e2e offline OK (user ' + user + ')');
} finally {
  await browser.close();
}
