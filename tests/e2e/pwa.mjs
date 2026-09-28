// PWA: service worker caches the shell so the app loads offline. Driven through Edge.
import { chromium } from 'playwright';
import assert from 'node:assert';

const BASE = process.env.NJ_BASE || 'http://localhost:8000';
const user = 'pwa_' + Date.now();

const browser = await chromium.launch({ channel: 'msedge', headless: true });
const context = await browser.newContext();
const page = await context.newPage();
page.setDefaultTimeout(10000);
page.on('dialog', d => d.accept());
const txt = async sel => (await page.locator(`[data-testid="${sel}"]`).innerText()).trim();

try {
  await page.goto(BASE + '/index.php');
  await page.waitForLoadState('networkidle');

  // manifest linked
  assert.equal(await page.locator('link[rel="manifest"]').count(), 1, 'manifest linked');

  // service worker becomes ready (bounded so a failed install can't hang the test)
  const swReady = await page.evaluate(() => Promise.race([
    navigator.serviceWorker.ready.then(r => !!r.active),
    new Promise(res => setTimeout(() => res(false), 8000))
  ]));
  assert.equal(swReady, true, 'service worker active');

  // register a user while online (so localStorage has a token -> app view on reload)
  await page.click('[data-testid="auth-toggle"]');
  await page.fill('input[type="text"]', user);
  await page.fill('input[type="password"]', 'pw123456');
  await page.click('[data-testid="auth-submit"]');
  await page.waitForSelector('[data-testid="tap"]');
  await page.waitForTimeout(400);

  // GO OFFLINE and reload — shell must come from cache
  await context.setOffline(true);
  await page.reload();
  await page.waitForSelector('[data-testid="tap"]', { timeout: 5000 });
  // and the app still works locally while offline
  await page.click('[data-testid="tap"]');
  assert.equal(await txt('current'), '1', 'tap works offline after cached reload');

  console.log('e2e pwa OK (user ' + user + ')');
} finally {
  await browser.close();
}
