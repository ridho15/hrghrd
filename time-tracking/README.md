# hrghrd: HR Group HRD App (time tracking and KPI module)

Transparent employee **time tracking & KPI** for HR/HRD.

- Working time, active vs idle, keystroke & mouse **counts** (numbers only, no
  key contents, **no screenshots, no keylogging**). Locked screen, paused and
  "away" time are **not** counted.
- App **productivity categories** (productive / neutral / unproductive) feeding a
  transparent KPI score.
- Admin KPI dashboard, per-employee tracker, employee self-view.
- **Timesheet** submission + manager **approval** workflow.
- **Agent offline alerts** (dashboard banner, history, optional Slack/Teams webhook).
- **Roles**: admin (full) and manager (read KPI + approve). Hashed passwords,
  login rate limiting, CSP + security headers, optional HTTPS.
- **Per-employee timezone**: day boundaries and times follow each employee's zone.
- **Export**: CSV + printable PDF report.
- A **visible** Windows agent (tray icon, autostart, first-run consent) with a
  one-click installer.

## Stack

- **Backend**: Node.js ≥ 22.13, built-in `node:http` + `node:sqlite`, **zero npm deps**.
- **Frontend**: static HTML/CSS/JS in the HR Group HRIS visual language (see [`DESIGN.md`](DESIGN.md)), strict CSP (no inline scripts), self-hosted Plus Jakarta Sans (OFL).
- **Agent**: .NET 8 / WinForms (`agent/`), built on Windows or by GitHub Actions.

## Run the backend

```bash
cp .env.example .env        # set ADMIN_PASSWORD_HASH (prod) or ADMIN_PASSWORD (dev), PORT, DB_PATH
npm run seed                # DEV ONLY: sample data (refuses to wipe a DB that has employees)
npm start                   # http://localhost:3000
npm test                    # end-to-end API tests (spins up a throwaway server)
```

Or with Docker:

```bash
docker build -t hrg-timetracking .
docker run -d -p 3000:3000 -v hrg-data:/data -e ADMIN_PASSWORD_HASH='scrypt$...' hrg-timetracking
```

- **Admin dashboard**: `/` → leave username empty and use the admin password
  (bootstrap), or log in with a dashboard user created under Settings.
- **Employee self-view**: `/me.html` → log in with the employee's agent token
  (read-only, own data only; employees can submit timesheets here).

Production checklist (HTTPS, hashed password, `TRUST_PROXY`, `ADMIN_BOOTSTRAP=off`,
backups, PostgreSQL path): see [`docs/PRODUCTION.md`](docs/PRODUCTION.md).

## Windows agent

See [`agent/README.md`](agent/README.md). The **Time Tracking: Agent & Installer**
workflow produces `HrgTracker-Setup-<ver>.exe`; employees run it and paste the
Server URL + their token (Settings → Kelola Karyawan → 📋 Token).

## API

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/login` / `/api/logout` | none / session | dashboard session |
| POST | `/api/agent/heartbeat` | agent token | online/paused status |
| POST | `/api/agent/activity` | agent token | one interval (idempotent via `client_id`; 422 = rejected) |
| GET  | `/api/agent/me?fromDate&toDate` | agent token | own summary + timeline |
| POST/GET | `/api/agent/timesheets` | agent token | submit / list own timesheets |
| GET  | `/api/admin/me` | admin+mgr | current role |
| GET  | `/api/admin/kpi?fromDate&toDate` | admin+mgr | all-employee KPI + agent state |
| GET/POST | `/api/admin/employees` | read: admin+mgr (no tokens for mgr) / create: admin | employees |
| POST | `/api/admin/employees/:id/active` · `/regenerate-token` | admin | deactivate / rotate token |
| GET  | `/api/admin/employees/:id/timeline?fromDate&toDate` | admin+mgr | per-interval detail |
| GET/POST | `/api/admin/categories` | read: admin+mgr / set: admin | productivity categories |
| GET  | `/api/admin/timesheets` · POST `/:id/review` | admin+mgr | approvals |
| GET  | `/api/admin/alerts` · POST `/api/admin/settings` | admin+mgr / admin | offline alerts |
| GET/POST/DELETE | `/api/admin/users` | admin | dashboard users |
| GET  | `/api/admin/export/kpi.csv` · `/api/admin/report` | admin+mgr | CSV / printable report |

`fromDate`/`toDate` are local dates (`YYYY-MM-DD`) interpreted in **each
employee's own timezone**.

## KPI formula

```
score = 0.5 × min(active_hours / (target_hours × expected_days), 1)
      + 0.2 × active / (active + idle)
      + 0.3 × productive / active
```

`expected_days` = weekdays (Mon–Fri, employee's timezone) in the range up to
today, plus any weekend day with activity. Weights are explicit and tunable.

## Privacy stance

Monitoring is **transparent and consensual**: the agent is visible, asks for
consent on first run, can be paused by the employee, and records only aggregate
counts and the active app name, never keystroke contents, passwords, or screen
captures.
