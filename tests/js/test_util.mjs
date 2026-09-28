import assert from 'node:assert';
import { formatNumber, deriveStats, crossedMala, shouldSyncOnMala } from '../../util.js';

assert.equal(formatNumber(1234, false), '1,234');
assert.equal(formatNumber(1200, true), '1.2K');
assert.equal(formatNumber(2000, true), '2K');       // drop .0
assert.equal(formatNumber(999, true), '999');       // below 1000, no K

assert.deepEqual(deriveStats(0), {total:0, malas:0, current:0});
assert.deepEqual(deriveStats(108), {total:108, malas:1, current:0});
assert.deepEqual(deriveStats(250), {total:250, malas:2, current:34});

assert.equal(crossedMala(107, 108), true);
assert.equal(crossedMala(108, 109), false);
assert.equal(crossedMala(50, 60), false);

assert.equal(shouldSyncOnMala(107, 108, 120000), true);   // crossed + >100s
assert.equal(shouldSyncOnMala(107, 108, 50000), false);   // crossed but <100s -> defer
assert.equal(shouldSyncOnMala(60, 70, 999999), false);    // no cross
console.log('test_util OK');
