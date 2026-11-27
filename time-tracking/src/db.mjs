// SQLite database layer (Node's built-in node:sqlite; Node >= 22.13).
// All SQL lives in this file so a future PostgreSQL port stays contained here.
import { DatabaseSync } from 'node:sqlite';
import { randomBytes } from 'node:crypto';

const DB_PATH = process.env.DB_PATH || './hrghrd.db';
export const db = new DatabaseSync(DB_PATH);

db.exec('PRAGMA journal_mode = WAL;');
db.exec('PRAGMA foreign_keys = ON;');
db.exec('PRAGMA busy_timeout = 5000;');

db.exec(`
CREATE TABLE IF NOT EXISTS employees (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  email         TEXT UNIQUE,
  department    TEXT,
  position      TEXT,
  agent_token   TEXT UNIQUE NOT NULL,
  target_hours  REAL NOT NULL DEFAULT 8,      -- expected working hours per day
  timezone      TEXT NOT NULL DEFAULT 'Asia/Jakarta',
  active        INTEGER NOT NULL DEFAULT 1,
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Each row = one reporting interval sent by the (visible) desktop agent.
-- We store COUNTS only (keystrokes / mouse moves) and time, never key contents.
CREATE TABLE IF NOT EXISTS activity_intervals (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id     INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
  client_id       TEXT,                       -- agent-generated id (idempotent retries)
  hostname        TEXT,
  started_at      TEXT NOT NULL,              -- normalized ISO 8601 UTC (toISOString)
  ended_at        TEXT NOT NULL,
  active_seconds  INTEGER NOT NULL DEFAULT 0,
  idle_seconds    INTEGER NOT NULL DEFAULT 0,
  keystrokes      INTEGER NOT NULL DEFAULT 0,
  mouse_moves     INTEGER NOT NULL DEFAULT 0,
  foreground_app  TEXT,
  created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_intervals_emp_time ON activity_intervals(employee_id, started_at);

-- Last heartbeat per employee, for online/offline/paused status.
CREATE TABLE IF NOT EXISTS agent_status (
  employee_id   INTEGER PRIMARY KEY REFERENCES employees(id) ON DELETE CASCADE,
  hostname      TEXT,
  agent_version TEXT,
  paused        INTEGER NOT NULL DEFAULT 0,
  last_seen     TEXT NOT NULL
);

-- Maps an application (process) name to a productivity class.
CREATE TABLE IF NOT EXISTS app_categories (
  app_name   TEXT PRIMARY KEY COLLATE NOCASE,
  category   TEXT NOT NULL DEFAULT 'neutral',
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS app_settings (
  key   TEXT PRIMARY KEY,
  value TEXT
);

-- Dashboard users: 'admin' (full) or 'manager' (read KPI + approve timesheets).
CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT UNIQUE NOT NULL COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'manager',
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS agent_alerts (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id     INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
  type            TEXT NOT NULL,            -- 'offline' | 'online'
  offline_minutes INTEGER,
  at              TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_alerts_time ON agent_alerts(at DESC);

CREATE TABLE IF NOT EXISTS timesheets (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id     INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
  period_start    TEXT NOT NULL,            -- local date YYYY-MM-DD
  period_end      TEXT NOT NULL,
  tracked_seconds INTEGER NOT NULL DEFAULT 0,
  note            TEXT,
  status          TEXT NOT NULL DEFAULT 'submitted', -- submitted|approved|rejected
  reviewer_note   TEXT,
  reviewed_at     TEXT,
  submitted_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_timesheets_emp ON timesheets(employee_id, submitted_at DESC);
CREATE INDEX IF NOT EXISTS idx_timesheets_status ON timesheets(status, submitted_at DESC);
`);

// ---- Migrations for databases created by older versions ----
function hasColumn(table, col) {
  return db.prepare(`PRAGMA table_info(${table})`).all().some(c => c.name === col);
}
if (!hasColumn('employees', 'timezone'))
  db.exec("ALTER TABLE employees ADD COLUMN timezone TEXT NOT NULL DEFAULT 'Asia/Jakarta'");
if (!hasColumn('activity_intervals', 'client_id'))
  db.exec('ALTER TABLE activity_intervals ADD COLUMN client_id TEXT');
if (!hasColumn('agent_status', 'paused'))
  db.exec('ALTER TABLE agent_status ADD COLUMN paused INTEGER NOT NULL DEFAULT 0');
