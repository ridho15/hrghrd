// HTTP server — zero external deps (node:http + node:sqlite).
import http from 'node:http';
import https from 'node:https';
import { readFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join, normalize, extname, sep } from 'node:path';
import {
  db, createEmployee, getEmployee, listEmployees, setEmployeeActive, regenerateAgentToken,
  recordHeartbeat, recordInterval, agentStates, offlineAgents, recentAlerts, offlineThresholdMin,
  listCategories, setCategory, observedApps, getSetting, setSetting,
  trackedSeconds, hasOverlappingTimesheet, createTimesheet, listTimesheetsByEmployee,
  listTimesheets, reviewTimesheet, pendingTimesheetCount,
  createUser, listUsers, deleteUser,
} from './db.mjs';
import {
  login, logout, hasRole, rateLimited, clientIp, authAgent, purgeUserSessions,
  isUsingDefaultAdminPassword, hashPassword, USERNAME_RE,
} from './auth.mjs';
import { startAlertMonitor } from './alerts.mjs';
import { buildKpiReport } from './report.mjs';
import { allEmployeesSummary, employeeSummary, employeeTimeline } from './kpi.mjs';
import { isValidTimeZone, isValidDate, dateRangeUtc, localDateOf, daysBetween, safeTz } from './tz.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const PUBLIC_DIR = join(__dirname, '..', 'public');
const PORT = Number(process.env.PORT || 3000);

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
  '.txt': 'text/plain; charset=utf-8',
};

const SECURITY_HEADERS = {
  'x-content-type-options': 'nosniff',
  'x-frame-options': 'SAMEORIGIN',
  'referrer-policy': 'no-referrer',
  'cross-origin-opener-policy': 'same-origin',
  // No inline scripts anywhere: injected markup can't execute even if it slips through.
  'content-security-policy': "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
    + "img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; "
    + "form-action 'self'; frame-ancestors 'self'",
};

class HttpError extends Error {
  constructor(status, message) { super(message); this.status = status; }
}
const bad = (msg) => new HttpError(400, msg);
const invalid = (msg) => new HttpError(422, msg); // well-formed but rejected → agent drops it

function send(res, status, body, headers = {}) {
  const data = typeof body === 'string' || Buffer.isBuffer(body) ? body : JSON.stringify(body);
  res.writeHead(status, { 'content-type': 'application/json; charset=utf-8', ...SECURITY_HEADERS, ...headers });
  res.end(data);
}

async function readJson(req, limit = 64 * 1024) {
  const chunks = [];
  let size = 0;
  for await (const c of req) {
    size += c.length;
    if (size > limit) throw new HttpError(413, 'payload too large');
    chunks.push(c);
  }
  if (!chunks.length) return {};
  let v;
  try { v = JSON.parse(Buffer.concat(chunks).toString('utf8')); }
  catch { throw bad('invalid JSON'); }
  if (!v || typeof v !== 'object' || Array.isArray(v)) throw bad('JSON body must be an object');
  return v;
}

// ---- Small validators ----
function optStr(v, max, field) {
  if (v == null || v === '') return null;
  if (typeof v !== 'string') throw bad(`${field} must be a string`);
  const s = v.replace(/[\u0000-\u001f\u007f]/g, '').trim();
  if (s.length > max) throw bad(`${field} max ${max} chars`);
  return s || null;
}
function nonNegInt(v, field, max) {
  if (typeof v !== 'number' || !Number.isInteger(v) || v < 0 || v > max)
    throw invalid(`${field} must be an integer 0..${max}`);
  return v;
}

// Validate + normalize one agent interval. Timestamps are re-serialized with
// toISOString() so lexical range comparisons in SQL are always correct.
function validateInterval(b) {
  const s = Date.parse(b.started_at), e = Date.parse(b.ended_at);
  if (!Number.isFinite(s) || !Number.isFinite(e)) throw invalid('started_at/ended_at must be ISO timestamps');
  if (e <= s) throw invalid('ended_at must be after started_at');
  const span = (e - s) / 1000;
  if (span > 24 * 3600) throw invalid('interval longer than 24h');
  const now = Date.now();
  if (e > now + 5 * 60 * 1000) throw invalid('interval is in the future');
  if (s < now - 45 * 86400 * 1000) throw invalid('interval older than 45 days');
  const active = nonNegInt(b.active_seconds, 'active_seconds', 86400);
  const idle = nonNegInt(b.idle_seconds ?? 0, 'idle_seconds', 86400);
  if (active + idle > span + 60) throw invalid('active+idle exceeds the interval length');
  const clientId = b.client_id == null ? null : String(b.client_id);
  if (clientId && !/^[A-Za-z0-9-]{8,64}$/.test(clientId)) throw invalid('client_id format');
  let app = null;
  try { app = optStr(b.foreground_app, 128, 'foreground_app'); } catch (err) { throw invalid(err.message); }
  return {
    client_id: clientId,
    hostname: typeof b.hostname === 'string' ? b.hostname.slice(0, 64) : null,
    started_at: new Date(s).toISOString(),
    ended_at: new Date(e).toISOString(),
    active_seconds: active,
    idle_seconds: idle,
    keystrokes: nonNegInt(b.keystrokes ?? 0, 'keystrokes', Math.ceil(span) * 25 + 100),
    mouse_moves: nonNegInt(b.mouse_moves ?? 0, 'mouse_moves', Math.ceil(span) * 1000 + 1000),
    foreground_app: app,
  };
}

