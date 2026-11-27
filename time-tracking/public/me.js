// Employee self-view: read-only, own data only (auth via the agent token).
// All dynamic text goes through esc(); no inline handlers (strict CSP).
const $ = (s, root = document) => root.querySelector(s);
let TOKEN = sessionStorage.getItem('hrg_me_token') || null;
const todayLocal = () => new Intl.DateTimeFormat('en-CA').format(new Date());
let DAY = todayLocal();
let TZ = 'UTC';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const nf = (n) => new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
const pct = (x) => `${Math.round(x || 0)}%`;
const hm = (sec) => { sec = Math.round(sec || 0); return `${Math.floor(sec / 3600)}:${String(Math.floor(sec % 3600 / 60)).padStart(2, '0')}`; };
const mins = (sec) => `${Math.round((sec || 0) / 60)} mnt`;
const addDays = (d, n) => { const [y, m, dd] = d.split('-').map(Number); return new Date(Date.UTC(y, m - 1, dd + n)).toISOString().slice(0, 10); };
const dayLabel = (d) => new Date(d + 'T00:00:00Z').toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', timeZone: 'UTC' });
function safeTz(tz) { try { new Intl.DateTimeFormat('en', { timeZone: tz }); return tz; } catch { return 'UTC'; } }
const TZ_ABBR = { 'Asia/Jakarta': 'WIB', 'Asia/Makassar': 'WITA', 'Asia/Jayapura': 'WIT' };
const tzLabel = (tz) => TZ_ABBR[tz] ? `${TZ_ABBR[tz]} (${tz})` : tz;
const hmTz = (iso, tz) => new Intl.DateTimeFormat('en-GB', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(iso));
const hourTz = (iso, tz) => Number(new Intl.DateTimeFormat('en-GB', { timeZone: tz, hour: '2-digit', hour12: false }).format(new Date(iso))) % 24;
const TS_STATUS = { submitted: ['Menunggu', 'status-warn'], approved: ['Disetujui', 'status-ok'], rejected: ['Ditolak', 'status-bad'] };
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
const shortDate = (d, y) => new Date(d + 'T00:00:00Z').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', ...(y ? { year: 'numeric' } : {}), timeZone: 'UTC' });
const periodLabel = (a, b) => `${shortDate(a, a.slice(0, 4) !== b.slice(0, 4))} sampai ${shortDate(b, true)}`;

