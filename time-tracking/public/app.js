// Time Tracking dashboard (admin / manager). Design decisions: DESIGN.md.
// All dynamic text is escaped with esc() before reaching innerHTML; no inline
// handlers (strict CSP).
const $ = (s, root = document) => root.querySelector(s);
const $$ = (s, root = document) => [...root.querySelectorAll(s)];
let TOKEN = sessionStorage.getItem('hrg_token') || null;
let ROLE = 'manager';
let USERNAME = '';
let EMPLOYEES = [];
let TAB = 'tracker';
let PENDING = 0;
const STATE = { empId: null, day: null, summaryRange: null };

// ---------- formatting ----------
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const nf = (n) => new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
const pct = (x) => `${Math.round(x || 0)}%`;
const hm = (sec) => { sec = Math.round(sec || 0); return `${Math.floor(sec / 3600)}:${String(Math.floor(sec % 3600 / 60)).padStart(2, '0')}`; };
const mins = (sec) => `${Math.round((sec || 0) / 60)} mnt`;
const durLabel = (m) => m >= 60 ? `${Math.floor(m / 60)} jam${m % 60 ? ` ${m % 60} mnt` : ''}` : `${m} mnt`;
const initials = (n) => String(n || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
const isLow = (score) => score < 50;

// Readable names for common Windows process names (the agent reports process names).
const APP_NAMES = { code: 'VS Code', excel: 'Excel', winword: 'Word', powerpnt: 'PowerPoint', outlook: 'Outlook',
  onenote: 'OneNote', msedge: 'Edge', chrome: 'Chrome', firefox: 'Firefox', 'ms-teams': 'Teams', teams: 'Teams',
  slack: 'Slack', figma: 'Figma', explorer: 'File Explorer', acrord32: 'Acrobat Reader', devenv: 'Visual Studio',
  whatsapp: 'WhatsApp', zoom: 'Zoom', notepad: 'Notepad', 'notepad++': 'Notepad++', windowsterminal: 'Terminal' };
function appName(p) {
  if (!p) return 'Tidak tercatat';
  const known = APP_NAMES[p.toLowerCase()];
  if (known) return known;
  return p === p.toLowerCase() || p === p.toUpperCase() ? p.charAt(0).toUpperCase() + p.slice(1).toLowerCase() : p;
}
const periodLabel = (a, b) => {
  const f = (d, withYear) => dayLabel(d, withYear ? { day: 'numeric', month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' });
  return `${f(a, a.slice(0, 4) !== b.slice(0, 4))} sampai ${f(b, true)}`;
};

function safeTz(tz) { try { new Intl.DateTimeFormat('en', { timeZone: tz }); return tz; } catch { return 'UTC'; } }
const TZ_ABBR = { 'Asia/Jakarta': 'WIB', 'Asia/Makassar': 'WITA', 'Asia/Jayapura': 'WIT' };
const tzLabel = (tz) => TZ_ABBR[tz] ? `${TZ_ABBR[tz]} (${tz})` : tz;
const tzOf = (empId) => safeTz(EMPLOYEES.find(e => e.id === empId)?.timezone || 'UTC');
const hmTz = (iso, tz) => new Intl.DateTimeFormat('en-GB', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(iso));
const hourTz = (iso, tz) => Number(new Intl.DateTimeFormat('en-GB', { timeZone: tz, hour: '2-digit', hour12: false }).format(new Date(iso))) % 24;
const todayLocal = () => new Intl.DateTimeFormat('en-CA').format(new Date());
const addDays = (d, n) => { const [y, m, dd] = d.split('-').map(Number); return new Date(Date.UTC(y, m - 1, dd + n)).toISOString().slice(0, 10); };
const dayLabel = (d, opts = { weekday: 'long', day: 'numeric', month: 'short' }) =>
  new Date(d + 'T00:00:00Z').toLocaleDateString('id-ID', { ...opts, timeZone: 'UTC' });
const fmtWhen = (iso) => iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '';

const AGENT_STATUS = {
  online: ['Online', 'status-ok'], paused: ['Dijeda', 'status-warn'],
  offline: ['Offline', 'status-bad'], none: ['Agent belum terpasang', 'status-none'],
};
const TS_STATUS = { submitted: ['Menunggu', 'status-warn'], approved: ['Disetujui', 'status-ok'], rejected: ['Ditolak', 'status-bad'] };
const statusLabel = ([text, cls]) => `<span class="status ${cls}">${esc(text)}</span>`;

// ---------- API ----------
const NET_ERR = 'Tidak bisa terhubung ke server. Periksa koneksi, lalu coba lagi.';
async function api(path, opts = {}) {
  const res = await fetch(path, {
    ...opts,
    headers: { 'content-type': 'application/json', ...(TOKEN ? { authorization: `Bearer ${TOKEN}` } : {}), ...(opts.headers || {}) },
  }).catch(() => { throw new Error(NET_ERR); });
  if (res.status === 401 && !path.startsWith('/api/login')) { logout(true); throw new Error('Sesi berakhir. Silakan masuk lagi.'); }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Permintaan gagal (${res.status})`);
  return data;
}
async function apiRaw(path) {
  const res = await fetch(path, { headers: { authorization: `Bearer ${TOKEN}` } }).catch(() => { throw new Error(NET_ERR); });
  if (res.status === 401) { logout(true); throw new Error('Sesi berakhir. Silakan masuk lagi.'); }
  if (!res.ok) throw new Error((await res.json().catch(() => ({}))).error || `Permintaan gagal (${res.status})`);
  return res;
}

// ---------- navigation ----------
const ICON = {
  tracker: '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9.5 2h5"/>',
  summary: '<path d="M5 20V12M12 20V4M19 20v-6"/>',
  activity: '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
  approval: '<path d="M9 12l2 2 4-4"/><rect x="4" y="4" width="16" height="16" rx="3"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
};
const SCREENS = {
  tracker:  { nav: 'Tracker',    title: 'Tracker harian' },
  summary:  { nav: 'Ringkasan',  title: 'Ringkasan KPI' },
  activity: { nav: 'Rincian',    title: 'Rincian aktivitas' },
  approval: { nav: 'Approval',   title: 'Approval timesheet' },
  settings: { nav: 'Pengaturan', title: 'Pengaturan' },
};

function renderNav() {
  const html = `<ul>${Object.entries(SCREENS).map(([key, s]) => `
    <li><button type="button" class="tab" data-tab="${key}" ${key === TAB ? 'aria-current="page"' : ''}>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICON[key]}</svg>
      <span>${s.nav}</span>
      ${key === 'approval' && PENDING > 0 ? `<span class="tab-count" aria-label="${PENDING} menunggu">${PENDING}</span>` : ''}
    </button></li>`).join('')}</ul>`;
  $$('[data-nav]').forEach(nav => {
    nav.innerHTML = html;
    $$('.tab', nav).forEach(btn => btn.addEventListener('click', () => go(btn.dataset.tab)));
  });
}

function go(tab) {
  TAB = tab;
  renderNav();
  render();
  window.scrollTo({ top: 0 });
}

function showApp(show) {
  $('#app').classList.toggle('hidden', !show);
  $('#login').classList.toggle('hidden', show);
  $('#session').classList.toggle('hidden', !show);
  $$('[data-nav]').forEach(n => n.classList.toggle('hidden', !show));
  if (!show) {
    $('#page-title').textContent = 'Masuk';
    $('#page-sub').textContent = '';
    $('#page-actions').innerHTML = '';
    $('#notices').innerHTML = '';
  }
}

function logout(localOnly = false) {
  if (TOKEN && !localOnly) fetch('/api/logout', { method: 'POST', headers: { authorization: `Bearer ${TOKEN}` } }).catch(() => {});
  TOKEN = null;
  sessionStorage.removeItem('hrg_token');
  sessionStorage.removeItem('hrg_default_pw');
  showApp(false);
  $('#username').focus();
}
$('#logout-btn').addEventListener('click', () => logout());

// ---------- login ----------
$('#login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const err = $('#login-error');
  err.textContent = '';
  const btn = $('button[type=submit]', e.currentTarget);
  btn.disabled = true;
  try {
    const { token, default_password } = await api('/api/login', {
      method: 'POST',
      body: JSON.stringify({ username: $('#username').value.trim() || undefined, password: $('#password').value }),
    });
    TOKEN = token;
    sessionStorage.setItem('hrg_token', token);
    if (default_password) sessionStorage.setItem('hrg_default_pw', '1'); else sessionStorage.removeItem('hrg_default_pw');
    $('#password').value = '';
    showApp(true);
    await boot();
  } catch (ex) { err.textContent = ex.message; }
  finally { btn.disabled = false; }
});

// ---------- boot ----------
async function boot() {
  const [me, { employees }] = await Promise.all([api('/api/admin/me'), api('/api/admin/employees')]);
  ROLE = me.role;
  USERNAME = me.username;
  const roleName = ROLE === 'admin' ? 'Admin' : 'Manajer';
  $('#session-user').textContent = USERNAME.toLowerCase() === roleName.toLowerCase() ? roleName : `${USERNAME} (${roleName})`;
  EMPLOYEES = employees.filter(e => e.active);
  fillEmployeeSelect();
  STATE.day = STATE.day || todayLocal();
  $('#day').value = STATE.day;
  $('#day').max = todayLocal();
  $('#emp-select').onchange = () => { STATE.empId = Number($('#emp-select').value) || null; render(); };
  $('#day').onchange = () => { STATE.day = $('#day').value || todayLocal(); render(); };
  renderNav();
  render();
  refreshNotices();
  refreshPending();
  if (!window.__pollTimer) window.__pollTimer = setInterval(() => { if (TOKEN) { refreshNotices(); refreshPending(); } }, 60000);
}

function fillEmployeeSelect() {
  const sel = $('#emp-select');
  if (!EMPLOYEES.some(e => e.id === STATE.empId)) STATE.empId = EMPLOYEES[0]?.id ?? null;
  sel.innerHTML = EMPLOYEES.length
    ? EMPLOYEES.map(e => `<option value="${e.id}">${esc(e.name)}${e.department ? ` (${esc(e.department)})` : ''}</option>`).join('')
    : '<option value="">Belum ada karyawan</option>';
  sel.disabled = !EMPLOYEES.length;
  sel.value = STATE.empId ?? '';
}

// Notices are contextual: offline agents on the monitoring screens, the
// default-password warning on Pengaturan (the only place it can be acted on).
async function refreshNotices() {
  const tab = TAB;
  const parts = [];
  if (tab === 'settings' && sessionStorage.getItem('hrg_default_pw'))
    parts.push(`<div class="notice notice-warn" role="status"><p><b>Password admin masih bawaan.</b>
      Ganti lewat ADMIN_PASSWORD_HASH di server sebelum dashboard dipakai sungguhan.</p></div>`);
  if (tab === 'tracker' || tab === 'summary') {
    try {
      const { offline, threshold_min } = await api('/api/admin/alerts');
      if (offline.length) {
        const names = offline.slice(0, 3).map(o => `${esc(o.name)} (${durLabel(o.offline_minutes)})`).join(', ');
        const more = offline.length > 3 ? `, dan ${offline.length - 3} lainnya` : '';
        parts.unshift(`<div class="notice notice-danger" role="status"><p><b>${offline.length} agent offline</b>
          lebih dari ${Number(threshold_min)} menit: ${names}${more}.</p></div>`);
      }
    } catch { /* the screen itself reports connection errors */ }
  }
  if (tab === TAB) $('#notices').innerHTML = parts.join('');
}

async function refreshPending() {
  try {
    const { pending } = await api('/api/admin/timesheets?status=submitted');
    if (pending !== PENDING) { PENDING = pending; renderNav(); }
  } catch {}
}

// ---------- render dispatcher ----------
let renderSeq = 0;
const afterRender = {};
async function render() {
  const view = $('#view');
  const seq = ++renderSeq;
  const screen = SCREENS[TAB];
  $('#page-title').textContent = screen.title;
  $('#page-sub').textContent = '';
  $('#page-actions').innerHTML = '';
  $('#filters').classList.toggle('hidden', !(TAB === 'tracker' || TAB === 'activity'));
  $('#notices').innerHTML = '';
  refreshNotices();
  view.setAttribute('aria-busy', 'true');
  view.innerHTML = `<p class="state" role="status">Memuat ${esc(screen.title.charAt(0).toLowerCase() + screen.title.slice(1))}…</p>`;
  try {
    const html = await { tracker: renderTracker, summary: renderSummary, activity: renderActivity,
                         approval: renderApproval, settings: renderSettings }[TAB]();
    if (seq !== renderSeq) return;
    view.innerHTML = html;
    afterRender[TAB]?.(view);
  } catch (err) {
    if (seq !== renderSeq) return;
    view.innerHTML = `<div class="state" role="alert"><b>Data gagal dimuat.</b>${esc(err.message)}
      <br><button type="button" class="btn btn-secondary" id="retry">Coba lagi</button></div>`;
    $('#retry').addEventListener('click', render);
  } finally {
    if (seq === renderSeq) view.removeAttribute('aria-busy');
  }
}

const noEmployees = () => `<div class="state"><b>Belum ada karyawan aktif.</b>${ROLE === 'admin'
  ? 'Tambahkan karyawan di Pengaturan, lalu pasang agent di komputernya.'
  : 'Minta admin menambahkan karyawan di Pengaturan.'}</div>`;

// ---------- Tracker ----------
async function renderTracker() {
  if (!STATE.empId) return noEmployees();
  const tz = tzOf(STATE.empId);
  const { summary: s, timeline } = await api(`/api/admin/employees/${STATE.empId}/timeline?fromDate=${STATE.day}&toDate=${STATE.day}`);
  const targetH = s.employee.target_hours || 8;
  const toTarget = Math.min(100, s.working_seconds / (targetH * 3600) * 100);
  $('#page-sub').textContent = `${s.employee.name}, ${dayLabel(STATE.day)}`;
  return `
    <div class="tracker-grid">
      <div>
        <section class="panel focus" aria-labelledby="focus-label">
          <h2 class="focus-label" id="focus-label">Waktu kerja, ${esc(dayLabel(STATE.day))}</h2>
          <p class="focus-value num">${hm(s.working_seconds)}<small>jam</small></p>
          <div class="rule rule-focal" role="img" aria-label="${pct(toTarget)} dari target ${targetH} jam"><span style="width:${toTarget}%"></span></div>
          <p class="focus-meta"><span class="num">${pct(toTarget)} dari target ${targetH} jam</span><span>${esc(tzLabel(tz))}</span></p>
          <dl class="metrics">
            <div class="metric"><dt>Waktu aktif</dt><dd class="num">${pct(s.kpi.activityPct)}</dd><dd class="metric-note">sisanya idle</dd></div>
            <div class="metric"><dt>Skor KPI</dt><dd class="num ${isLow(s.kpi.score) ? 'is-low' : ''}">${Math.round(s.kpi.score)}</dd><dd class="metric-note">dari 100</dd></div>
            <div class="metric"><dt>Ketikan</dt><dd class="num">${nf(s.keystrokes)}</dd><dd class="metric-note">jumlah, bukan isi</dd></div>
            <div class="metric"><dt>Gerakan mouse</dt><dd class="num">${nf(s.mouse_moves)}</dd><dd class="metric-note">jumlah</dd></div>
          </dl>
        </section>
        ${productivityPanel(s)}
      </div>
      <section class="panel" aria-labelledby="ivl-title">
        <div class="panel-head"><h2 id="ivl-title">Aktivitas per 10 menit</h2><p>Garis menunjukkan porsi waktu aktif</p></div>
        ${intervalList(timeline, tz, s.employee.name)}
      </section>
    </div>`;
}

function productivityPanel(s) {
  const p = s.productivity || { productive: 0, neutral: 0, unproductive: 0 };
  const total = p.productive + p.neutral + p.unproductive;
  const w = (v) => total ? (v / total * 100).toFixed(1) : 0;
  const bar = total
    ? `<div class="split" role="img" aria-label="Produktif ${w(p.productive)}%, netral ${w(p.neutral)}%, non-produktif ${w(p.unproductive)}%">
        ${p.productive ? `<span class="s-prod" style="width:${w(p.productive)}%"></span>` : ''}
        ${p.neutral ? `<span class="s-neu" style="width:${w(p.neutral)}%"></span>` : ''}
        ${p.unproductive ? `<span class="s-unp" style="width:${w(p.unproductive)}%"></span>` : ''}
      </div>`
    : '<div class="split" aria-hidden="true"><span class="split-empty"></span></div>';
  return `<section class="panel" aria-labelledby="prod-title">
    <div class="panel-head"><h2 id="prod-title">Pemakaian aplikasi</h2><p class="num">${total ? `${pct(s.kpi.productivePct)} produktif` : 'Belum ada data'}</p></div>
    ${bar}
    <ul class="legend">
      <li><i style="background:var(--brand-bar)"></i>Produktif<span class="v num">${mins(p.productive)}</span></li>
      <li><i style="background:var(--neutral-bar)"></i>Netral<span class="v num">${mins(p.neutral)}</span></li>
      <li><i style="background:var(--danger-bar)"></i>Non-produktif<span class="v num">${mins(p.unproductive)}</span></li>
    </ul>
  </section>`;
}

function intervalList(timeline, tz, name) {
  if (!timeline.length)
    return `<div class="state"><b>Belum ada aktivitas pada tanggal ini.</b>
      Agent mengirim data tiap 10 menit selama komputer ${esc(name)} menyala dan pelacakan tidak dijeda.</div>`;
  const byHour = new Map();
  for (const iv of timeline) {
    const h = hourTz(iv.started_at, tz);
    if (!byHour.has(h)) byHour.set(h, []);
    byHour.get(h).push(iv);
  }
  const pad = (n) => String(n).padStart(2, '0');
  return [...byHour.entries()].sort((a, b) => a[0] - b[0]).map(([h, ivs]) => {
    const active = ivs.reduce((a, i) => a + i.active_seconds, 0);
    return `<div class="hour"><h3><span class="num">${pad(h)}.00–${pad((h + 1) % 24)}.00</span><span class="num">${mins(active)} aktif</span></h3>
      <ol>${ivs.map(iv => {
        const w = iv.active_seconds + iv.idle_seconds;
        const share = w ? iv.active_seconds / w * 100 : 0;
        return `<li class="ivl">
          <span class="ivl-time num">${hmTz(iv.started_at, tz)}–${hmTz(iv.ended_at, tz)}</span>
          <span class="ivl-app">${esc(appName(iv.foreground_app))}
            <span class="rule" role="img" aria-label="${pct(share)} aktif"><span style="width:${share}%"></span></span></span>
          <span class="ivl-min num">${mins(iv.active_seconds)}</span>
        </li>`;
      }).join('')}</ol></div>`;
  }).join('');
}

// ---------- Summary ----------
async function renderSummary() {
  const toDate = STATE.day || todayLocal();
  const fromDate = addDays(toDate, -6);
  STATE.summaryRange = { fromDate, toDate };
  const { employees } = await api(`/api/admin/kpi?fromDate=${fromDate}&toDate=${toDate}`);
  const short = { day: 'numeric', month: 'short' };
  $('#page-sub').textContent = `${dayLabel(fromDate, short)} sampai ${dayLabel(toDate, short)}, skor terendah di atas`;
  $('#page-actions').innerHTML = `<button type="button" class="btn btn-secondary" id="csv-dl">Unduh CSV</button>
    <button type="button" class="btn btn-secondary" id="pdf-dl">Cetak PDF</button>`;
  if (!employees.length) return noEmployees();
  const rows = [...employees].sort((a, b) => a.kpi.score - b.kpi.score).map(e => `
    <li>
      <div class="row">
        <span class="avatar" aria-hidden="true">${esc(initials(e.employee.name))}</span>
        <div class="row-main">
          <p class="row-title">${esc(e.employee.name)}</p>
          <p class="row-sub">${esc(e.employee.position || e.employee.department || '')}</p>
        </div>
        <div class="row-side">
          <p class="score num ${isLow(e.kpi.score) ? 'is-low' : ''}">${Math.round(e.kpi.score)}</p>
          <p class="score-label">skor KPI</p>
        </div>
      </div>
      <div class="row-meta">
        ${statusLabel(AGENT_STATUS[e.agent?.state] || AGENT_STATUS.none)}
        <span>Waktu kerja <b class="num">${hm(e.working_seconds)} jam</b></span>
        <span>Aktif <b class="num">${pct(e.kpi.activityPct)}</b></span>
        <span>Produktif <b class="num">${pct(e.kpi.productivePct)}</b></span>
        <span>Hari kerja <b class="num">${e.days}</b></span>
      </div>
    </li>`).join('');
  return `<ul class="list">${rows}</ul>`;
}
afterRender.summary = () => {
  const { fromDate, toDate } = STATE.summaryRange;
  const qs = `fromDate=${fromDate}&toDate=${toDate}`;
  $('#csv-dl').addEventListener('click', async (ev) => {
    const btn = ev.currentTarget; btn.disabled = true;
    try {
      const blob = await (await apiRaw(`/api/admin/export/kpi.csv?${qs}`)).blob();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `kpi_${fromDate}_${toDate}.csv`;
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    } catch (e) { alert('Unduh CSV gagal: ' + e.message); }
    finally { btn.disabled = false; }
  });
  $('#pdf-dl').addEventListener('click', async () => {
    const w = window.open('', '_blank'); // opened synchronously so popup blockers allow it
    if (!w) { alert('Popup diblokir. Izinkan popup untuk mencetak laporan.'); return; }
    try {
      const html = await (await apiRaw(`/api/admin/report?${qs}`)).text();
      w.document.open(); w.document.write(html); w.document.close();
      setTimeout(() => { w.focus(); w.print(); }, 300);
    } catch (e) { w.close(); alert('Laporan gagal dibuat: ' + e.message); }
  });
};

// ---------- Activity detail ----------
async function renderActivity() {
  if (!STATE.empId) return noEmployees();
  const tz = tzOf(STATE.empId);
  const emp = EMPLOYEES.find(e => e.id === STATE.empId);
  const { timeline } = await api(`/api/admin/employees/${STATE.empId}/timeline?fromDate=${STATE.day}&toDate=${STATE.day}`);
  $('#page-sub').textContent = `${emp?.name || ''}, ${dayLabel(STATE.day)}, ${tzLabel(tz)}`;
  if (!timeline.length)
    return `<div class="state"><b>Tidak ada interval tercatat pada tanggal ini.</b>Pilih tanggal lain, atau pastikan agent di komputer ${esc(emp?.name || 'karyawan')} berjalan.</div>`;
  return `<div class="table-wrap"><table>
    <caption class="sr-only">Interval 10 menit</caption>
    <thead><tr><th scope="col">Waktu</th><th scope="col">Aplikasi</th><th scope="col" class="r">Aktif</th>
      <th scope="col" class="r">Idle</th><th scope="col" class="r">Ketikan</th><th scope="col" class="r">Gerakan mouse</th></tr></thead>
    <tbody>${timeline.map(iv => `<tr>
      <td class="num">${hmTz(iv.started_at, tz)}–${hmTz(iv.ended_at, tz)}</td>
      <td>${esc(appName(iv.foreground_app))}</td>
      <td class="r num">${mins(iv.active_seconds)}</td>
      <td class="r num">${mins(iv.idle_seconds)}</td>
      <td class="r num">${nf(iv.keystrokes)}</td>
      <td class="r num">${nf(iv.mouse_moves)}</td></tr>`).join('')}</tbody>
  </table></div>`;
}

// ---------- Approval ----------
async function renderApproval() {
  const { timesheets, pending } = await api('/api/admin/timesheets');
  if (pending !== PENDING) { PENDING = pending; renderNav(); }
  $('#page-sub').textContent = pending ? `${pending} menunggu keputusan` : 'Tidak ada yang menunggu';
  if (!timesheets.length)
    return `<div class="state"><b>Belum ada timesheet.</b>Karyawan mengirim timesheet dari halaman Data Saya (/me.html) dengan token mereka.</div>`;
  return `<ul class="list">${timesheets.map(t => `
    <li>
      <div class="row">
        <div class="row-main">
          <p class="row-title">${esc(t.name)}</p>
          <p class="row-sub">${esc(t.department || '')}</p>
        </div>
        ${statusLabel(TS_STATUS[t.status] || [t.status, 'status-none'])}
      </div>
      <div class="row-meta">
        <span>Periode <b class="num">${esc(periodLabel(t.period_start, t.period_end))}</b></span>
        <span>Tercatat <b class="num">${hm(t.tracked_seconds)} jam</b></span>
      </div>
      ${t.note ? `<p class="note"><span class="sr-only">Catatan karyawan: </span>${esc(t.note)}</p>` : ''}
      ${t.reviewer_note ? `<p class="note">Catatan reviewer: ${esc(t.reviewer_note)}</p>` : ''}
      ${t.status === 'submitted' ? `
        <div class="form-grid" style="margin-top:12px">
          <label class="field"><span>Catatan reviewer <span class="help">(opsional)</span></span>
            <input type="text" class="ts-rnote" data-id="${t.id}" maxlength="500"></label>
        </div>
        <div class="row-actions">
          <button type="button" class="btn btn-primary ts-ok" data-id="${t.id}">Setujui</button>
          <button type="button" class="btn btn-danger ts-no" data-id="${t.id}">Tolak</button>
        </div>` : `<p class="row-sub" style="margin-top:8px">Diputuskan ${esc(fmtWhen(t.reviewed_at))}</p>`}
    </li>`).join('')}</ul>`;
}
afterRender.approval = (view) => {
  const review = async (id, decision, btn) => {
    const note = $(`.ts-rnote[data-id="${id}"]`, view)?.value || '';
    $$(`button[data-id="${id}"]`, view).forEach(b => { b.disabled = true; });
    try {
      await api(`/api/admin/timesheets/${id}/review`, { method: 'POST', body: JSON.stringify({ decision, note }) });
      await render();
    } catch (e) { alert(e.message); $$(`button[data-id="${id}"]`, view).forEach(b => { b.disabled = false; }); btn.focus(); }
  };
  $$('.ts-ok', view).forEach(b => b.addEventListener('click', () => review(b.dataset.id, 'approved', b)));
  $$('.ts-no', view).forEach(b => b.addEventListener('click', () => review(b.dataset.id, 'rejected', b)));
};

// ---------- Settings ----------
const CAT_LABEL = { productive: 'Produktif', neutral: 'Netral', unproductive: 'Non-produktif' };
async function renderSettings() {
  const isAdmin = ROLE === 'admin';
  const [{ observed }, alerts, usersResp, empResp] = await Promise.all([
    api('/api/admin/categories'),
    api('/api/admin/alerts'),
    isAdmin ? api('/api/admin/users') : Promise.resolve({ users: [] }),
    isAdmin ? api('/api/admin/employees') : Promise.resolve({ employees: [] }),
  ]);
  $('#page-sub').textContent = isAdmin ? 'Masuk sebagai admin' : 'Masuk sebagai manajer: hanya bisa melihat';
  const dis = isAdmin ? '' : 'disabled';
  const catOptions = (sel) => Object.entries(CAT_LABEL)
    .map(([v, l]) => `<option value="${v}" ${v === sel ? 'selected' : ''}>${l}</option>`).join('');

  const appRows = observed.length ? `<ul class="list">${observed.map(a => `
    <li><label class="row">
      <span class="row-main"><span class="row-title">${esc(appName(a.app_name))}</span><span class="row-sub num">Proses ${esc(a.app_name)}, ${mins(a.active_seconds)} aktif</span></span>
      <select class="cat-select" data-app="${esc(a.app_name)}" style="width:150px" ${dis}>${catOptions(a.category in CAT_LABEL ? a.category : 'neutral')}</select>
    </label></li>`).join('')}</ul>`
    : `<div class="state"><b>Belum ada aplikasi terdeteksi.</b>Daftar ini terisi setelah agent mengirim data pertama.</div>`;

  return `
    <div class="notice notice-info"><p>Agent selalu terlihat di tray, meminta persetujuan saat pertama jalan, dan hanya mencatat jumlah ketikan, gerakan mouse, serta nama aplikasi. Isi ketikan dan screenshot tidak pernah direkam.</p></div>
    ${isAdmin ? employeesSection(empResp.employees) : ''}
    ${isAdmin ? usersSection(usersResp.users) : ''}

    <section class="section" aria-labelledby="cat-title">
      <h2 class="section-title" id="cat-title">Kategori aplikasi</h2>
      <p class="section-desc">Menentukan porsi produktif di skor KPI.${isAdmin ? '' : ' Hanya admin yang bisa mengubah.'}</p>
      ${appRows}
    </section>

    <section class="section" aria-labelledby="alert-title">
      <h2 class="section-title" id="alert-title">Alert agent offline</h2>
      <p class="section-desc">Agent dianggap offline bila tidak melapor melebihi batas ini.${isAdmin ? '' : ' Hanya admin yang bisa mengubah.'}</p>
      <form class="panel" id="alert-form">
        <div class="form-grid cols-2">
          <label class="field"><span>Batas offline (menit)</span>
            <input type="number" id="thr" min="1" max="1440" required value="${Number(alerts.threshold_min)}" ${dis}></label>
          <label class="field"><span>Webhook Slack/Teams <span class="help">(${alerts.webhook_configured ? 'aktif, isi untuk mengganti' : 'opsional'})</span></span>
            <input type="url" id="wh" placeholder="https://hooks.slack.com/services/..." ${dis}></label>
        </div>
        ${isAdmin ? `<div class="form-actions">
          <button type="submit" class="btn btn-primary">Simpan</button>
          ${alerts.webhook_configured ? '<button type="button" class="btn btn-danger" id="wh-clear">Hapus webhook</button>' : ''}
        </div>` : ''}
        <p class="form-msg" id="alert-msg" role="status"></p>
      </form>
    </section>

    <section class="section" aria-labelledby="hist-title">
      <h2 class="section-title" id="hist-title">Riwayat alert</h2>
      <p class="section-desc">Perubahan status agent, terbaru di atas.</p>
      ${alertFeed(alerts.recent)}
    </section>

    <section class="section" aria-labelledby="kpi-title">
      <h2 class="section-title" id="kpi-title">Cara skor KPI dihitung</h2>
      <div class="panel"><p class="num">Skor = 50% jam aktif terhadap target, 20% porsi waktu aktif, 30% porsi aplikasi produktif.</p>
        <p class="section-desc" style="margin:6px 0 0">Target dihitung dari hari kerja Senin sampai Jumat di zona waktu karyawan, ditambah hari libur yang ada aktivitasnya. Hari ini baru dihitung setelah ada aktivitas.</p></div>
    </section>`;
}
afterRender.settings = (view) => {
  $$('.cat-select', view).forEach(sel => sel.addEventListener('change', async () => {
    sel.disabled = true;
    try {
      await api('/api/admin/categories', { method: 'POST', body: JSON.stringify({ app_name: sel.dataset.app, category: sel.value }) });
    } catch (e) { alert(e.message); }
    finally { sel.disabled = false; sel.focus(); }
  }));
  const msg = $('#alert-msg', view);
  const saveSettings = async (payload, okText) => {
    try {
      await api('/api/admin/settings', { method: 'POST', body: JSON.stringify(payload) });
      refreshNotices();
      await render();
      const m = $('#alert-msg'); if (m) { m.textContent = okText; m.className = 'form-msg is-ok'; }
    } catch (e) { msg.textContent = e.message; msg.className = 'form-msg is-error'; }
  };
  $('#alert-form', view).addEventListener('submit', (e) => {
    e.preventDefault();
    if (ROLE !== 'admin') return;
    const payload = { offline_threshold_min: Number($('#thr', view).value) };
    const wh = $('#wh', view).value.trim();
    if (wh) payload.alert_webhook_url = wh;
    saveSettings(payload, 'Pengaturan alert tersimpan.');
  });
  $('#wh-clear', view)?.addEventListener('click', () => {
    if (confirm('Hapus webhook notifikasi?')) saveSettings({ alert_webhook_url: '' }, 'Webhook dihapus.');
  });
  if (ROLE === 'admin') { wireEmployees(view); wireUsers(view); }
};

// ---- Employees (admin) ----
const TZ_OPTIONS = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'];
function employeesSection(employees) {
  const rows = employees.length ? `<ul class="list">${employees.map(e => `
    <li>
      <div class="row">
        <div class="row-main">
          <p class="row-title">${esc(e.name)}</p>
          <p class="row-sub">${esc([e.department, e.position, tzLabel(e.timezone)].filter(Boolean).join(' · '))}</p>
        </div>
        ${e.active ? '' : statusLabel(['Nonaktif', 'status-none'])}
      </div>
      <div class="row-actions">
        ${e.active ? `<button type="button" class="btn btn-secondary em-copy" data-token="${esc(e.agent_token)}" aria-label="Salin token ${esc(e.name)}">Salin token</button>
        <button type="button" class="btn btn-secondary em-regen" data-id="${e.id}" aria-label="Buat token baru untuk ${esc(e.name)}">Token baru</button>` : ''}
        <button type="button" class="btn ${e.active ? 'btn-danger' : 'btn-secondary'} em-toggle" data-id="${e.id}" data-active="${e.active ? 1 : 0}"
          aria-label="${e.active ? 'Nonaktifkan' : 'Aktifkan'} ${esc(e.name)}">${e.active ? 'Nonaktifkan' : 'Aktifkan'}</button>
      </div>
    </li>`).join('')}</ul>`
    : `<div class="state"><b>Belum ada karyawan.</b>Isi formulir di bawah untuk menambahkan karyawan pertama.</div>`;
  return `
    <section class="section" aria-labelledby="emp-title">
      <h2 class="section-title" id="emp-title">Kelola karyawan</h2>
      <p class="section-desc">Setiap karyawan punya token sendiri. Salin token, lalu tempel saat memasang agent di komputernya.</p>
      ${rows}
      <form class="panel" id="emp-form" style="margin-top:12px" aria-labelledby="emp-form-title">
        <h3 class="section-title" id="emp-form-title">Tambah karyawan</h3>
        <div class="form-grid cols-2" style="margin-top:12px">
          <label class="field"><span>Nama lengkap</span><input type="text" id="e-name" maxlength="100" required></label>
          <label class="field"><span>Departemen <span class="help">(opsional)</span></span><input type="text" id="e-dept" maxlength="100"></label>
          <label class="field"><span>Jabatan <span class="help">(opsional)</span></span><input type="text" id="e-pos" maxlength="100"></label>
          <label class="field"><span>Email <span class="help">(opsional)</span></span><input type="email" id="e-email" maxlength="120" placeholder="nama@perusahaan.co.id"></label>
          <label class="field"><span>Zona waktu</span><select id="e-tz">${TZ_OPTIONS.map(t => `<option value="${t}">${esc(tzLabel(t))}</option>`).join('')}</select></label>
          <label class="field"><span>Target jam per hari</span><input type="number" id="e-target" value="8" min="1" max="24" required></label>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Tambah karyawan</button></div>
        <p class="form-msg" id="e-msg" role="status"></p>
        <div id="e-new"></div>
      </form>
    </section>`;
}

async function copyToken(token, btn) {
  try {
    await navigator.clipboard.writeText(token);
    const old = btn.textContent; btn.textContent = 'Tersalin';
    setTimeout(() => { btn.textContent = old; }, 1500);
  } catch { prompt('Salin token ini:', token); }
}

function showNewToken(employee, verb) {
  const box = $('#e-new');
  if (!box) return;
  box.innerHTML = `<div class="notice notice-info" role="status" style="margin:12px 0 0">
    <p><b>${esc(employee.name)}</b> ${esc(verb)}. Token agent:</p>
    <p class="num" style="word-break:break-all;margin:6px 0 10px"><code>${esc(employee.agent_token)}</code></p>
    <button type="button" class="btn btn-secondary" id="e-copy-new">Salin token</button></div>`;
  $('#e-copy-new').addEventListener('click', (ev) => copyToken(employee.agent_token, ev.currentTarget));
  $('#e-copy-new').focus();
}

async function reloadEmployees() {
  EMPLOYEES = (await api('/api/admin/employees')).employees.filter(e => e.active);
  fillEmployeeSelect();
}

function wireEmployees(view) {
  $$('.em-copy', view).forEach(b => b.addEventListener('click', () => copyToken(b.dataset.token, b)));
  $$('.em-toggle', view).forEach(b => b.addEventListener('click', async () => {
    const activate = b.dataset.active !== '1';
    if (!activate && !confirm('Nonaktifkan karyawan ini? Data dari agent-nya tidak akan diterima lagi.')) return;
    try {
      await api(`/api/admin/employees/${b.dataset.id}/active`, { method: 'POST', body: JSON.stringify({ active: activate }) });
      await reloadEmployees();
      await render();
    } catch (e) { alert(e.message); }
  }));
  $$('.em-regen', view).forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Buat token baru? Token lama langsung tidak berlaku, jadi agent di komputer karyawan perlu diisi token baru.')) return;
    try {
      const { employee } = await api(`/api/admin/employees/${b.dataset.id}/regenerate-token`, { method: 'POST' });
      await render();
      showNewToken(employee, 'mendapat token baru');
    } catch (e) { alert(e.message); }
  }));
  $('#emp-form', view).addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const v = (id) => $(id, view).value.trim();
    const msg = $('#e-msg', view);
    const body = {
      name: v('#e-name'), department: v('#e-dept'), position: v('#e-pos'),
      email: v('#e-email') || undefined, timezone: v('#e-tz'), target_hours: Number(v('#e-target')) || 8,
    };
    if (!body.name) { msg.textContent = 'Nama wajib diisi.'; msg.className = 'form-msg is-error'; $('#e-name', view).focus(); return; }
    try {
      const { employee } = await api('/api/admin/employees', { method: 'POST', body: JSON.stringify(body) });
      await reloadEmployees();
      if (!STATE.empId) STATE.empId = employee.id;
      await render();
      showNewToken(employee, 'ditambahkan');
    } catch (e) { msg.textContent = e.message; msg.className = 'form-msg is-error'; }
  });
}

// ---- Dashboard users (admin) ----
function usersSection(users) {
  const rows = users.length ? `<ul class="list">${users.map(u => `
    <li><div class="row">
      <div class="row-main"><p class="row-title">${esc(u.username)}</p><p class="row-sub">${u.role === 'admin' ? 'Admin' : 'Manajer'}</p></div>
      <button type="button" class="btn btn-danger user-del" data-id="${u.id}" aria-label="Hapus pengguna ${esc(u.username)}">Hapus</button>
    </div></li>`).join('')}</ul>`
    : `<div class="state"><b>Belum ada pengguna lain.</b>Tambahkan manajer agar mereka bisa melihat KPI dan menyetujui timesheet.</div>`;
  return `
    <section class="section" aria-labelledby="usr-title">
      <h2 class="section-title" id="usr-title">Pengguna dashboard</h2>
      <p class="section-desc">Admin bisa mengubah semua pengaturan. Manajer melihat KPI dan memutuskan timesheet.</p>
      ${rows}
      <form class="panel" id="user-form" style="margin-top:12px" aria-labelledby="usr-form-title">
        <h3 class="section-title" id="usr-form-title">Tambah pengguna</h3>
        <div class="form-grid cols-2" style="margin-top:12px">
          <label class="field"><span>Username</span><input type="text" id="u-name" maxlength="32" autocomplete="off" required>
            <span class="help">3 sampai 32 karakter: huruf, angka, titik, garis bawah, atau tanda minus.</span></label>
          <label class="field"><span>Password</span><input type="password" id="u-pass" maxlength="128" autocomplete="new-password" required>
            <span class="help">Minimal 8 karakter.</span></label>
          <label class="field"><span>Peran</span><select id="u-role"><option value="manager">Manajer</option><option value="admin">Admin</option></select></label>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Tambah pengguna</button></div>
        <p class="form-msg" id="u-msg" role="status"></p>
      </form>
    </section>`;
}

function wireUsers(view) {
  $('#user-form', view).addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const msg = $('#u-msg', view);
    try {
      await api('/api/admin/users', { method: 'POST', body: JSON.stringify({
        username: $('#u-name', view).value.trim(), password: $('#u-pass', view).value, role: $('#u-role', view).value,
      }) });
      await render();
      const m = $('#u-msg'); if (m) { m.textContent = 'Pengguna ditambahkan.'; m.className = 'form-msg is-ok'; }
    } catch (e) { msg.textContent = e.message; msg.className = 'form-msg is-error'; }
  });
  $$('.user-del', view).forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Hapus pengguna ini? Sesi login-nya langsung berakhir.')) return;
    try { await api(`/api/admin/users/${b.dataset.id}`, { method: 'DELETE' }); await render(); }
    catch (e) { alert(e.message); }
  }));
}

function alertFeed(rows) {
  if (!rows || !rows.length) return `<div class="state"><b>Belum ada alert.</b>Riwayat muncul saat agent pertama kali berganti status.</div>`;
  return `<ul class="list">${rows.map(r => {
    const off = r.type === 'offline';
    return `<li><div class="row">
      <div class="row-main"><p class="row-title">${esc(r.name)}</p>
        <p class="row-sub">${esc(fmtWhen(r.at))}${off && r.offline_minutes != null ? `, ${durLabel(r.offline_minutes)} tanpa laporan` : ''}</p></div>
      ${statusLabel(off ? ['Offline', 'status-bad'] : ['Kembali online', 'status-ok'])}
    </div></li>`;
  }).join('')}</ul>`;
}

// ---------- start ----------
renderNav();
if (TOKEN) { showApp(true); boot().catch(() => logout(true)); } else { showApp(false); }
