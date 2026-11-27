// KPI aggregation — turns raw activity intervals into per-employee rollups.
// Ranges can be given as local dates (fromDate/toDate, interpreted in EACH
// employee's own timezone) or as raw ISO instants (from/to).
import { db } from './db.mjs';
import {
  safeTz, dateRangeUtc, localDateOf, addDays, isWeekend, isValidDate, daysBetween,
} from './tz.mjs';

const MAX_RANGE_DAYS = 400;

function rangeClause(from, to) {
  const parts = [], params = [];
  if (from) { parts.push('started_at >= ?'); params.push(from); }
  if (to)   { parts.push('started_at <= ?'); params.push(to); }
  return { where: parts.length ? 'AND ' + parts.join(' AND ') : '', params };
}

// Resolve a query into ISO bounds for one employee.
export function resolveRange(emp, q = {}) {
  const tz = safeTz(emp.timezone);
  if (isValidDate(q.fromDate) && isValidDate(q.toDate) && q.fromDate <= q.toDate
      && daysBetween(q.fromDate, q.toDate) <= MAX_RANGE_DAYS) {
    return { ...dateRangeUtc(q.fromDate, q.toDate, tz), fromDate: q.fromDate, toDate: q.toDate, tz };
  }
  return { from: q.from || null, to: q.to || null, fromDate: null, toDate: null, tz };
}

// Transparent KPI score (0-100):
//   hoursScore        = min(activeHours / (targetHours × expectedDays), 1) × 100
//   activityScore     = active / (active + idle) × 100
//   productivityScore = productive / active × 100
//   score = 0.5×hours + 0.2×activity + 0.3×productivity
export function kpiScore({ active_seconds, working_seconds, productive_seconds, target_hours, days }) {
  const target = target_hours >= 1 && target_hours <= 24 ? target_hours : 8;
  const targetSeconds = target * 3600 * Math.max(days, 1);
  const hoursScore = Math.min((active_seconds || 0) / targetSeconds, 1) * 100;
  const activityPct = working_seconds > 0 ? active_seconds / working_seconds : 0;
  const productivePct = active_seconds > 0 ? (productive_seconds || 0) / active_seconds : 0;
  const score = 0.5 * hoursScore + 0.2 * activityPct * 100 + 0.3 * productivePct * 100;
  const r1 = (x) => Math.round(x * 10) / 10;
  return {
    score: r1(score),
    hoursScore: r1(hoursScore),
    activityScore: r1(activityPct * 100),
    productivityScore: r1(productivePct * 100),
    activityPct: r1(activityPct * 100),
    productivePct: r1(productivePct * 100),
  };
}

function productivityBreakdown(employeeId, from, to) {
  const { where, params } = rangeClause(from, to);
  const rows = db.prepare(`
    SELECT COALESCE(ac.category, 'neutral') AS category, SUM(ai.active_seconds) AS secs
    FROM activity_intervals ai
    LEFT JOIN app_categories ac ON ac.app_name = ai.foreground_app
    WHERE ai.employee_id = ? ${where}
    GROUP BY COALESCE(ac.category, 'neutral')
  `).all(employeeId, ...params);
  const out = { productive: 0, neutral: 0, unproductive: 0 };
  for (const r of rows) if (r.category in out) out[r.category] = r.secs || 0;
  return out;
}

// Local dates (in the employee's timezone) on which there was any activity.
function activeLocalDates(employeeId, from, to, tz) {
  const { where, params } = rangeClause(from, to);
  const hours = db.prepare(`
    SELECT DISTINCT substr(started_at, 1, 13) AS h FROM activity_intervals
    WHERE employee_id = ? ${where}
  `).all(employeeId, ...params);
  const set = new Set();
  for (const { h } of hours) set.add(localDateOf(new Date(h + ':00:00Z'), tz));
  return set;
}

// Days the employee was expected to work in the range: past weekdays (local),
// plus any weekend day they actually worked. Today only counts once there is
// activity, so the score isn't penalised for a day that has barely started.
function expectedDays(range, activeDates) {
  if (!range.fromDate) return Math.max(activeDates.size, 1);
  const today = localDateOf(new Date(), range.tz);
  const last = range.toDate < today ? range.toDate : today;
  let n = 0;
  for (let d = range.fromDate; d <= last; d = addDays(d, 1)) {
    if (d === today && !activeDates.has(d)) continue;
    if (!isWeekend(d) || activeDates.has(d)) n++;
  }
  return Math.max(n, 1);
}

export function employeeSummary(employeeId, q = {}) {
  const emp = db.prepare('SELECT * FROM employees WHERE id = ?').get(employeeId);
  if (!emp) return null;
  const range = resolveRange(emp, q);
  const { where, params } = rangeClause(range.from, range.to);
  const agg = db.prepare(`
    SELECT COALESCE(SUM(active_seconds),0) AS active_seconds,
           COALESCE(SUM(idle_seconds),0)   AS idle_seconds,
           COALESCE(SUM(keystrokes),0)     AS keystrokes,
           COALESCE(SUM(mouse_moves),0)    AS mouse_moves
    FROM activity_intervals WHERE employee_id = ? ${where}
  `).get(employeeId, ...params);

  const working_seconds = agg.active_seconds + agg.idle_seconds;
  const activeDates = activeLocalDates(employeeId, range.from, range.to, range.tz);
  const days = expectedDays(range, activeDates);
  const prod = productivityBreakdown(employeeId, range.from, range.to);

  return {
    employee: {
      id: emp.id, name: emp.name, email: emp.email,
      department: emp.department, position: emp.position,
      target_hours: emp.target_hours, timezone: range.tz, active: emp.active,
    },
    range: { from: range.from, to: range.to, fromDate: range.fromDate, toDate: range.toDate },
    working_seconds,
    active_seconds: agg.active_seconds,
    idle_seconds: agg.idle_seconds,
    keystrokes: agg.keystrokes,
    mouse_moves: agg.mouse_moves,
    productivity: prod,
    days,
    active_days: activeDates.size,
    kpi: kpiScore({
      active_seconds: agg.active_seconds, working_seconds,
      productive_seconds: prod.productive, target_hours: emp.target_hours, days,
    }),
  };
}

export function allEmployeesSummary(q = {}) {
  const ids = db.prepare('SELECT id FROM employees WHERE active = 1 ORDER BY name').all();
  return ids.map(({ id }) => employeeSummary(id, q)).filter(Boolean);
}

export function employeeTimeline(employeeId, q = {}) {
  const emp = db.prepare('SELECT * FROM employees WHERE id = ?').get(employeeId);
  if (!emp) return [];
  const range = resolveRange(emp, q);
  const { where, params } = rangeClause(range.from, range.to);
  return db.prepare(`
    SELECT started_at, ended_at, active_seconds, idle_seconds, keystrokes, mouse_moves, foreground_app
    FROM activity_intervals WHERE employee_id = ? ${where}
    ORDER BY started_at LIMIT 5000
  `).all(employeeId, ...params);
}
