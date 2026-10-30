import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {readFileSync} from 'node:fs';
const base = process.argv[2] || 'http://127.0.0.1:8791';
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname), 'Only loopback fixtures allowed');
const salt = '!KTMdg#^^I6Z!deIVR#SgpAI6qTN7oVl';
const md5 = v => createHash('md5').update(v).digest('hex');
const initial = await fetch(base + '/login.php');
const cookie = initial.headers.getSetCookie()[0].split(';')[0];
const html = await initial.text();
const challenge = html.match(/id="firesunCheck"[\s\S]*?value="([a-f0-9]+)"/)[1];
let password = 'fixture-pass';
for (let i = 0; i < 3; i++) password = md5(salt + password + salt);
for (let i = 0; i < 3; i++) password = md5(salt + password + challenge + salt);
const login = await fetch(base + '/login.php', {method:'POST', redirect:'manual', headers:{Cookie:cookie, 'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({password, firesunCheck:challenge})});
assert.equal(login.status, 302);
const session = login.headers.getSetCookie()[0].split(';')[0];
const admin = await fetch(base + '/admin.php', {headers:{Cookie:session}});
const token = (await admin.text()).match(/var CSRF_TOKEN = "([a-f0-9]+)"/)[1];
const headers = {Cookie:session, 'X-CSRF-Token':token};
for (const query of ['cmd[]=list','cmd=get&id[]=bad','cmd=search&keyword[]=bad']) {
    assert.equal((await fetch(base+'/api.php?'+query,{headers})).status,400);
}
const unauth = await fetch(base + '/api.php?cmd=list', {redirect:'manual'});
assert.equal(unauth.status,302);
for (const h of [{Cookie:session}, {Cookie:session, Referer:base+'/admin.php'}, {...headers, 'X-CSRF-Token':'bad'}]) {
    assert.equal((await fetch(base+'/api.php?cmd=list', {headers:h})).status,403);
}
for (const project of ['default','other']) {
    assert.equal((await fetch(base+'/index.php?'+new URLSearchParams({proj:project, q:'中文', 'nested[x]':'<b>'}))).status,200);
}
const api = async cmd => { const r=await fetch(base+'/api.php?'+cmd,{headers}); assert.equal(r.status,200); return r.json(); };
const records = await api('cmd=list');
assert(records.length >= 2);
assert(records.every(r => /^\d{10}-[a-f0-9]{32}$/.test(r.id)));
assert.equal((await api('cmd=search&project=other&keyword='+encodeURIComponent('中文'))).length,1);
const exported = await fetch(base+'/api.php?cmd=export_csv&project=other',{headers});
assert.equal(exported.status,200); assert.match(exported.headers.get('content-disposition'),/attachment/);
assert((await exported.text()).includes(records.find(r=>r.project==='other').id));
const keep = await fetch(base+'/index.php?'+new URLSearchParams({keepsession:'1',cookie:'fixture=only',location:'http://127.0.0.1:8792/dir/start'}));
assert.equal(keep.status,200);
const keepRun = await fetch(base+'/keepsession.php');
assert.equal(keepRun.status,200); assert(!/Fatal error|Warning/.test(await keepRun.text()));
const remove = records.find(r=>r.project==='other').id;
assert.equal(await api('cmd=del&id='+remove),true);
assert.equal(await api('cmd=get&id='+remove),false);
if (process.argv[3]) {
    const events = readFileSync(process.argv[3], 'utf8').trim().split(/\r?\n/).map(line => JSON.parse(line));
    assert(events.length >= 2 && events.length % 2 === 0);
    events.forEach((event, index) => {
        assert.equal(event.path, index % 2 ? '/dir/next' : '/dir/start');
        assert.equal(event.cookie, 'fixture=only');
    });
    console.log('PASS controlled redirect log: exact start/next pairs, no unauthorized port');
}
console.log('PASS real HTTP login/session, CSRF (Referer denied), AES/unique capture, project/search/export/delete and keep-session invocation');
