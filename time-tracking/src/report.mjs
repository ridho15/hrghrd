// Builds a self-contained, print-optimized HTML KPI report (print → Save as PDF).
// No inline scripts (CSP-safe); the dashboard triggers window.print().
// Visual language follows DESIGN.md (HRIS emerald / slate, Plus Jakarta Sans).
import { allEmployeesSummary } from './kpi.mjs';

export function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
const hm = (sec) => {
  sec = Math.round(sec || 0);
  return `${Math.floor(sec / 3600)}:${String(Math.floor(sec % 3600 / 60)).padStart(2, '0')}`;
};

export function buildKpiReport(q = {}) {
  const rows = allEmployeesSummary(q).sort((a, b) => a.kpi.score - b.kpi.score);
  const period = q.fromDate && q.toDate
    ? `${q.fromDate} sampai ${q.toDate}, waktu lokal tiap karyawan`
    : `${String(q.from || 'awal').slice(0, 10)} sampai ${String(q.to || 'sekarang').slice(0, 10)}`;
  const avg = rows.length ? rows.reduce((a, r) => a + r.kpi.score, 0) / rows.length : 0;
  const totalSec = rows.reduce((a, r) => a + r.working_seconds, 0);
  const low = rows.filter(r => r.kpi.score < 50).length;

  const body = rows.map((r, i) => `
    <tr>
      <td class="n">${i + 1}</td>
      <td><b>${esc(r.employee.name)}</b><br><span class="muted">${esc(r.employee.position || r.employee.department || '')}</span></td>
      <td class="n">${hm(r.working_seconds)}</td>
      <td class="n">${r.days}</td>
      <td class="n">${Math.round(r.kpi.activityPct)}%</td>
      <td class="n">${Math.round(r.kpi.productivePct)}%</td>
      <td class="n">${Number(r.keystrokes).toLocaleString('id-ID')}</td>
      <td class="n">${Number(r.mouse_moves).toLocaleString('id-ID')}</td>
      <td class="n score${r.kpi.score < 50 ? ' low' : ''}">${Math.round(r.kpi.score)}</td>
    </tr>`).join('');

  return `<!doctype html><html lang="id"><head><meta charset="utf-8">
<title>Laporan KPI ${esc(period)}</title>
<style>
  @font-face { font-family: "Plus Jakarta Sans"; font-weight: 400; src: url("/fonts/plus-jakarta-sans-400.woff2") format("woff2"); }
  @font-face { font-family: "Plus Jakarta Sans"; font-weight: 700; src: url("/fonts/plus-jakarta-sans-700.woff2") format("woff2"); }
  * { box-sizing: border-box; }
  body { font: 13px/1.5 "Plus Jakarta Sans", "Segoe UI", Arial, sans-serif; color: #0f172a; margin: 32px; }
  h1 { margin: 0 0 4px; font-size: 20px; }
  .sub { color: #475569; margin: 0 0 20px; }
  .facts { display: flex; gap: 32px; margin: 0 0 20px; padding: 0; flex-wrap: wrap; }
  .facts div { border-top: 3px solid #059669; padding-top: 6px; min-width: 120px; }
  .facts dt { color: #475569; font-size: 12px; }
  .facts dd { margin: 0; font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  th { color: #475569; font-size: 12px; font-weight: 700; border-bottom: 1px solid #cbd5e1; }
  td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; }
  td.score { font-weight: 700; }
  td.score.low { color: #be123c; }
  .muted { color: #475569; font-size: 12px; }
  .foot { margin-top: 20px; color: #475569; font-size: 12px; }
  .hint { color: #475569; font-size: 12px; margin: 0 0 16px; }
  @media print { body { margin: 12mm; } .hint { display: none; } @page { size: A4 landscape; } }
</style></head><body>
  <p class="hint">Tekan Ctrl+P (Mac: Cmd+P), lalu pilih Simpan sebagai PDF.</p>
  <h1>Laporan KPI karyawan</h1>
  <p class="sub">HR Group Time Tracking. Periode ${esc(period)}. Dibuat ${esc(new Date().toLocaleString('id-ID'))}.</p>
  <dl class="facts">
    <div><dt>Karyawan</dt><dd>${rows.length}</dd></div>
    <div><dt>Total waktu kerja</dt><dd>${hm(totalSec)} jam</dd></div>
    <div><dt>Rata-rata skor KPI</dt><dd>${Math.round(avg)}</dd></div>
    <div><dt>Skor di bawah 50</dt><dd>${low}</dd></div>
  </dl>
  <table>
    <thead><tr>
      <th class="n">No</th><th>Karyawan</th><th class="n">Waktu kerja</th><th class="n">Hari kerja</th>
      <th class="n">Aktif</th><th class="n">Produktif</th><th class="n">Ketikan</th><th class="n">Gerakan mouse</th><th class="n">Skor KPI</th>
    </tr></thead>
    <tbody>${body || '<tr><td colspan="9">Tidak ada karyawan aktif pada periode ini.</td></tr>'}</tbody>
  </table>
  <p class="foot">Diurutkan dari skor terendah. Yang dicatat hanya jumlah aktivitas dan nama aplikasi, tanpa isi ketikan atau screenshot.
    Skor KPI = 50% jam aktif terhadap target (target x hari kerja), 20% porsi waktu aktif, 30% porsi aplikasi produktif.</p>
</body></html>`;
}
