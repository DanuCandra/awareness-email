const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const sandbox = { module: { exports: {} }, URL, Intl, Blob, TextEncoder, crypto: webcrypto, setTimeout };
const plain = value => JSON.parse(JSON.stringify(value));
for (const [file, name] of [['awareness-preview.js', 'AwarenessPreview'], ['awareness-admin-core.js', 'AwarenessAdmins']]) {
  sandbox.module = { exports: {} };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/', file), 'utf8'), sandbox);
  sandbox[name] = sandbox.module.exports;
}
const core = sandbox.AwarenessAdmins;
function storage() {
  const values = new Map();
  return { getItem: key => values.get(key) || null, setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) };
}
const input = (changes = {}) => ({ name: 'Admin IT Kedua', username: 'it.admin', email: 'admin.it@ast-indonesia.example', password: 'Contoh123!', passwordConfirmation: 'Contoh123!', ...changes });

test('sample initialization is stable and stores no plain text passwords', async () => {
  const target = storage();

  const first = await core.initialize(target), second = await core.initialize(target);

  assert.equal(first.admins.length, 1);
  assert.equal(second.admins[0].hash, first.admins[0].hash);
  assert.ok(!target.getItem(core.storageKey).includes('Admin123!'));
  assert.deepEqual(Object.keys(core.publicAdmin(first.admins[0])).sort(), ['createdAt', 'email', 'id', 'name', 'username']);
});

test('admin passwords require eight characters and matching confirmation', () => {
  assert.ok(core.validate(input({ password: '1234567', passwordConfirmation: '1234567' }), []).password);
  assert.deepEqual(plain(core.validate(input({ password: '12345678', passwordConfirmation: '12345678' }), [])), {});
  assert.ok(core.validate(input({ passwordConfirmation: 'different' }), []).passwordConfirmation);
  assert.ok(core.validate(input({ password: 'x'.repeat(129) }), []).password);
  assert.deepEqual(plain(core.validate(input({ password: '', passwordConfirmation: '' }), [], 'existing')), {});
  assert.ok(core.validate(input({ password: '', passwordConfirmation: 'different' }), [], 'existing').password);
});

test('duplicate email and username checks ignore casing and do not modify saved data', async () => {
  const target = storage();
  await core.initialize(target);
  const before = target.getItem(core.storageKey);

  const result = await core.saveAdmin(target, input({ username: ' ADMIN ', email: 'ADMIN@AST-INDONESIA.EXAMPLE' }));

  assert.equal(result.saved, false);
  assert.ok(result.errors.username);
  assert.ok(result.errors.email);
  assert.equal(target.getItem(core.storageKey), before);
  assert.ok(core.validate(input({ username: 'with space' }), []).username);
  assert.ok(core.validate(input({ username: 'with@email' }), []).username);
});

test('new admins can log in with either username or email and use their own password', async () => {
  const target = storage();
  const saved = await core.saveAdmin(target, input({ username: ' IT.Admin ', email: 'ADMIN.IT@AST-INDONESIA.EXAMPLE' }));

  assert.equal(saved.saved, true);
  assert.equal(saved.admin.username, 'it.admin');
  assert.equal((await core.login(target, ' IT.ADMIN ', 'Contoh123!')).id, saved.admin.id);
  core.logout(target);
  assert.equal(core.session(target), null);
  assert.equal((await core.login(target, 'ADMIN.IT@AST-INDONESIA.EXAMPLE', 'Contoh123!')).id, saved.admin.id);
  assert.equal(await core.login(target, 'it.admin', 'Incorrect123!'), null);
  assert.equal(await core.login(target, 'missing', 'Contoh123!'), null);
  assert.ok(!target.getItem(core.storageKey).includes('Contoh123!'));
});

test('editing an admin without a password preserves credentials and the current session identity updates', async () => {
  const target = storage();
  await core.initialize(target);
  await core.login(target, 'admin', 'Admin123!');
  const hash = core.load(target).admins[0].hash;

  const result = await core.saveAdmin(target, input({ name: 'Nama baru', username: 'admin', email: 'admin@ast-indonesia.example', password: '', passwordConfirmation: '' }), 'admin-demo');

  assert.equal(result.saved, true);
  assert.equal(result.sessionInvalidated, false);
  assert.equal(core.load(target).admins[0].hash, hash);
  assert.equal(core.session(target).name, 'Nama baru');
  assert.equal((await core.login(target, 'admin', 'Admin123!')).name, 'Nama baru');
});

test('changing the current password invalidates the old session and old password', async () => {
  const target = storage();
  await core.initialize(target);
  await core.login(target, 'admin', 'Admin123!');

  const result = await core.saveAdmin(target, input({ username: 'admin', email: 'admin@ast-indonesia.example', password: 'Baru123!', passwordConfirmation: 'Baru123!' }), 'admin-demo');

  assert.equal(result.sessionInvalidated, true);
  assert.equal(core.session(target), null);
  assert.equal(await core.login(target, 'admin', 'Admin123!'), null);
  assert.equal((await core.login(target, 'admin', 'Baru123!')).id, 'admin-demo');
});

test('deletion protects the last admin and the logged in account while removed accounts cannot log in', async () => {
  const target = storage();
  await core.initialize(target);
  assert.throws(() => core.removeAdmin(target, 'admin-demo'), /Minimal satu/);
  const saved = await core.saveAdmin(target, input());
  await core.login(target, 'admin', 'Admin123!');

  assert.throws(() => core.removeAdmin(target, 'admin-demo'), /sedang digunakan/);
  core.removeAdmin(target, saved.admin.id);

  assert.equal(core.load(target).admins.length, 1);
  assert.equal(await core.login(target, 'it.admin', 'Contoh123!'), null);
  assert.throws(() => core.removeAdmin(target, 'missing'), /tidak ditemukan/);
});

test('unknown admin edits cannot create an account or overwrite existing records', async () => {
  const target = storage();
  await core.initialize(target);
  const before = target.getItem(core.storageKey);

  const result = await core.saveAdmin(target, input(), 'missing');

  assert.equal(result.saved, false);
  assert.equal(target.getItem(core.storageKey), before);
});

test('blocked browser storage reports initialization and save failures', async () => {
  const blocked = { getItem: () => null, setItem: () => { throw new Error('Blocked'); } };

  await assert.rejects(core.initialize(blocked), /Blocked/);
  assert.equal(core.session(blocked), null);
  assert.equal(core.load({ getItem: () => '{broken' }), null);
});
