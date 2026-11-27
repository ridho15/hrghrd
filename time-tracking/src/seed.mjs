// DEV ONLY: fills the DB with sample employees and activity for demos.
// It WIPES employees/activity/timesheets/alerts, so it refuses to run on a DB
// that already has employees unless you pass --force.
import {
  db, createEmployee, recordInterval, recordHeartbeat, createTimesheet, trackedSeconds,
} from './db.mjs';
import { tzOffsetMinutes, localDateOf, dateRangeUtc, addDays } from './tz.mjs';

const existing = db.prepare('SELECT COUNT(*) AS n FROM employees').get().n;
if (existing > 0 && !process.argv.includes('--force')) {
  console.error(`Refusing to seed: the database already has ${existing} employee(s).`);
  console.error('This would DELETE real data. Re-run with --force only on a dev/test database.');
  process.exit(1);
}

db.exec(`
  DELETE FROM timesheets; DELETE FROM agent_alerts; DELETE FROM activity_intervals;
  DELETE FROM agent_status; DELETE FROM employees;
  DELETE FROM sqlite_sequence WHERE name IN ('employees','activity_intervals','timesheets','agent_alerts');
`);

const people = [
  { name: 'Ayu Lestari',  email: 'ayu@hrg.co.id',   department: 'Finance',     position: 'Staff Akuntansi',   timezone: 'Asia/Jakarta' },
  { name: 'Budi Santoso', email: 'budi@hrg.co.id',  department: 'Engineering', position: 'Software Engineer', timezone: 'Asia/Jakarta' },
  { name: 'Citra Dewi',   email: 'citra@hrg.co.id', department: 'Engineering', position: 'QA Engineer',       timezone: 'Asia/Makassar' },
  { name: 'Dian Pratama', email: 'dian@hrg.co.id',  department: 'Sales',       position: 'Sales Executive',   timezone: 'Asia/Jakarta' },
  { name: 'Eka Wijaya',   email: 'eka@hrg.co.id',   department: 'Support',     position: 'Customer Support',  timezone: 'Asia/Jayapura' },
];
const apps = ['Code', 'chrome', 'EXCEL', 'slack', 'OUTLOOK', 'Figma'];
const pick = (a) => a[Math.floor(Math.random() * a.length)];

const now = new Date();
const created = [];
for (const p of people) {
  const emp = createEmployee({ ...p, target_hours: 8 });
  created.push(emp);
  const host = `WIN-${p.name.split(' ')[0].toUpperCase()}`;
  recordHeartbeat(emp.id, { hostname: host, agent_version: '0.2.0' });
  const today = localDateOf(now, p.timezone);

  // Last 5 local days, 09:00–17:00 local, 10-minute intervals (never in the future).
  for (let d = 4; d >= 0; d--) {
    const day = addDays(today, -d);
    const diligence = 0.55 + Math.random() * 0.4;
    const [y, m, dd] = day.split('-').map(Number);
    for (let h = 9; h < 17; h++) {
      for (let min = 0; min < 60; min += 10) {
        const wall = Date.UTC(y, m - 1, dd, h, min);
        const start = new Date(wall - tzOffsetMinutes(p.timezone, new Date(wall)) * 60000);
        const end = new Date(start.getTime() + 600000);
        if (end > now) continue;
        const frac = Math.min(1, Math.max(0, diligence + (Math.random() - 0.5) * 0.3));
        const active = Math.round(600 * frac);
        recordInterval(emp.id, {
          hostname: host, started_at: start.toISOString(), ended_at: end.toISOString(),
          active_seconds: active, idle_seconds: 600 - active,
          keystrokes: Math.round(active / 10 * (0.5 + Math.random())),
          mouse_moves: Math.round(active * (3 + Math.random() * 4)),
          foreground_app: pick(apps),
        });
      }
    }
  }
  console.log(`seeded ${emp.name} (id=${emp.id}, tz=${p.timezone})`);
}

// Three submitted timesheets for the approval demo (local-date periods).
for (const [idx, note] of [[1, 'Minggu ini fokus fitur login.'], [2, 'Banyak testing regresi.'], [3, 'Kunjungan klien 2 hari.']]) {
  const emp = created[idx];
  const to = localDateOf(now, emp.timezone), from = addDays(to, -6);
  const r = dateRangeUtc(from, to, emp.timezone);
  createTimesheet(emp.id, { period_start: from, period_end: to, note, tracked_seconds: trackedSeconds(emp.id, r.from, r.to) });
}
console.log('Seeded 3 submitted timesheets.');

// Two agents look "offline" (stale heartbeat) to demo alerts.
const stale = db.prepare(`UPDATE agent_status SET last_seen = datetime('now', ?) WHERE employee_id = ?`);
stale.run('-42 minutes', created[3].id);
stale.run('-3 hours', created[4].id);
console.log('Marked 2 agents as stale (offline demo).\nSeed complete.');