// Query → range for KPI/timeline: local dates (preferred) or raw ISO instants.
function rangeQuery(url) {
  const q = {};
  const fromDate = url.searchParams.get('fromDate'), toDate = url.searchParams.get('toDate');
  if (fromDate || toDate) {
    if (!isValidDate(fromDate) || !isValidDate(toDate) || fromDate > toDate)
      throw bad('fromDate/toDate must be valid YYYY-MM-DD with fromDate <= toDate');
    if (daysBetween(fromDate, toDate) > 400) throw bad('range too long (max 400 days)');
    q.fromDate = fromDate; q.toDate = toDate;
    return q;
  }
  for (const k of ['from', 'to']) {
    const v = url.searchParams.get(k);
    if (v) {
      const t = Date.parse(v);
      if (!Number.isFinite(t)) throw bad(`${k} must be an ISO timestamp`);
      q[k] = new Date(t).toISOString();
    }
  }
  return q;
}

// ---- CSV ----
function csvCell(v) {
  let s = String(v ?? '');
  if (/^[=+\-@\t\r]/.test(s)) s = "'" + s; // block spreadsheet formula injection
  return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
}
const toCsv = (headers, rows) =>
  '﻿' + [headers.join(','), ...rows.map(r => r.map(csvCell).join(','))].join('\r\n') + '\r\n';
function sendCsv(res, filename, text) {
  const safe = filename.replace(/[^0-9A-Za-z_.-]/g, '_');
  res.writeHead(200, {
    'content-type': 'text/csv; charset=utf-8',
    'content-disposition': `attachment; filename="${safe}"`,
    ...SECURITY_HEADERS,
  });
  res.end(text);
}
const hms = (sec) => {
  sec = Math.round(sec || 0);
  const p = (n) => String(n).padStart(2, '0');
  return `${p(Math.floor(sec / 3600))}:${p(Math.floor(sec % 3600 / 60))}:${p(sec % 60)}`;
};

// ---- Static files ----
async function serveStatic(res, urlPath) {
  const rel = urlPath === '/' ? '/index.html' : urlPath;
  if (rel.includes('\0')) return send(res, 400, { error: 'bad path' });
  const full = normalize(join(PUBLIC_DIR, rel));
  if (!full.startsWith(PUBLIC_DIR + sep)) return send(res, 403, { error: 'forbidden' });
  try {
    const data = await readFile(full);
    res.writeHead(200, {
      'content-type': MIME[extname(full)] || 'application/octet-stream',
      // Fonts never change in place; app files are revalidated on every load.
      'cache-control': extname(full) === '.woff2' ? 'public, max-age=604800' : 'no-cache',
      ...SECURITY_HEADERS,
    });
    res.end(data);
  } catch {
    send(res, 404, { error: 'not found' });
  }
}

function withAgentState(rows) {
  const states = new Map(agentStates(offlineThresholdMin()).map(s => [s.id, s]));
  return rows.map(r => ({ ...r, agent: states.get(r.employee.id) || { state: 'none' } }));
}

