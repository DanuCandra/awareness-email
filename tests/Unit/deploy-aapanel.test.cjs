const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const bash = process.platform === 'win32' ? 'C:/Program Files/Git/bin/bash.exe' : '/bin/bash';
const script = path.resolve(__dirname, '../../deploy-aapanel.sh');
const posix = value => process.platform === 'win32' ? value.replace(/\\/g, '/').replace(/^([A-Za-z]):/, (_, letter) => '/' + letter.toLowerCase()) : value;

function deploy(t, failure = '') {
  if (!fs.existsSync(bash)) { t.skip('Bash is required to exercise the deployment script.'); return null; }
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'awareness-deploy-test-'));
  t.after(() => {
    const absolute = path.resolve(dir);
    assert.equal(path.dirname(absolute), path.resolve(os.tmpdir()));
    assert.ok(path.basename(absolute).startsWith('awareness-deploy-test-'));
    fs.rmSync(absolute, { recursive: true, force: true });
  });
  const app = path.join(dir, 'app'), bin = path.join(dir, 'bin');
  for (const relative of ['vendor', 'bootstrap/cache', 'storage/framework', 'storage/app/private', 'public']) fs.mkdirSync(path.join(app, relative), { recursive: true });
  fs.mkdirSync(bin);
  for (const file of ['artisan', '.env', '.git', 'vendor/autoload.php']) fs.writeFileSync(path.join(app, file), 'fixture');
  fs.writeFileSync(path.join(dir, 'state'), 'RUNNING');
  function shim(name, code) { const file = path.join(bin, name); fs.writeFileSync(file, '#!/bin/bash\nset -eu\n' + code + '\n', { mode: 0o755 }); return posix(file); }
  const trace = 'printf "%s\\n" "' + '$0 $*' + '" >> "$DEPLOY_TRACE"\n';
  const php = shim('php', trace + `
if [[ "$1" == '-r' ]]; then
    if [[ "$2" == *random_bytes* ]]; then printf 'test-maintenance-secret';
    elif [[ "$2" == *getPdo* ]]; then [[ "$FAIL_PHASE" != 'runtime' ]] || exit 43; printf 'https://awareness.example.test'; fi
elif [[ "$1" == *composer ]]; then [[ "$FAIL_PHASE" != 'dependencies' ]] || exit 44
elif [[ "$1" == 'artisan' ]]; then
    case "$2" in
        down) touch storage/framework/down storage/framework/maintenance.php ;;
        migrate) [[ "$FAIL_PHASE" != 'migrations' ]] || exit 45 ;;
        up) rm -f storage/framework/down storage/framework/maintenance.php ;;
    esac
fi`);
  const composer = shim('composer', 'exit 0');
  shim('git', trace + `
if [[ "$1" == '-c' ]]; then shift 2; fi
case "$1" in
    symbolic-ref) echo main ;;
    diff) [[ "$FAIL_PHASE" != 'dirty' ]] || exit 1 ;;
    rev-parse) if [[ "$2" == 'HEAD' ]]; then printf 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'; else printf 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'; fi ;;
    fetch) [[ "$FAIL_PHASE" != 'fetch' ]] || exit 46 ;;
    merge-base) exit 0 ;;
    cat-file) [[ "$3" != *:.env ]] ;;
esac`);
  const supervisor = shim('supervisorctl', trace + `
if [[ "$1" == '-c' ]]; then shift 2; fi
case "$1" in
    status) state=$(cat "$DEPLOY_STATE"); printf 'awareness:awareness_00 %s fixture\\n' "$state"; [[ "$state" == 'RUNNING' ]] || exit 3 ;;
    stop) echo STOPPED > "$DEPLOY_STATE" ;;
    start) [[ "$FAIL_PHASE" != 'supervisor' ]] || exit 47; echo RUNNING > "$DEPLOY_STATE" ;;
esac`);
  shim('curl', trace + `
if [[ "$*" == *--write-out* ]]; then
    [[ "$FAIL_PHASE" != 'health' ]] || { printf 500; exit 22; }
    if [[ "$FAIL_PHASE" == 'post-health' && ! -f storage/framework/down ]]; then printf 500; exit 22; fi
    printf 200
fi`);
  shim('flock', trace + '[[ "$FAIL_PHASE" != "lock" ]]');
  for (const name of ['chown', 'find', 'sleep']) shim(name, trace + 'exit 0');
  shim('setsid', 'exit 0');
  const result = spawnSync(bash, [posix(script)], { encoding: 'utf8', timeout: 15000, env: {
    ...process.env, PATH: posix(bin) + path.delimiter + process.env.PATH,
    APP_DIR: posix(app), PHP_BIN: php, COMPOSER_BIN: composer, SUPERVISORCTL_BIN: supervisor, SUPERVISOR_CONFIG: '',
    BUILD_FRONTEND: '0', AWARENESS_DEPLOY_SNAPSHOT: '0', FAIL_PHASE: failure,
    DEPLOY_TRACE: posix(path.join(dir, 'trace')), DEPLOY_STATE: posix(path.join(dir, 'state'))
  } });
  assert.ifError(result.error);
  const output = result.stdout + result.stderr;
  const lines = fs.existsSync(path.join(dir, 'trace')) ? fs.readFileSync(path.join(dir, 'trace'), 'utf8') : '';
  return { result, output, trace: lines, app, state: fs.readFileSync(path.join(dir, 'state'), 'utf8').trim() };
}

