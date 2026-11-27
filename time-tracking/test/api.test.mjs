// End-to-end API tests. Zero dependencies: run with `npm test` (node --test).
// Each suite starts the real server on a throwaway SQLite file.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DatabaseSync } from 'node:sqlite';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const TMP = mkdtempSync(join(tmpdir(), 'hrghrd-test-'));
const DB = join(TMP, 'test.db');
let port = 4100 + Math.floor(Math.random() * 800);
let srv;

function startServer(dbPath, p, env = {}) {
  return new Promise((ok, fail) => {
    const child = spawn(process.execPath, ['--no-warnings', 'src/server.mjs'], {
      cwd: ROOT, env: { ...process.env, DB_PATH: dbPath, PORT: String(p), ...env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    const t = setTimeout(() => fail(new Error('server did not start')), 10000);
    child.stdout.on('data', d => { if (String(d).includes('server on')) { clearTimeout(t); ok(child); } });
    child.on('exit', c => { clearTimeout(t); fail(new Error('server exited ' + c)); });
  });
}
const stop = (c) => new Promise(r => { if (!c || c.exitCode !== null) return r(); c.on('exit', r); c.kill('SIGTERM'); });

async function call(method, path, { token, body, raw, p = port } = {}) {
  const res = await fetch(`http://127.0.0.1:${p}${path}`, {
    method,
    headers: { 'content-type': 'application/json', ...(token ? { authorization: `Bearer ${token}` } : {}) },
    body: raw !== undefined ? raw : body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await res.text();
  let json; try { json = JSON.parse(text); } catch {}
  return { status: res.status, json, text, headers: res.headers };
}

const isoAgo = (min) => new Date(Date.now() - min * 60000).toISOString();
const localDate = (tz, d = new Date()) => new Intl.DateTimeFormat('en-CA', { timeZone: tz }).format(d);
let admin, jkt, wit; // admin session token, employees

before(async () => {
  srv = await startServer(DB, port);
  admin = (await call('POST', '/api/login', { body: { password: 'admin123' } })).json.token;
  jkt = (await call('POST', '/api/admin/employees', { token: admin,
    body: { name: 'Uji Jakarta', department: 'QA', timezone: 'Asia/Jakarta' } })).json.employee;
  wit = (await call('POST', '/api/admin/employees', { token: admin,
    body: { name: 'Uji Jayapura', timezone: 'Asia/Jayapura' } })).json.employee;
});
after(async () => { await stop(srv); rmSync(TMP, { recursive: true, force: true }); });

test('health + security headers incl. CSP', async () => {
  const r = await call('GET', '/api/health');
  assert.equal(r.status, 200);
  assert.match(r.headers.get('content-security-policy'), /script-src 'self'/);
  const page = await call('GET', '/');
  assert.equal(page.status, 200);
  assert.match(page.headers.get('content-security-policy'), /object-src 'none'/);
});

test('static path traversal is blocked', async () => {
  for (const p of ['/../src/db.mjs', '/%2e%2e/src/db.mjs', '/..%2fsrc%2fdb.mjs']) {
    const r = await call('GET', p);
    assert.notEqual(r.status, 200, p);
    assert.ok(!r.text.includes('DatabaseSync'), p);
  }
});

test('employee validation: timezone, target hours, email', async () => {
  const bad = async (body) => (await call('POST', '/api/admin/employees', { token: admin, body })).status;
  assert.equal(await bad({ name: 'X', timezone: 'Foo/Bar' }), 400);
  assert.equal(await bad({ name: 'X', target_hours: -5 }), 400);
  assert.equal(await bad({ name: 'X', target_hours: 'abc' }), 400);
  assert.equal(await bad({ name: 'X', email: 'not-an-email' }), 400);
  assert.equal(await bad({ name: '   ' }), 400);
  assert.equal((await call('POST', '/api/admin/employees', { token: admin, raw: 'null' })).status, 400);
});

test('agent tokens are hidden from managers; managers cannot mutate config', async () => {
  await call('POST', '/api/admin/users', { token: admin, body: { username: 'mgr1', password: 'rahasia123', role: 'manager' } });
  const mgr = (await call('POST', '/api/login', { body: { username: 'mgr1', password: 'rahasia123' } })).json.token;
  const asMgr = (await call('GET', '/api/admin/employees', { token: mgr })).json.employees;
  assert.ok(asMgr.length >= 2);
  assert.ok(asMgr.every(e => !('agent_token' in e)), 'manager must not see tokens');
  const asAdmin = (await call('GET', '/api/admin/employees', { token: admin })).json.employees;
  assert.ok(asAdmin.every(e => typeof e.agent_token === 'string'));
  for (const [m, p, b] of [['POST', '/api/admin/employees', { name: 'Z' }],
    ['POST', '/api/admin/categories', { app_name: 'x', category: 'productive' }],
    ['POST', '/api/admin/settings', { offline_threshold_min: 5 }],
    ['GET', '/api/admin/users'], ['POST', `/api/admin/employees/${jkt.id}/regenerate-token`]]) {
    assert.equal((await call(m, p, { token: mgr, body: b })).status, 403, `${m} ${p}`);
  }
});

test('agent activity: validation, normalization, idempotency', async () => {
  const T = jkt.agent_token;
  const post = (body) => call('POST', '/api/agent/activity', { token: T, body });
  const base = { started_at: isoAgo(30), ended_at: isoAgo(20), active_seconds: 500, idle_seconds: 100,
    keystrokes: 300, mouse_moves: 2000, foreground_app: 'Code', client_id: 'aaaaaaaa-0001' };
  assert.equal((await post(base)).status, 201);
  const dup = await post(base);
  assert.equal(dup.status, 200); assert.equal(dup.json.duplicate, true);
  const dupClient = await post({ ...base, started_at: isoAgo(19), ended_at: isoAgo(10) });
  assert.equal(dupClient.json.duplicate, true, 'same client_id is a duplicate');

  const rejects = [
    { ...base, client_id: undefined, idle_seconds: -5 },
    { ...base, client_id: undefined, active_seconds: 999999 },
    { ...base, client_id: undefined, active_seconds: 'abc' },
    { ...base, client_id: undefined, started_at: 'garbage' },
    { ...base, client_id: undefined, started_at: isoAgo(5), ended_at: isoAgo(10) },
    { ...base, client_id: undefined, started_at: isoAgo(-60), ended_at: isoAgo(-70) },
    { ...base, client_id: undefined, started_at: isoAgo(60 * 24 * 60), ended_at: isoAgo(60 * 24 * 60 - 10) },
    { ...base, client_id: undefined, started_at: isoAgo(50), ended_at: isoAgo(48), active_seconds: 600 },
    { ...base, client_id: 'bad id!' },
  ];
  for (const b of rejects) assert.equal((await post(b)).status, 422, JSON.stringify(b));

  // Offset timestamps are normalized to UTC ISO.
  const d = new Date(Date.now() - 3 * 3600e3);
  const wib = new Date(d.getTime() + 7 * 3600e3).toISOString().slice(0, 19) + '+07:00';
  const r = await post({ ...base, client_id: 'aaaaaaaa-0002', started_at: wib,
    ended_at: new Date(d.getTime() + 600e3).toISOString(), active_seconds: 300, idle_seconds: 300 });
  assert.equal(r.status, 201);
  const db = new DatabaseSync(DB);
  const row = db.prepare('SELECT started_at FROM activity_intervals WHERE client_id = ?').get('aaaaaaaa-0002');
  db.close();
  assert.equal(row.started_at, new Date(d.getTime()).toISOString().replace(/\.\d{3}Z$/, '.000Z'));
});

test('XSS payloads are stored as plain data (rendering escapes them)', async () => {
  const payload = '<img src=x onerror=alert(1)>';
  const r = await call('POST', '/api/agent/activity', { token: jkt.agent_token, body: {
    started_at: isoAgo(90), ended_at: isoAgo(80), active_seconds: 100, idle_seconds: 0,
    keystrokes: 1, mouse_moves: 1, foreground_app: payload } });
  assert.equal(r.status, 201);
  const obs = (await call('GET', '/api/admin/categories', { token: admin })).json.observed;
  assert.ok(obs.some(o => o.app_name === payload));
});

test('timesheets: real dates, timezone-correct totals, overlap, limits', async () => {
  const T = wit.agent_token;
  const ts = (body) => call('POST', '/api/agent/timesheets', { token: T, body });
  // 07:30–07:40 local WIT (UTC+9) is still the previous UTC day.
  const today = localDate('Asia/Jayapura');
  const [y, m, d] = today.split('-').map(Number);
  let start = new Date(Date.UTC(y, m - 1, d, 7, 30) - 9 * 3600e3);
  if (start > new Date(Date.now() - 15 * 60e3)) start = new Date(start.getTime() - 86400e3);
  const day = localDate('Asia/Jayapura', start);
  assert.equal((await call('POST', '/api/agent/activity', { token: T, body: {
    started_at: start.toISOString(), ended_at: new Date(start.getTime() + 600e3).toISOString(),
    active_seconds: 600, idle_seconds: 0, keystrokes: 10, mouse_moves: 10 } })).status, 201);

  assert.equal((await ts({ period_start: '2026-02-30', period_end: '2026-03-01' })).status, 400);
  assert.equal((await ts({ period_start: day, period_end: day, note: 'x'.repeat(501) })).status, 400);
  assert.equal((await ts({ period_start: '2099-01-01', period_end: '2099-01-02' })).status, 400);
  const ok = await ts({ period_start: day, period_end: day, note: 'pagi' });
  assert.equal(ok.status, 201);
  assert.equal(ok.json.timesheet.tracked_seconds, 600, 'local-day boundaries must be used');
  assert.equal((await ts({ period_start: day, period_end: day })).status, 409, 'overlap rejected');
});

test('KPI target counts expected weekdays, not only active days', async () => {
  const emp = (await call('POST', '/api/admin/employees', { token: admin,
    body: { name: 'Uji KPI', timezone: 'Asia/Jakarta', target_hours: 8 } })).json.employee;
  // Last fully-past Monday..Sunday week (Jakarta), with work on Wednesday only.
  const today = localDate('Asia/Jakarta');
  const [y, m, d] = today.split('-').map(Number);
  const todayUtc = Date.UTC(y, m - 1, d);
  const dow = new Date(todayUtc).getUTCDay();
  const monday = new Date(todayUtc - (((dow + 6) % 7) + 7) * 86400e3);
  const fmt = (t) => new Date(t).toISOString().slice(0, 10);
  const fromDate = fmt(monday), toDate = fmt(monday.getTime() + 6 * 86400e3);
  const wedNine = monday.getTime() + 2 * 86400e3 + 2 * 3600e3; // 09:00 WIB = 02:00 UTC
  for (let i = 0; i < 6; i++) {
    const s = wedNine + i * 600e3;
    await call('POST', '/api/agent/activity', { token: emp.agent_token, body: {
      started_at: new Date(s).toISOString(), ended_at: new Date(s + 600e3).toISOString(),
      active_seconds: 600, idle_seconds: 0, keystrokes: 5, mouse_moves: 5 } });
  }
  const r = await call('GET', `/api/admin/employees/${emp.id}/timeline?fromDate=${fromDate}&toDate=${toDate}`, { token: admin });
  assert.equal(r.status, 200);
  assert.equal(r.json.summary.days, 5);
  assert.equal(r.json.summary.active_days, 1);
  assert.equal(r.json.summary.kpi.hoursScore, Math.round(3600 / (8 * 3600 * 5) * 1000) / 10);
});

test('CSV export blocks formula injection and sends security headers', async () => {
  await call('POST', '/api/admin/employees', { token: admin, body: { name: '=HYPERLINK("http://evil")' } });
  const r = await call('GET', '/api/admin/export/kpi.csv?fromDate=2026-01-01&toDate=2026-01-07', { token: admin });
  assert.equal(r.status, 200);
  assert.match(r.text, /"'=HYPERLINK/);
  assert.match(r.headers.get('content-disposition'), /^attachment; filename="kpi_2026-01-01_2026-01-07\.csv"$/);
  assert.ok(r.headers.get('x-content-type-options'));
  assert.equal((await call('GET', '/api/admin/export/kpi.csv?fromDate=a";b&toDate=x', { token: admin })).status, 400);
});

test('paused heartbeat shows as paused, not offline', async () => {
  await call('POST', '/api/agent/heartbeat', { token: jkt.agent_token, body: { hostname: 'PC1', paused: true } });
  const k = await call('GET', '/api/admin/kpi', { token: admin });
  assert.equal(k.json.employees.find(e => e.employee.id === jkt.id).agent.state, 'paused');
});

test('sessions: logout and deleted users are revoked immediately', async () => {
  const u = (await call('POST', '/api/admin/users', { token: admin, body: { username: 'temp.user', password: 'rahasia123' } })).json.user;
  const t = (await call('POST', '/api/login', { body: { username: 'temp.user', password: 'rahasia123' } })).json.token;
  assert.equal((await call('GET', '/api/admin/kpi', { token: t })).status, 200);
  await call('DELETE', `/api/admin/users/${u.id}`, { token: admin });
  assert.equal((await call('GET', '/api/admin/kpi', { token: t })).status, 401);

  const t2 = (await call('POST', '/api/login', { body: { password: 'admin123' } })).json.token;
  await call('POST', '/api/logout', { token: t2 });
  assert.equal((await call('GET', '/api/admin/kpi', { token: t2 })).status, 401);
  assert.equal((await call('POST', '/api/admin/users', { token: admin, body: { username: '   ', password: 'rahasia123' } })).status, 400);
});

test('offline alerts are not duplicated across restarts', async () => {
  await call('POST', '/api/agent/heartbeat', { token: wit.agent_token, body: { hostname: 'PC2' } });
  const db = new DatabaseSync(DB);
  db.prepare(`UPDATE agent_status SET last_seen = datetime('now','-2 hours') WHERE employee_id = ?`).run(wit.id);
  const count = () => db.prepare(`SELECT COUNT(*) n FROM agent_alerts WHERE employee_id = ? AND type='offline'`).get(wit.id).n;
  await stop(srv); srv = await startServer(DB, port);
  await new Promise(r => setTimeout(r, 300));
  assert.equal(count(), 1);
  await stop(srv); srv = await startServer(DB, port);
  await new Promise(r => setTimeout(r, 300));
  assert.equal(count(), 1, 'restart must not re-alert');
  db.close();
  admin = (await call('POST', '/api/login', { body: { password: 'admin123' } })).json.token;
});

test('login rate limit is not reset by a successful login', async () => {
  const p2 = port + 1, db2 = join(TMP, 'rl.db');
  const s2 = await startServer(db2, p2);
  try {
    const a = (await call('POST', '/api/login', { p: p2, body: { password: 'admin123' } })).json.token;
    await call('POST', '/api/admin/users', { p: p2, token: a, body: { username: 'mgr2', password: 'rahasia123' } });
    for (let round = 0; round < 3; round++) {
      for (let i = 0; i < 4; i++) await call('POST', '/api/login', { p: p2, body: { password: 'wrong' } });
      await call('POST', '/api/login', { p: p2, body: { username: 'mgr2', password: 'rahasia123' } });
    }
    const r = await call('POST', '/api/login', { p: p2, body: { password: 'admin123' } });
    assert.equal(r.status, 429, 'admin account must be locked after 10+ failures');
  } finally { await stop(s2); }
});

test('seed refuses to wipe a database that has employees', async () => {
  const r = await new Promise(res => {
    const c = spawn(process.execPath, ['--no-warnings', 'src/seed.mjs'], { cwd: ROOT, env: { ...process.env, DB_PATH: DB } });
    let err = ''; c.stderr.on('data', d => err += d);
    c.on('exit', code => res({ code, err }));
  });
  assert.equal(r.code, 1);
  assert.match(r.err, /Refusing to seed/);
});