const handler = async (req, res) => {
  try {
    const url = new URL(req.url, 'http://localhost');
    const path = url.pathname;
    const method = req.method || 'GET';

    if (path === '/api/health') return send(res, 200, { ok: true });

    // ---------- Agent API (per-employee token) ----------
    if (path.startsWith('/api/agent/')) {
      const emp = authAgent(req);
      if (!emp) return send(res, 401, { error: 'invalid agent token' });

      if (path === '/api/agent/heartbeat' && method === 'POST') {
        const b = await readJson(req);
        recordHeartbeat(emp.id, {
          hostname: typeof b.hostname === 'string' ? b.hostname.slice(0, 64) : null,
          agent_version: typeof b.agent_version === 'string' ? b.agent_version.slice(0, 32) : null,
          paused: b.paused === true,
        });
        return send(res, 200, { ok: true, employee: emp.name });
      }

      if (path === '/api/agent/activity' && method === 'POST') {
        const b = await readJson(req);
        const iv = validateInterval(b);
        const { id, duplicate } = recordInterval(emp.id, iv);
        recordHeartbeat(emp.id, {
          hostname: iv.hostname,
          agent_version: typeof b.agent_version === 'string' ? b.agent_version.slice(0, 32) : null,
          paused: b.paused === true,
        });
        // Duplicates are acknowledged with 200 so the agent drops them from its queue.
        return send(res, duplicate ? 200 : 201, { ok: true, duplicate, interval_id: duplicate ? null : id });
      }

      // Self-view: an employee sees ONLY their own data.
      if (path === '/api/agent/me' && method === 'GET') {
        const q = rangeQuery(url);
        return send(res, 200, {
          summary: employeeSummary(emp.id, q),
          timeline: employeeTimeline(emp.id, q),
        });
      }

      if (path === '/api/agent/timesheets' && method === 'POST') {
        const b = await readJson(req);
        const start = b.period_start, end = b.period_end;
        if (!isValidDate(start) || !isValidDate(end)) throw bad('Tanggal periode tidak valid.');
        if (end < start) throw bad('Tanggal "Sampai" tidak boleh sebelum tanggal "Dari".');
        if (daysBetween(start, end) > 62) throw bad('Periode terlalu panjang, maksimal 62 hari.');
        const tz = safeTz(emp.timezone);
        if (start > localDateOf(new Date(), tz)) throw bad('Periode tidak boleh dimulai di masa depan.');
        if (typeof b.note === 'string' && b.note.length > 500) throw bad('Catatan maksimal 500 karakter.');
        const note = optStr(b.note, 500, 'note');
        if (hasOverlappingTimesheet(emp.id, start, end))
          return send(res, 409, { error: 'Sudah ada timesheet (menunggu/disetujui) yang tumpang-tindih dengan periode ini.' });
        const r = dateRangeUtc(start, end, tz);
        const ts = createTimesheet(emp.id, {
          period_start: start, period_end: end, note,
          tracked_seconds: trackedSeconds(emp.id, r.from, r.to),
        });
        return send(res, 201, { ok: true, timesheet: ts });
      }

      if (path === '/api/agent/timesheets' && method === 'GET')
        return send(res, 200, { timesheets: listTimesheetsByEmployee(emp.id) });

      return send(res, 404, { error: 'unknown agent route' });
    }

    // ---------- Dashboard auth ----------
    if (path === '/api/login' && method === 'POST') {
      const ip = clientIp(req);
      const b = await readJson(req, 4096);
      const username = typeof b.username === 'string' ? b.username.trim() : '';
      if (rateLimited(ip, username)) return send(res, 429, { error: 'Terlalu banyak percobaan. Coba lagi dalam 10 menit.' });
      const result = login({ username, password: typeof b.password === 'string' ? b.password : '', ip });
      if (!result) return send(res, 401, { error: 'Username atau password salah' });
      return send(res, 200, { ...result, default_password: isUsingDefaultAdminPassword() });
    }

    if (path === '/api/logout' && method === 'POST') {
      logout(req);
      return send(res, 200, { ok: true });
    }

    // ---------- Admin API (admin or manager session) ----------
    if (path.startsWith('/api/admin/')) {
      const session = hasRole(req, ['admin', 'manager']);
      if (!session) return send(res, 401, { error: 'unauthorized' });
      const isAdmin = session.role === 'admin';
      const adminOnly =
        (path.startsWith('/api/admin/employees') && method !== 'GET') ||
        (path === '/api/admin/categories' && method === 'POST') ||
        (path === '/api/admin/settings' && method === 'POST') ||
        path.startsWith('/api/admin/users');
      if (adminOnly && !isAdmin) return send(res, 403, { error: 'admin only' });

      if (path === '/api/admin/me' && method === 'GET')
        return send(res, 200, { role: session.role, username: session.username });

      // ---- Users ----
      if (path === '/api/admin/users' && method === 'GET')
        return send(res, 200, { users: listUsers() });
      if (path === '/api/admin/users' && method === 'POST') {
        const b = await readJson(req, 4096);
        const username = typeof b.username === 'string' ? b.username.trim() : '';
        if (!USERNAME_RE.test(username)) throw bad('username 3-32 karakter: huruf, angka, titik, _ atau -');
        if (typeof b.password !== 'string' || b.password.length < 8 || b.password.length > 128)
          throw bad('password minimal 8 karakter');
        const role = b.role === 'admin' ? 'admin' : 'manager';
        try {
          return send(res, 201, { user: createUser({ username, password_hash: hashPassword(b.password), role }) });
        } catch (e) {
          if (/UNIQUE/.test(String(e))) throw bad('username sudah dipakai');
          throw e;
        }
      }
      const mUser = /^\/api\/admin\/users\/(\d+)$/.exec(path);
      if (mUser && method === 'DELETE') {
        const id = Number(mUser[1]);
        if (session.userId === id) throw bad('Tidak bisa menghapus akun sendiri');
        const ok = deleteUser(id);
        purgeUserSessions(id);
        return send(res, 200, { ok });
      }

      // ---- KPI & employees ----
      if (path === '/api/admin/kpi' && method === 'GET') {
        const q = rangeQuery(url);
        return send(res, 200, {
          ...q, threshold_min: offlineThresholdMin(),
          employees: withAgentState(allEmployeesSummary(q)),
        });
      }

      if (path === '/api/admin/employees' && method === 'GET')
        return send(res, 200, { employees: listEmployees({ includeToken: isAdmin }) });

      if (path === '/api/admin/employees' && method === 'POST') {
        const b = await readJson(req, 8192);
        const name = optStr(b.name, 100, 'name');
        if (!name) throw bad('Nama wajib diisi');
        const email = optStr(b.email, 120, 'email');
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw bad('format email tidak valid');
        const timezone = b.timezone == null || b.timezone === '' ? 'Asia/Jakarta' : b.timezone;
        if (!isValidTimeZone(timezone)) throw bad('timezone tidak dikenal');
        const target = b.target_hours == null || b.target_hours === '' ? 8 : Number(b.target_hours);
        if (!Number.isFinite(target) || target < 1 || target > 24) throw bad('target jam harus 1-24');
        try {
          const emp = createEmployee({
            name, email, timezone, target_hours: target,
            department: optStr(b.department, 100, 'department'),
            position: optStr(b.position, 100, 'position'),
          });
          return send(res, 201, { employee: emp }); // includes agent_token (admin only route)
        } catch (e) {
          if (/UNIQUE/.test(String(e))) throw bad('email sudah dipakai');
          throw e;
        }
      }

      const mActive = /^\/api\/admin\/employees\/(\d+)\/active$/.exec(path);
      if (mActive && method === 'POST') {
        const b = await readJson(req, 1024);
        if (!getEmployee(Number(mActive[1]))) return send(res, 404, { error: 'employee not found' });
        return send(res, 200, { employee: setEmployeeActive(Number(mActive[1]), b.active === true) });
      }

      const mRegen = /^\/api\/admin\/employees\/(\d+)\/regenerate-token$/.exec(path);
      if (mRegen && method === 'POST') {
        if (!getEmployee(Number(mRegen[1]))) return send(res, 404, { error: 'employee not found' });
        return send(res, 200, { employee: regenerateAgentToken(Number(mRegen[1])) });
      }

      const mTimeline = /^\/api\/admin\/employees\/(\d+)\/timeline$/.exec(path);
      if (mTimeline && method === 'GET') {
        const id = Number(mTimeline[1]);
        if (!getEmployee(id)) return send(res, 404, { error: 'employee not found' });
        const q = rangeQuery(url);
        return send(res, 200, { summary: employeeSummary(id, q), timeline: employeeTimeline(id, q) });
      }

      // ---- Export ----
      if (path === '/api/admin/export/kpi.csv' && method === 'GET') {
        const q = rangeQuery(url);
        const rows = allEmployeesSummary(q).map(s => [
          s.employee.name, s.employee.department || '', s.employee.position || '', s.employee.timezone,
          hms(s.working_seconds), hms(s.active_seconds), hms(s.idle_seconds), s.days,
          s.kpi.activityPct, s.kpi.productivePct, s.keystrokes, s.mouse_moves,
          Math.round(s.productivity.productive / 60), Math.round(s.productivity.neutral / 60),
          Math.round(s.productivity.unproductive / 60), s.kpi.score,
        ]);
        const csv = toCsv(
          ['Nama', 'Departemen', 'Posisi', 'Timezone', 'Working Time', 'Active', 'Idle', 'Hari kerja',
           'Activity %', 'Produktif %', 'Keystrokes', 'Mouse Moves',
           'Produktif (m)', 'Netral (m)', 'Non-produktif (m)', 'KPI'], rows);
        const label = q.fromDate ? `${q.fromDate}_${q.toDate}` : 'all';
        return sendCsv(res, `kpi_${label}.csv`, csv);
      }

      if (path === '/api/admin/report' && method === 'GET') {
        const html = buildKpiReport(rangeQuery(url));
        res.writeHead(200, { 'content-type': 'text/html; charset=utf-8', ...SECURITY_HEADERS });
        return res.end(html);
      }

      // ---- Alerts & settings ----
      if (path === '/api/admin/alerts' && method === 'GET') {
        const thr = offlineThresholdMin();
        return send(res, 200, {
          threshold_min: thr,
          webhook_configured: !!getSetting('alert_webhook_url', ''),
          offline: offlineAgents(thr),
          recent: recentAlerts(20),
        });
      }

      if (path === '/api/admin/settings' && method === 'POST') {
        const b = await readJson(req, 4096);
        if (b.offline_threshold_min != null) {
          const n = Number(b.offline_threshold_min);
          if (!Number.isInteger(n) || n < 1 || n > 1440) throw bad('batas offline harus 1..1440 menit');
          setSetting('offline_threshold_min', n);
        }
        if (b.alert_webhook_url != null) {
          const u = String(b.alert_webhook_url).trim();
          if (u && (!/^https?:\/\/[^\s]+$/i.test(u) || u.length > 500)) throw bad('webhook harus URL http(s)');
          setSetting('alert_webhook_url', u); // '' clears it
        }
        return send(res, 200, { ok: true });
      }

      // ---- Timesheets ----
      if (path === '/api/admin/timesheets' && method === 'GET') {
        const status = url.searchParams.get('status');
        if (status && !['submitted', 'approved', 'rejected'].includes(status)) throw bad('invalid status');
        return send(res, 200, { pending: pendingTimesheetCount(), timesheets: listTimesheets(status) });
      }

      const mReview = /^\/api\/admin\/timesheets\/(\d+)\/review$/.exec(path);
      if (mReview && method === 'POST') {
        const b = await readJson(req, 4096);
        try {
          const ts = reviewTimesheet(Number(mReview[1]), b.decision, optStr(b.note, 500, 'note'));
          return send(res, 200, { ok: true, timesheet: ts });
        } catch (e) {
          if (e instanceof HttpError) throw e;
          throw bad(e.message);
        }
      }

      // ---- Categories ----
      if (path === '/api/admin/categories' && method === 'GET')
        return send(res, 200, { categories: listCategories(), observed: observedApps() });

      if (path === '/api/admin/categories' && method === 'POST') {
        const b = await readJson(req, 4096);
        try { return send(res, 200, { ok: true, ...setCategory(b.app_name, b.category) }); }
        catch (e) { throw bad(e.message); }
      }

      return send(res, 404, { error: 'unknown admin route' });
    }

    if (path.startsWith('/api/')) return send(res, 404, { error: 'not found' });

    // ---------- Static dashboard ----------
    if (method === 'GET' || method === 'HEAD') return serveStatic(res, path);
    return send(res, 405, { error: 'method not allowed' });
  } catch (err) {
    if (err instanceof HttpError) return send(res, err.status, { error: err.message });
    console.error('Unhandled error:', err);
    if (!res.headersSent) return send(res, 500, { error: 'internal server error' });
    res.end();
  }
};

