const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
for (const file of ['src/js/loadxsspanel.js', 'static/js/loadxsspanel.min.js']) {
    const context = vm.createContext({document: {}, $: () => ({ready() {}})});
    vm.runInContext(fs.readFileSync(path.join(root, file), 'utf8'), context);
    assert(/id:\s*['"]id['"]/.test(fs.readFileSync(path.join(root, file), 'utf8')));
    assert.equal(context.getGridApiUrl(), './api.php?cmd=list&project=default');
    context.currentProject = '中文 & test';
    assert.equal(new URL(context.getGridApiUrl(), 'http://localhost').searchParams.get('project'), '中文 & test');
    context.currentSearch = '中文';
    const search = new URL(context.getGridApiUrl(), 'http://localhost');
    assert.equal(search.searchParams.get('cmd'), 'search');
    assert.equal(search.searchParams.get('keyword'), '中文');
    assert.equal(search.searchParams.get('project'), '中文 & test');
    context.currentSearch = '';
    assert.equal(new URL(context.getGridApiUrl(), 'http://localhost').searchParams.get('project'), '中文 & test');
    assert.equal(context.formatParameterValue({nested: ['&lt;b&gt;']}), '{"nested":["&lt;b&gt;"]}');
    assert(!context.formatParameterValue({nested: '<img>'}).includes('<'));
    console.log('PASS project list/search/clear and nested rendering: ' + file);
}

// Execute the actual admin toolbar script with a lightweight jQuery adapter.
const values = {'#projectSelect': 'default', '#panelSearchInput': ''};
const events = {};
const urls = [];
let refreshes = 0;
function $(key) {
    if (typeof key === 'function') { key(); return; }
    return {
        val(value) { if (arguments.length) { values[key] = value; return this; } return values[key]; },
        trim() { return this; }, empty() { return this; }, append() { return this; }, text() { return this; },
        on(name, fn) { events[key + ':' + name] = fn; return this; }
    };
}
$.each = (object, fn) => Object.keys(object).forEach(k => fn(k, object[k]));
$.getJSON = (url, fn) => {
    urls.push(url);
    fn(url.includes('project_cmd') ? {default: {name: '<b>default</b>'}, other: {name: 'other'}} : {total: 1, today: 1, week: 1});
    return {fail() {}};
};
let exported;
const context = vm.createContext({$, document: 'document', CSRF_TOKEN: 'test-token', currentProject: 'default', currentSearch: '',
    fetch(url, options) { exported = {url, options}; return new Promise(() => {}); }, refreshGridSource() { refreshes++; }});
const scripts = [...fs.readFileSync(path.join(root, 'admin.php'), 'utf8').matchAll(/<script>([\s\S]*?)<\/script>/g)];
vm.runInContext(scripts[scripts.length - 1][1], context);
values['#projectSelect'] = 'other';
events['#projectSelect:change']();
assert.equal(context.currentProject, 'other');
assert(urls.some(url => url.includes('cmd=stats&project=other')));
values['#panelSearchInput'] = ' 中文 ';
events['#panelSearchBtn:click']();
assert.equal(context.currentSearch, '中文');
events['#panelSearchClear:click']();
assert.equal(context.currentSearch, '');
assert.equal(context.currentProject, 'other');
events['#exportJsonBtn:click']();
assert.equal(exported.url, 'api.php?cmd=export_json&project=other');
assert.equal(exported.options.headers['X-CSRF-Token'], 'test-token');
assert(refreshes >= 4);
const statsBeforeChange = urls.length;
events['document:recordsChanged']();
assert.equal(urls.length, statsBeforeChange + 1);
console.log('PASS admin toolbar switch/search/clear/stats/export wiring');

for (const file of ['src/js/notification.js', 'static/js/notification.min.js']) {
    let requested;
    const context = vm.createContext({document: {title: '', documentElement: {style: {}}}, urlbase: './api.php', $: {ajax(options) { requested = options.url; }}});
    vm.runInContext(fs.readFileSync(path.join(root, file), 'utf8'), context);
    const id = '1700000000-' + 'a'.repeat(32);
    context.showNotification(1, id, 2000);
    assert.equal(new URL(requested, 'http://localhost').searchParams.get('id'), id);
}
console.log('PASS notification lookup preserves full record ID (source and runtime)');
