import assert from 'node:assert';
import I18N from '../../i18n.js';
const required = ['appTitle','tapToCount','totalJaap','totalMalas','todayJaap','todayMalas',
 'addPhysical','addCount','resetToday','resetConfirm','login','register','username','password',
 'logout','history','daily','monthly','yearly','refresh','loggedElsewhere','notSynced','deviceActiveConfirm'];
for (const k of required) {
  assert.ok(I18N.en[k], 'missing en.' + k);
  assert.ok(I18N.hi[k], 'missing hi.' + k);
}
assert.deepEqual(Object.keys(I18N.en).sort(), Object.keys(I18N.hi).sort());
console.log('test_i18n OK');