test('successful webhook deploy stops processes before checkout and restarts after migration and HTTP health', t => {
  const run = deploy(t); if (!run) return;
  assert.equal(run.result.status, 0, run.output);
  assert.equal(run.state, 'RUNNING');
  assert.equal(fs.existsSync(path.join(run.app, 'storage/framework/down')), false);
  assert.equal(fs.readFileSync(path.join(run.app, 'storage/app/private/.last-successful-deploy'), 'utf8').trim(), 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
  assert.ok(run.trace.indexOf(' stop awareness:awareness_00') < run.trace.indexOf(' merge --ff-only'));
  assert.ok(run.trace.indexOf('artisan migrate --force') < run.trace.indexOf(' start awareness:awareness_00'));
  assert.ok(run.trace.indexOf(' start awareness:awareness_00') < run.trace.indexOf('artisan up'));
  assert.equal((run.trace.match(/--write-out/g) || []).length, 2);
  assert.doesNotMatch(run.trace, /cache:clear|git reset|migrate:rollback/);
});

for (const failure of ['migrations', 'dependencies', 'health', 'supervisor']) {
  test(failure + ' failure keeps maintenance and workers stopped without a false rollback', t => {
    const run = deploy(t, failure); if (!run) return;
    assert.notEqual(run.result.status, 0, run.output);
    assert.equal(run.state, 'STOPPED');
    assert.equal(fs.existsSync(path.join(run.app, 'storage/framework/down')), true);
    assert.equal(fs.existsSync(path.join(run.app, 'storage/app/private/.last-successful-deploy')), false);
    assert.doesNotMatch(run.trace, /artisan up|git reset|migrate:rollback/);
    assert.match(run.output, /DEPLOY FAILED/);
  });
}

for (const failure of ['fetch', 'dirty', 'runtime', 'lock']) {
  test(failure + ' preflight failure leaves live application and workers untouched', t => {
    const run = deploy(t, failure); if (!run) return;
    assert.notEqual(run.result.status, 0, run.output);
    assert.equal(run.state, 'RUNNING');
    assert.equal(fs.existsSync(path.join(run.app, 'storage/framework/down')), false);
    assert.doesNotMatch(run.trace, / stop awareness|artisan down| merge --ff-only/);
  });
}

test('HTTP failure after reopening the site restores maintenance and stops the restarted worker', t => {
  const run = deploy(t, 'post-health'); if (!run) return;
  assert.notEqual(run.result.status, 0, run.output);
  assert.equal(run.state, 'STOPPED');
  assert.equal(fs.existsSync(path.join(run.app, 'storage/framework/down')), true);
  assert.match(run.trace, /artisan up/);
  assert.equal(fs.existsSync(path.join(run.app, 'storage/app/private/.last-successful-deploy')), false);
});