// Remove duplicate intervals left by older agents, then enforce uniqueness so
// retries/replays can never double-count time.
db.exec(`
  DELETE FROM activity_intervals WHERE id NOT IN
    (SELECT MIN(id) FROM activity_intervals GROUP BY employee_id, started_at);
  CREATE UNIQUE INDEX IF NOT EXISTS uq_intervals_start ON activity_intervals(employee_id, started_at);
  CREATE UNIQUE INDEX IF NOT EXISTS uq_intervals_client ON activity_intervals(employee_id, client_id)
    WHERE client_id IS NOT NULL;
`);

// Default categories (process names as reported by the Windows agent). INSERT OR
// IGNORE never overrides an admin's choice, so new defaults also reach old DBs.
const APP_DEFAULTS = {
  productive: ['code', 'vs code', 'devenv', 'idea64', 'pycharm64', 'rider64', 'webstorm64',
    'datagrip64', 'sublime_text', 'notepad++', 'excel', 'winword', 'word', 'powerpnt',
    'powerpoint', 'outlook', 'onenote', 'visio', 'msaccess', 'slack', 'teams', 'ms-teams',
    'figma', 'notion', 'photoshop', 'illustrator', 'acad', 'postman', 'docker desktop',
    'dbeaver', 'pbidesktop', 'acrobat', 'acrord32', 'saplogon', 'terminal', 'windowsterminal'],
  neutral: ['chrome', 'google chrome', 'msedge', 'edge', 'firefox', 'brave', 'opera',
    'explorer', 'finder', 'zoom', 'notepad'],
  unproductive: ['youtube', 'steam', 'epicgameslauncher', 'discord', 'spotify', 'netflix',
    'game', 'whatsapp', 'instagram', 'tiktok', 'telegram', 'robloxplayerbeta', 'minecraft'],
};
{
  const ins = db.prepare('INSERT OR IGNORE INTO app_categories (app_name, category) VALUES (?, ?)');
  for (const [cat, names] of Object.entries(APP_DEFAULTS))
    for (const name of names) ins.run(name, cat);
}

export function newToken() {
  return randomBytes(24).toString('hex');
}

// ---- Employees ----
const EMP_PUBLIC_COLS = 'id, name, email, department, position, target_hours, timezone, active, created_at';

export function createEmployee({ name, email, department, position, target_hours = 8, timezone = 'Asia/Jakarta' }) {
  const info = db.prepare(`
    INSERT INTO employees (name, email, department, position, agent_token, target_hours, timezone)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  `).run(name, email || null, department || null, position || null, newToken(), target_hours, timezone);
  return getEmployee(Number(info.lastInsertRowid));
}

export function getEmployee(id) {
  return db.prepare('SELECT * FROM employees WHERE id = ?').get(id);
}

export function getEmployeeByToken(token) {
  if (!token) return undefined;
  return db.prepare('SELECT * FROM employees WHERE agent_token = ? AND active = 1').get(token);
}

// Agent tokens are credentials: only include them when the caller is an admin.
export function listEmployees({ includeToken = false } = {}) {
  const cols = includeToken ? '*' : EMP_PUBLIC_COLS;
  return db.prepare(`SELECT ${cols} FROM employees ORDER BY active DESC, name`).all();
}

export function setEmployeeActive(id, active) {
  const info = db.prepare('UPDATE employees SET active = ? WHERE id = ?').run(active ? 1 : 0, id);
  if (info.changes === 0) throw new Error('employee not found');
  return getEmployee(id);
}

export function regenerateAgentToken(id) {
  const info = db.prepare('UPDATE employees SET agent_token = ? WHERE id = ?').run(newToken(), id);
  if (info.changes === 0) throw new Error('employee not found');
  return getEmployee(id);
}

// ---- Agent reporting ----
export function recordHeartbeat(employeeId, { hostname, agent_version, paused } = {}) {
  db.prepare(`
    INSERT INTO agent_status (employee_id, hostname, agent_version, paused, last_seen)
    VALUES (?, ?, ?, ?, datetime('now'))
    ON CONFLICT(employee_id) DO UPDATE SET
      hostname = excluded.hostname, agent_version = excluded.agent_version,
      paused = excluded.paused, last_seen = excluded.last_seen
  `).run(employeeId, hostname ?? null, agent_version ?? null, paused ? 1 : 0);
}

