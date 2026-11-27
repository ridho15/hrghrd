// Agent offline-alert monitor. Logs online/offline transitions once a minute and
// optionally posts to a Slack/Teams-compatible webhook.
import { getSetting, agentStates, logAlert, lastAlertTypes, offlineThresholdMin } from './db.mjs';

// Last known state per employee. Seeded from the alert log so a server restart
// does not re-alert (or re-fire the webhook) for agents already known offline.
let prevState = null;

async function fireWebhook(employeeName, type, minutes) {
  const url = getSetting('alert_webhook_url', '');
  if (!url) return;
  const text = type === 'offline'
    ? `HRG Tracker: agent ${employeeName} offline, ${minutes} menit tanpa laporan.`
    : `HRG Tracker: agent ${employeeName} kembali online.`;
  try {
    await fetch(url, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ text }),
      signal: AbortSignal.timeout(8000),
    });
  } catch { /* best effort */ }
}

export function evaluateAlerts(names) {
  if (!prevState) prevState = lastAlertTypes();
  for (const s of agentStates(offlineThresholdMin())) {
    const state = s.state === 'offline' ? 'offline' : 'online'; // paused agents still heartbeat
    const prev = prevState.get(s.id) ?? 'online';
    if (state !== prev) {
      logAlert(s.id, state, state === 'offline' ? s.offline_minutes : null);
      fireWebhook(names.get(s.id) || `#${s.id}`, state, s.offline_minutes);
    }
    prevState.set(s.id, state);
  }
}

export function startAlertMonitor(getNames, intervalMs = 60_000) {
  const run = () => {
    try { evaluateAlerts(getNames()); }
    catch (e) { console.error('alert monitor:', e.message); }
  };
  run();
  const timer = setInterval(run, intervalMs);
  timer.unref?.();
  return timer;
}
