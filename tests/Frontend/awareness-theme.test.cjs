const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/config.js'), 'utf8');
const prefix = 'templateCustomizer-security-awareness--';

function initialize(initial = {}, blocked = false) {
  const values = new Map(Object.entries(initial));
  let options;
  const window = { Helpers: { getCssVar: () => '#7367F0' } };
  const sandbox = {
    window,
    document: { documentElement: { dataset: { layout: 'app' }, getAttribute: name => name === 'data-template' ? 'security-awareness' : '/assets/' } },
    localStorage: {
      getItem: key => { if (blocked) throw new Error('Storage unavailable'); return values.get(key) ?? null; },
      setItem: (key, value) => values.set(key, value)
    },
    TemplateCustomizer: function (settings) { options = settings; }
  };
  Object.defineProperty(sandbox, 'templateName', { get: () => window.templateName });
  vm.runInNewContext(source, sandbox);
  return { values, options };
}

test('new browsers use the green preset as their customizable default', () => {
  const { values, options } = initialize();
  assert.equal(options.defaultPrimaryColor, '#0D9394');
  assert.equal(values.get(prefix + 'Color'), '#0D9394');
  assert.equal(options.displayCustomizer, true);
});

test('old default purple migrates once while language and layout preferences survive', () => {
  const { values, options } = initialize({ [prefix + 'Color']: '#7367F0', [prefix + 'Theme']: 'dark', [prefix + 'Lang']: 'id', unrelated: 'preserve' });
  assert.equal(values.get(prefix + 'Color'), '#0D9394');
  assert.equal(values.get(prefix + 'Theme'), 'dark');
  assert.equal(values.get('unrelated'), 'preserve');
  assert.equal(options.lang, 'id');
  const again = initialize({ ...Object.fromEntries(values), [prefix + 'Color']: '#7367F0' });
  assert.equal(again.values.get(prefix + 'Color'), '#7367F0');
});

test('an existing custom color remains selectable and stored', () => {
  const { values, options } = initialize({ [prefix + 'Color']: '#2092EC' });
  assert.equal(values.get(prefix + 'Color'), '#2092EC');
  assert.equal(options.defaultPrimaryColor, '#0D9394');
});

test('blocked browser storage does not prevent the green default from initializing', () => {
  const { options } = initialize({}, true);
  assert.equal(options.defaultPrimaryColor, '#0D9394');
  assert.equal(options.lang, 'en');
});