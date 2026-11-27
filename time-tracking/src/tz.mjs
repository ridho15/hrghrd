// Timezone helpers (IANA zones via Intl; no dependencies).
// All stored timestamps are UTC ISO strings; "local dates" are YYYY-MM-DD in an
// employee's own timezone.

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

export function isValidTimeZone(tz) {
  if (typeof tz !== 'string' || !tz) return false;
  try { new Intl.DateTimeFormat('en-US', { timeZone: tz }); return true; }
  catch { return false; }
}

export function safeTz(tz) {
  return isValidTimeZone(tz) ? tz : 'UTC';
}

// A real calendar date in YYYY-MM-DD form (rejects 2026-02-30, 2026-99-99, ...).
export function isValidDate(s) {
  if (typeof s !== 'string' || !DATE_RE.test(s)) return false;
  const [y, m, d] = s.split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d));
  return dt.getUTCFullYear() === y && dt.getUTCMonth() === m - 1 && dt.getUTCDate() === d;
}

// Minutes to add to UTC to get local time in `tz` at instant `date`.
export function tzOffsetMinutes(tz, date) {
  const p = new Intl.DateTimeFormat('en-US', {
    timeZone: safeTz(tz), hour12: false, year: 'numeric', month: '2-digit',
    day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit',
  }).formatToParts(date).reduce((a, x) => (a[x.type] = x.value, a), {});
  const asUTC = Date.UTC(p.year, p.month - 1, p.day, p.hour === '24' ? 0 : p.hour, p.minute, p.second);
  return Math.round((asUTC - date.getTime()) / 60000);
}

// UTC instant of local midnight starting `localDate` in `tz`.
export function localMidnightUtc(localDate, tz) {
  const [y, m, d] = localDate.split('-').map(Number);
  const guess = Date.UTC(y, m - 1, d);
  // Two passes handle DST transitions correctly.
  let t = guess - tzOffsetMinutes(tz, new Date(guess)) * 60000;
  t = guess - tzOffsetMinutes(tz, new Date(t)) * 60000;
  return t;
}

// [from, to] ISO bounds covering local dates fromDate..toDate (inclusive).
export function dateRangeUtc(fromDate, toDate, tz) {
  const start = localMidnightUtc(fromDate, tz);
  const end = localMidnightUtc(addDays(toDate, 1), tz) - 1;
  return { from: new Date(start).toISOString(), to: new Date(end).toISOString() };
}

// Local YYYY-MM-DD of an instant in `tz`.
export function localDateOf(date, tz) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: safeTz(tz) }).format(date);
}

export function addDays(localDate, n) {
  const [y, m, d] = localDate.split('-').map(Number);
  return new Date(Date.UTC(y, m - 1, d + n)).toISOString().slice(0, 10);
}

export function isWeekend(localDate) {
  const [y, m, d] = localDate.split('-').map(Number);
  const wd = new Date(Date.UTC(y, m - 1, d)).getUTCDay();
  return wd === 0 || wd === 6;
}

export function daysBetween(fromDate, toDate) {
  const a = Date.parse(fromDate + 'T00:00:00Z'), b = Date.parse(toDate + 'T00:00:00Z');
  return Math.round((b - a) / 86400000);
}