// HTTPS when TLS_CERT + TLS_KEY are provided, otherwise HTTP (put behind a
// TLS-terminating reverse proxy such as nginx/Caddy in production).
const TLS_CERT = process.env.TLS_CERT, TLS_KEY = process.env.TLS_KEY;
const server = TLS_CERT && TLS_KEY
  ? https.createServer({ cert: readFileSync(TLS_CERT), key: readFileSync(TLS_KEY) }, handler)
  : http.createServer(handler);
const scheme = TLS_CERT && TLS_KEY ? 'https' : 'http';

server.listen(PORT, () => {
  console.log(`hrghrd server on ${scheme}://localhost:${PORT}`);
  if (isUsingDefaultAdminPassword())
    console.log('WARNING: using default admin password "admin123". Set ADMIN_PASSWORD_HASH (or ADMIN_PASSWORD) in production.');
  startAlertMonitor(() => new Map(listEmployees().map(e => [e.id, e.name])));
});

// Graceful shutdown (docker stop / systemd): finish in-flight requests, close DB.
for (const sig of ['SIGTERM', 'SIGINT']) {
  process.on(sig, () => {
    server.close(() => { try { db.close(); } catch {} process.exit(0); });
    setTimeout(() => process.exit(0), 5000).unref();
  });
}
