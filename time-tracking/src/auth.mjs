// Auth: scrypt-hashed passwords, dashboard users with roles, agent tokens,
// in-memory sessions (re-validated on every request), and login rate limiting.
import { randomBytes, scryptSync, timingSafeEqual } from 'node:crypto';
import { getEmployeeByToken, getUserByUsername, getUserById } from './db.mjs';

const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'admin123';
// Optional pre-hashed admin password: "scrypt$<saltHex>$<keyHex>" (see hash-password.mjs).
const ADMIN_PASSWORD_HASH = process.env.ADMIN_PASSWORD_HASH || '';
// Set ADMIN_BOOTSTRAP=off once real admin users exist to disable the env-password login.
const BOOTSTRAP_ENABLED = (process.env.ADMIN_BOOTSTRAP || 'on').toLowerCase() !== 'off';
// Set TRUST_PROXY=1 when running behind nginx/Caddy so the client IP comes from X-Forwarded-For.
const TRUST_PROXY = process.env.TRUST_PROXY === '1';

const sessions = new Map(); // token -> { userId, role, username, exp }
const TTL_MS = 12 * 60 * 60 * 1000;

// ---- Password hashing (scrypt) ----
export function hashPassword(password) {
  const salt = randomBytes(16);
  const key = scryptSync(String(password), salt, 64);
  return `scrypt$${salt.toString('hex')}$${key.toString('hex')}`;
}
export function verifyPassword(password, stored) {
  try {
    const [scheme, saltHex, keyHex] = String(stored).split('$');
    if (scheme !== 'scrypt') return false;
    const key = Buffer.from(keyHex, 'hex');
    const test = scryptSync(String(password), Buffer.from(saltHex, 'hex'), key.length);
    return key.length === test.length && timingSafeEqual(key, test);
  } catch { return false; }
}
const DUMMY_HASH = hashPassword(randomBytes(8).toString('hex'));

function plainEqual(a, b) {
  const ab = Buffer.from(String(a)), bb = Buffer.from(String(b));
  return ab.length === bb.length && timingSafeEqual(ab, bb);
}

export function isUsingDefaultAdminPassword() {
  return BOOTSTRAP_ENABLED && !ADMIN_PASSWORD_HASH && ADMIN_PASSWORD === 'admin123';
}

export const USERNAME_RE = /^[A-Za-z0-9._-]{3,32}$/;

// ---- Client IP ----
export function clientIp(req) {
  if (TRUST_PROXY) {
    const fwd = String(req.headers['x-forwarded-for'] || '').split(',')[0].trim();
    if (fwd) return fwd;
  }
  return req.socket.remoteAddress || 'unknown';
}

// ---- Login rate limiting ----
// Failures are counted per IP AND per target account and are NOT reset by a
// successful login (so a valid login can't be used to keep brute-forcing).
const attempts = new Map(); // key -> { count, first }
const WINDOW_MS = 10 * 60 * 1000;
const LIMITS = { ip: 30, user: 10 };
function keysFor(ip, username) {
  return [['ip', `ip:${ip}`], ['user', `user:${(username || '#bootstrap-admin').toLowerCase()}`]];
}
function prune(now) {
  if (attempts.size < 5000) return;
  for (const [k, v] of attempts) if (now - v.first > WINDOW_MS) attempts.delete(k);
}
export function rateLimited(ip, username) {
  const now = Date.now();
  prune(now);
  return keysFor(ip, username).some(([kind, key]) => {
    const a = attempts.get(key);
    if (!a || now - a.first > WINDOW_MS) return false;
    return a.count >= LIMITS[kind];
  });
}
function noteFailure(ip, username) {
  const now = Date.now();
  for (const [, key] of keysFor(ip, username)) {
    const a = attempts.get(key);
    if (!a || now - a.first > WINDOW_MS) attempts.set(key, { count: 1, first: now });
    else a.count++;
  }
}

// ---- Login / sessions ----
export function login({ username, password, ip }) {
  let session = null;
  const name = typeof username === 'string' ? username.trim() : '';
  if (name) {
    const user = getUserByUsername(name);
    const ok = verifyPassword(password, user ? user.password_hash : DUMMY_HASH);
    if (user && ok) session = { userId: user.id, role: user.role, username: user.username };
  } else if (BOOTSTRAP_ENABLED) {
    const ok = ADMIN_PASSWORD_HASH ? verifyPassword(password, ADMIN_PASSWORD_HASH)
                                   : plainEqual(password, ADMIN_PASSWORD);
    if (ok) session = { userId: null, role: 'admin', username: 'admin' };
  }
  if (!session) { noteFailure(ip, name); return null; }
  const token = randomBytes(24).toString('hex');
  sessions.set(token, { ...session, exp: Date.now() + TTL_MS });
  return { token, role: session.role, username: session.username };
}

function bearer(req) {
  const m = /^Bearer\s+(\S+)$/i.exec(req.headers['authorization'] || '');
  return m ? m[1] : null;
}

// Returns the live session, re-reading the user so deletions/demotions apply now.
export function getSession(req) {
  const token = bearer(req);
  if (!token) return null;
  const s = sessions.get(token);
  if (!s || s.exp < Date.now()) { sessions.delete(token); return null; }
  if (s.userId != null) {
    const user = getUserById(s.userId);
    if (!user) { sessions.delete(token); return null; }
    s.role = user.role;
    s.username = user.username;
  } else if (!BOOTSTRAP_ENABLED) {
    sessions.delete(token);
    return null;
  }
  return s;
}

export function hasRole(req, roles) {
  const s = getSession(req);
  return s && roles.includes(s.role) ? s : null;
}

export function logout(req) {
  const token = bearer(req);
  if (token) sessions.delete(token);
}

export function purgeUserSessions(userId) {
  for (const [t, s] of sessions) if (s.userId === userId) sessions.delete(t);
}

// Agents authenticate with their per-employee token.
export function authAgent(req) {
  return getEmployeeByToken(bearer(req));
}