// Expects an already-validated interval (see server.mjs). Returns
// { id, duplicate } — duplicates (same client_id or started_at) are ignored.
export function recordInterval(employeeId, iv) {
  const info = db.prepare(`
    INSERT INTO activity_intervals
      (employee_id, client_id, hostname, started_at, ended_at, active_seconds, idle_seconds,
       keystrokes, mouse_moves, foreground_app)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT DO NOTHING
  `).run(employeeId, iv.client_id ?? null, iv.hostname ?? null, iv.started_at, iv.ended_at,
         iv.active_seconds, iv.idle_seconds, iv.keystrokes, iv.mouse_moves, iv.foreground_app ?? null);
  return { id: Number(info.lastInsertRowid), duplicate: info.changes === 0 };
}

// Online/offline/paused state for every active employee that has an agent.
export function agentStates(thresholdMin) {
  return db.prepare(`
    SELECT s.employee_id AS id, s.hostname, s.paused,
           strftime('%Y-%m-%dT%H:%M:%SZ', s.last_seen) AS last_seen,
           CAST((julianday('now') - julianday(s.last_seen)) * 1440 AS INTEGER) AS offline_minutes,
           CASE WHEN (julianday('now') - julianday(s.last_seen)) * 1440 >= ? THEN 'offline'
                WHEN s.paused = 1 THEN 'paused' ELSE 'online' END AS state
    FROM agent_status s
    JOIN employees e ON e.id = s.employee_id AND e.active = 1
  `).all(thresholdMin);
}

export function offlineAgents(thresholdMin) {
  return db.prepare(`
    SELECT e.id, e.name, e.department, e.position, s.hostname,
           strftime('%Y-%m-%dT%H:%M:%SZ', s.last_seen) AS last_seen,
           CAST((julianday('now') - julianday(s.last_seen)) * 1440 AS INTEGER) AS offline_minutes
    FROM agent_status s
    JOIN employees e ON e.id = s.employee_id
    WHERE e.active = 1 AND (julianday('now') - julianday(s.last_seen)) * 1440 >= ?
    ORDER BY offline_minutes DESC
  `).all(thresholdMin);
}

export function logAlert(employeeId, type, offlineMinutes = null) {
  db.prepare(`INSERT INTO agent_alerts (employee_id, type, offline_minutes, at)
              VALUES (?, ?, ?, datetime('now'))`).run(employeeId, type, offlineMinutes);
}

// Last logged alert type per employee (so a restart doesn't re-alert).
export function lastAlertTypes() {
  const rows = db.prepare(`
    SELECT a.employee_id, a.type FROM agent_alerts a
    WHERE a.id = (SELECT MAX(id) FROM agent_alerts b WHERE b.employee_id = a.employee_id)
  `).all();
  return new Map(rows.map(r => [r.employee_id, r.type]));
}

export function recentAlerts(limit = 20) {
  return db.prepare(`
    SELECT a.id, a.type, a.offline_minutes, strftime('%Y-%m-%dT%H:%M:%SZ', a.at) AS at,
           e.name, e.department
    FROM agent_alerts a JOIN employees e ON e.id = a.employee_id
    ORDER BY a.at DESC, a.id DESC LIMIT ?
  `).all(limit);
}

// ---- App categories ----
const VALID_CATEGORIES = new Set(['productive', 'neutral', 'unproductive']);

export function listCategories() {
  return db.prepare('SELECT app_name, category FROM app_categories ORDER BY category, app_name').all();
}

export function setCategory(appName, category) {
  const name = String(appName ?? '').trim();
  if (!name || name.length > 128) throw new Error('app_name required (max 128 chars)');
  if (!VALID_CATEGORIES.has(category)) throw new Error('invalid category');
  db.prepare(`
    INSERT INTO app_categories (app_name, category, updated_at) VALUES (?, ?, datetime('now'))
    ON CONFLICT(app_name) DO UPDATE SET category = excluded.category, updated_at = excluded.updated_at
  `).run(name, category);
  return { app_name: name, category };
}

export function observedApps() {
  return db.prepare(`
    SELECT ai.foreground_app AS app_name,
           COALESCE(ac.category, 'uncategorized') AS category,
           SUM(ai.active_seconds) AS active_seconds
    FROM activity_intervals ai
    LEFT JOIN app_categories ac ON ac.app_name = ai.foreground_app
    WHERE ai.foreground_app IS NOT NULL AND ai.foreground_app <> ''
    GROUP BY ai.foreground_app, ac.category
    ORDER BY active_seconds DESC
    LIMIT 200
  `).all();
}

