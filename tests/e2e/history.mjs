// History tab + Chart.js rendering and paging, driven through installed Edge.
import { chromium } from 'playwright';
import assert from 'node:assert';

const BASE = process.env.NJ_BASE || 'http://localhost:8000';
const user = 'hist_' + Date.now();

const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage();
page.on('dialog', d => d.accept());
const txt = async sel => (await page.locator(`[data-testid="${sel}"]`).innerText()).trim();

try {
  await page.goto(BASE + '/index.php');
  await page.waitForLoadState('networkidle');

  await page.click('[data-testid="auth-toggle"]');
  await page.fill('input[type="text"]', user);
  await page.fill('input[type="password"]', 'pw123456');
  await page.click('[data-testid="auth-submit"]');
  await page.waitForSelector('[data-testid="tap"]');

  // seed some counts today, sync to server
  await page.fill('[data-testid="manual"]', '42');
  await page.click('[data-testid="add"]');
  await page.click('[data-testid="refresh"]');
  await page.waitForTimeout(500);

  // open history
  await page.click('[data-testid="nav-history"]');
  await page.waitForTimeout(500);

  // newer arrow disabled at cursor 0
  assert.equal(await page.locator('[data-testid="chart-newer"]').isDisabled(), true, 'newer disabled at newest');
  const dailyRange = await txt('chart-range');
  assert.match(dailyRange, /\d{4}-\d{2}-\d{2} – \d{4}-\d{2}-\d{2}/, 'daily range is date–date');

  // read chart data length + today value straight from the Chart instance
  const dailyData = await page.evaluate(() => {
    const ch = Chart.getChart('njChart');
    return { n: ch.data.labels.length, last: ch.data.datasets[0].data[ch.data.labels.length - 1],
             lastLabel: ch.data.labels[ch.data.labels.length - 1] };
  });
  assert.equal(dailyData.n, 7, '7 daily bars');
  assert.equal(dailyData.last, 42, "today's bar = 42");

  // page older -> newer becomes enabled, range shifts
  await page.click('[data-testid="chart-older"]');
  await page.waitForTimeout(400);
  assert.equal(await page.locator('[data-testid="chart-newer"]').isDisabled(), false, 'newer enabled after paging older');

  // monthly: 12 bars, range is YYYY-MM
  await page.click('[data-testid="ct-monthly"]');
  await page.waitForTimeout(400);
  const monthly = await page.evaluate(() => Chart.getChart('njChart').data.labels.length);
  assert.equal(monthly, 12, '12 monthly bars');
  assert.match(await txt('chart-range'), /\d{4}-\d{2} – \d{4}-\d{2}/, 'monthly range is month–month');

  // yearly: 10 bars, range is YYYY
  await page.click('[data-testid="ct-yearly"]');
  await page.waitForTimeout(400);
  const yearly = await page.evaluate(() => Chart.getChart('njChart').data.labels.length);
  assert.equal(yearly, 10, '10 yearly bars');
  assert.match(await txt('chart-range'), /^\d{4} – \d{4}$/, 'yearly range is year–year');

  await page.screenshot({ path: 'tests/e2e/history.png', fullPage: true });
  console.log('e2e history OK (user ' + user + ')');
} finally {
  await browser.close();
}
