const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('request tokens remain valid UUIDs when randomUUID is unavailable on an office HTTP origin', () => {
  const sandbox = {
    window: { crypto: { getRandomValues: bytes => bytes.fill(255) } },
    document: { getElementById: () => null }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/awareness-api.js'), 'utf8'), sandbox);
  assert.equal(sandbox.window.AwarenessApi.newRequestToken(), 'ffffffff-ffff-4fff-bfff-ffffffffffff');
});
