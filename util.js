export function formatNumber(n, useK) {
  n = Number(n) || 0;
  if (useK && Math.abs(n) >= 1000) {
    const v = (n / 1000).toFixed(1).replace(/\.0$/, '');
    return v + 'K';
  }
  return new Intl.NumberFormat('en-US').format(n);
}
export function deriveStats(total) {
  total = Number(total) || 0;
  return { total, malas: Math.floor(total / 108), current: total % 108 };
}
export function crossedMala(prev, next) {
  return Math.floor(next / 108) > Math.floor(prev / 108);
}
export function shouldSyncOnMala(prev, next, msSinceLastSync) {
  return crossedMala(prev, next) && msSinceLastSync >= 100000;
}
// Build the push payload {day: count} for every dirty day, excluding zero counts.
export function dirtyPayload(days, dirty) {
  const out = {};
  for (const k of Object.keys(dirty)) {
    const v = Number(days[k] || 0);
    if (v > 0) out[k] = v;
  }
  return out;
}
// Browser global fallback so the Alpine inline script can use these without importing.
if (typeof window !== 'undefined') {
  window.NJUtil = { formatNumber, deriveStats, crossedMala, shouldSyncOnMala, dirtyPayload };
}