async function call(path, opts = {}) {
  const res = await fetch(path, {
    ...opts,
    headers: { 'content-type': 'application/json', authorization: `Bearer ${TOKEN}`, ...(opts.headers || {}) },
  }).catch(() => { throw new Error('Tidak bisa terhubung ke server. Periksa koneksi, lalu coba lagi.'); });
  if (res.status === 401) { logout(); throw new Error('Token tidak valid, atau akun Anda dinonaktifkan. Hubungi admin HR.'); }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Permintaan gagal (${res.status})`);
  return data;
}

function showApp(show) {
  $('#app').classList.toggle('hidden', !show);
  $('#login').classList.toggle('hidden', show);
  $('#session').classList.toggle('hidden', !show);
}
function logout() {
  TOKEN = null;
  sessionStorage.removeItem('hrg_me_token');
  showApp(false);
  $('#page-sub').textContent = 'Lihat waktu kerja Anda sendiri dan kirim timesheet.';
}
$('#logout').addEventListener('click', logout);

$('#login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  $('#login-error').textContent = '';
  TOKEN = $('#token').value.trim();
  const btn = $('button[type=submit]', e.currentTarget);
  btn.disabled = true;
  try {
    await render(true);
    sessionStorage.setItem('hrg_me_token', TOKEN);
    $('#token').value = '';
    showApp(true);
  } catch (err) { $('#login-error').textContent = err.message; TOKEN = null; }
  finally { btn.disabled = false; }
});
$('#day').value = DAY;
$('#day').max = DAY;
$('#day').addEventListener('change', () => { DAY = $('#day').value || todayLocal(); render(); });

function intervalList(timeline) {
  if (!timeline.length)
    return `<div class="state"><b>Belum ada aktivitas pada tanggal ini.</b>Agent mengirim data tiap 10 menit selama komputer menyala dan pelacakan tidak dijeda.</div>`;
  const byHour = new Map();
  for (const iv of timeline) {
    const h = hourTz(iv.started_at, TZ);
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
          <span class="ivl-time num">${hmTz(iv.started_at, TZ)}–${hmTz(iv.ended_at, TZ)}</span>
          <span class="ivl-app">${esc(appName(iv.foreground_app))}
            <span class="rule" role="img" aria-label="${pct(share)} aktif"><span style="width:${share}%"></span></span></span>
          <span class="ivl-min num">${mins(iv.active_seconds)}</span>
        </li>`;
      }).join('')}</ol></div>`;
  }).join('');
}

function productivityPanel(s) {
  const p = s.productivity || { productive: 0, neutral: 0, unproductive: 0 };
  const total = p.productive + p.neutral + p.unproductive;
  const w = (v) => total ? (v / total * 100).toFixed(1) : 0;
  return `<section class="panel" aria-labelledby="prod-title">
    <div class="panel-head"><h2 id="prod-title">Pemakaian aplikasi</h2><p class="num">${total ? `${pct(s.kpi.productivePct)} produktif` : 'Belum ada data'}</p></div>
    ${total ? `<div class="split" role="img" aria-label="Produktif ${w(p.productive)}%, netral ${w(p.neutral)}%, non-produktif ${w(p.unproductive)}%">
      ${p.productive ? `<span class="s-prod" style="width:${w(p.productive)}%"></span>` : ''}
      ${p.neutral ? `<span class="s-neu" style="width:${w(p.neutral)}%"></span>` : ''}
      ${p.unproductive ? `<span class="s-unp" style="width:${w(p.unproductive)}%"></span>` : ''}</div>`
      : '<div class="split" aria-hidden="true"><span class="split-empty"></span></div>'}
    <ul class="legend">
      <li><i style="background:var(--brand-bar)"></i>Produktif<span class="v num">${mins(p.productive)}</span></li>
      <li><i style="background:var(--neutral-bar)"></i>Netral<span class="v num">${mins(p.neutral)}</span></li>
      <li><i style="background:var(--danger-bar)"></i>Non-produktif<span class="v num">${mins(p.unproductive)}</span></li>
    </ul>
  </section>`;
}

async function render(throwErrors = false) {
  const view = $('#view');
  view.setAttribute('aria-busy', 'true');
  if (!throwErrors) view.innerHTML = '<p class="state" role="status">Memuat data Anda…</p>';
  try {
    // The server interprets fromDate/toDate in this employee's own timezone.
    const { summary: s, timeline } = await call(`/api/agent/me?fromDate=${DAY}&toDate=${DAY}`);
    TZ = safeTz(s.employee.timezone);
    $('#session-user').textContent = s.employee.name;
    $('#page-sub').textContent = `${s.employee.name}, ${dayLabel(DAY)}`;
    $('#day').value = DAY;
    const targetH = s.employee.target_hours || 8;
    const toTarget = Math.min(100, s.working_seconds / (targetH * 3600) * 100);
    view.innerHTML = `
      <div class="tracker-grid">
        <div>
          <section class="panel focus" aria-labelledby="focus-label">
            <h2 class="focus-label" id="focus-label">Waktu kerja Anda, ${esc(dayLabel(DAY))}</h2>
            <p class="focus-value num">${hm(s.working_seconds)}<small>jam</small></p>
            <div class="rule rule-focal" role="img" aria-label="${pct(toTarget)} dari target ${targetH} jam"><span style="width:${toTarget}%"></span></div>
            <p class="focus-meta"><span class="num">${pct(toTarget)} dari target ${targetH} jam</span><span>${esc(tzLabel(TZ))}</span></p>
            <dl class="metrics">
              <div class="metric"><dt>Waktu aktif</dt><dd class="num">${pct(s.kpi.activityPct)}</dd><dd class="metric-note">sisanya idle</dd></div>
              <div class="metric"><dt>Skor KPI</dt><dd class="num">${Math.round(s.kpi.score)}</dd><dd class="metric-note">dari 100</dd></div>
              <div class="metric"><dt>Ketikan</dt><dd class="num">${nf(s.keystrokes)}</dd><dd class="metric-note">jumlah, bukan isi</dd></div>
              <div class="metric"><dt>Gerakan mouse</dt><dd class="num">${nf(s.mouse_moves)}</dd><dd class="metric-note">jumlah</dd></div>
            </dl>
          </section>
          ${productivityPanel(s)}
          <div class="notice notice-info" style="margin-top:16px"><p>Ini data Anda sendiri. Yang dicatat hanya jumlah ketikan, gerakan mouse, dan nama aplikasi. Isi ketikan dan screenshot tidak pernah direkam.</p></div>
          <div id="ts-section"></div>
        </div>
        <section class="panel" aria-labelledby="ivl-title">
          <div class="panel-head"><h2 id="ivl-title">Aktivitas per 10 menit</h2><p>Garis menunjukkan porsi waktu aktif</p></div>
          ${intervalList(timeline)}
        </section>
      </div>`;
    renderTimesheets().catch((e) => {
      const el = $('#ts-section');
      if (el) el.innerHTML = `<div class="state" role="alert"><b>Timesheet gagal dimuat.</b>${esc(e.message)}</div>`;
    });
  } catch (err) {
    if (throwErrors) throw err;
    view.innerHTML = `<div class="state" role="alert"><b>Data gagal dimuat.</b>${esc(err.message)}
      <br><button type="button" class="btn btn-secondary" id="retry">Coba lagi</button></div>`;
    $('#retry')?.addEventListener('click', () => render());
  } finally {
    view.removeAttribute('aria-busy');
  }
}

// ---------- Timesheet ----------
async function renderTimesheets() {
  const el = $('#ts-section');
  if (!el) return;
  const { timesheets } = await call('/api/agent/timesheets');
  const today = todayLocal();
  const rows = timesheets.length ? `<ul class="list">${timesheets.map(t => {
    const [label, cls] = TS_STATUS[t.status] || [t.status, 'status-none'];
    return `<li><div class="row">
        <div class="row-main"><p class="row-title num">${esc(periodLabel(t.period_start, t.period_end))}</p>
          <p class="row-sub num">${hm(t.tracked_seconds)} jam tercatat</p></div>
        <span class="status ${cls}">${esc(label)}</span>
      </div>${t.reviewer_note ? `<p class="note">Catatan reviewer: ${esc(t.reviewer_note)}</p>` : ''}</li>`;
  }).join('')}</ul>`
    : `<div class="state"><b>Belum ada timesheet.</b>Kirim timesheet pertama Anda lewat formulir di atas.</div>`;

  el.innerHTML = `
    <section class="section" aria-labelledby="ts-title">
      <h2 class="section-title" id="ts-title">Timesheet</h2>
      <p class="section-desc">Jam tercatat diambil otomatis dari agent untuk periode yang Anda pilih, lalu diputuskan oleh atasan.</p>
      <form class="panel" id="ts-form">
        <div class="form-row">
          <label class="field"><span>Dari</span><input type="date" id="ts-from" value="${addDays(today, -6)}" max="${today}" required></label>
          <label class="field"><span>Sampai</span><input type="date" id="ts-to" value="${today}" max="${today}" required></label>
        </div>
        <label class="field" style="margin-top:12px"><span>Catatan untuk atasan <span class="help">(opsional)</span></span>
          <input type="text" id="ts-note" maxlength="500"></label>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Kirim timesheet</button></div>
        <p class="form-msg" id="ts-msg" role="status"></p>
      </form>
      <div style="margin-top:12px">${rows}</div>
    </section>`;

  $('#ts-form').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const btn = $('button[type=submit]', ev.currentTarget), msg = $('#ts-msg');
    btn.disabled = true;
    try {
      await call('/api/agent/timesheets', {
        method: 'POST',
        body: JSON.stringify({ period_start: $('#ts-from').value, period_end: $('#ts-to').value, note: $('#ts-note').value.trim() }),
      });
      await renderTimesheets();
      const m = $('#ts-msg'); m.textContent = 'Timesheet terkirim dan menunggu keputusan atasan.'; m.className = 'form-msg is-ok';
    } catch (e) { msg.textContent = e.message; msg.className = 'form-msg is-error'; btn.disabled = false; }
  });
}

if (TOKEN) { showApp(true); render(); } else { showApp(false); }