// ---- Settings ----
export function getSetting(key, fallback = null) {
  const row = db.prepare('SELECT value FROM app_settings WHERE key = ?').get(key);
  return row ? row.value : fallback;
}
export function setSetting(key, value) {
  db.prepare(`INSERT INTO app_settings (key, value) VALUES (?, ?)
              ON CONFLICT(key) DO UPDATE SET value = excluded.value`).run(key, String(value));
}
export function offlineThresholdMin() {
  const v = parseInt(getSetting('offline_threshold_min', '15'), 10);
  return Number.isFinite(v) && v > 0 ? v : 15;
}

// ---- Timesheets ----
export function trackedSeconds(employeeId, fromIso, toIso) {
  return db.prepare(`
    SELECT COALESCE(SUM(active_seconds + idle_seconds), 0) AS secs
    FROM activity_intervals WHERE employee_id = ? AND started_at >= ? AND started_at <= ?
  `).get(employeeId, fromIso, toIso).secs || 0;
}

export function hasOverlappingTimesheet(employeeId, start, end) {
  return !!db.prepare(`
    SELECT 1 FROM timesheets
    WHERE employee_id = ? AND status IN ('submitted', 'approved')
      AND NOT (period_end < ? OR period_start > ?)
    LIMIT 1
  `).get(employeeId, start, end);
}

export function createTimesheet(employeeId, { period_start, period_end, note, tracked_seconds }) {
  const info = db.prepare(`
    INSERT INTO timesheets (employee_id, period_start, period_end, tracked_seconds, note, status)
    VALUES (?, ?, ?, ?, ?, 'submitted')
  `).run(employeeId, period_start, period_end, tracked_seconds, note || null);
  return getTimesheet(Number(info.lastInsertRowid));
}

const TS_COLS = `t.id, t.employee_id, t.period_start, t.period_end, t.tracked_seconds, t.note,
  t.status, t.reviewer_note,
  strftime('%Y-%m-%dT%H:%M:%SZ', t.reviewed_at) AS reviewed_at,
  strftime('%Y-%m-%dT%H:%M:%SZ', t.submitted_at) AS submitted_at`;

export function getTimesheet(id) {
  return db.prepare(`SELECT ${TS_COLS}, e.name, e.department, e.position
    FROM timesheets t JOIN employees e ON e.id = t.employee_id WHERE t.id = ?`).get(id);
}

export function listTimesheetsByEmployee(employeeId) {
  return db.prepare(`SELECT ${TS_COLS} FROM timesheets t WHERE t.employee_id = ?
    ORDER BY t.submitted_at DESC, t.id DESC LIMIT 100`).all(employeeId);
}

export function listTimesheets(status) {
  const base = `SELECT ${TS_COLS}, e.name, e.department, e.position
    FROM timesheets t JOIN employees e ON e.id = t.employee_id`;
  return status
    ? db.prepare(base + ' WHERE t.status = ? ORDER BY t.submitted_at DESC, t.id DESC LIMIT 200').all(status)
    : db.prepare(base + ` ORDER BY (t.status = 'submitted') DESC, t.submitted_at DESC, t.id DESC LIMIT 200`).all();
}

export function reviewTimesheet(id, decision, reviewerNote) {
  if (!['approved', 'rejected'].includes(decision)) throw new Error('invalid decision');
  const info = db.prepare(`
    UPDATE timesheets SET status = ?, reviewer_note = ?, reviewed_at = datetime('now')
    WHERE id = ? AND status = 'submitted'
  `).run(decision, reviewerNote || null, id);
  if (info.changes === 0) throw new Error('timesheet not found or already reviewed');
  return getTimesheet(id);
}

export function pendingTimesheetCount() {
  return db.prepare(`SELECT COUNT(*) AS n FROM timesheets WHERE status = 'submitted'`).get().n;
}

// ---- Users (dashboard accounts) ----
export function createUser({ username, password_hash, role = 'manager' }) {
  if (!['admin', 'manager'].includes(role)) throw new Error('invalid role');
  const info = db.prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')
    .run(username, password_hash, role);
  return db.prepare('SELECT id, username, role, created_at FROM users WHERE id = ?').get(Number(info.lastInsertRowid));
}
export function getUserByUsername(username) {
  if (!username) return undefined;
  return db.prepare('SELECT * FROM users WHERE username = ?').get(String(username).trim());
}
export function getUserById(id) {
  return db.prepare('SELECT * FROM users WHERE id = ?').get(id);
}
export function listUsers() {
  return db.prepare('SELECT id, username, role, created_at FROM users ORDER BY role, username').all();
}
export function deleteUser(id) {
  return db.prepare('DELETE FROM users WHERE id = ?').run(id).changes > 0;
}
