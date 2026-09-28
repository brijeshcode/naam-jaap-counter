import assert from 'node:assert';
import { dirtyPayload } from '../../util.js';
const days = {'2026-09-27':40, '2026-09-28':108};
const dirty = {'2026-09-27':1, '2026-09-28':1};      // both dirty (offline across midnight)
assert.deepEqual(dirtyPayload(days, dirty), {'2026-09-27':40, '2026-09-28':108});
assert.deepEqual(dirtyPayload({'x':0}, {'x':1}), {});   // zero excluded
assert.deepEqual(dirtyPayload(days, {}), {});           // nothing dirty
console.log('test_sync OK');
