export function formatNumber(n, useK, lang = 'en') {
  n = Number(n) || 0;
  if (useK) {
    // Hindi uses the Indian scale (thousand/lakh/crore); English the short scale.
    const units = lang === 'hi'
      ? [[1e7, 'Cr'], [1e5, 'L'], [1e3, 'K']]
      : [[1e9, 'B'], [1e6, 'M'], [1e3, 'K']];
    const a = Math.abs(n);
    for (const [threshold, suffix] of units) {
      if (a >= threshold) return (n / threshold).toFixed(1).replace(/\.0$/, '') + suffix;
    }
  }
  // Full number: Indian digit grouping for Hindi (5,00,000), Western for English.
  return new Intl.NumberFormat(lang === 'hi' ? 'en-IN' : 'en-US').format(n);
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
