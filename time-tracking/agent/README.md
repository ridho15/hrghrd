# HRG Tracker: Windows Agent

A **visible** desktop time-tracking agent for the HRG HRD backend. Per 10-minute
interval it reports:

- active vs idle seconds (locked screen, paused, and "away" > 10 min are **not** counted),
- the **count** of keystrokes and mouse movements (numbers only),
- the most-used application (process name).

It never logs keystroke contents, passwords, or screenshots. The tray icon is
always visible; the employee can open the status window (today's totals), pause,
or quit at any time.

## One-click installer (recommended)

**GitHub Actions builds it for you** (no Windows machine needed):

1. Repo **Actions** tab → **Time Tracking: Agent & Installer** → latest run.
2. Download the **`HrgTracker-Setup`** artifact (zip with `HrgTracker-Setup-<ver>.exe`).
3. Give the `.exe` to employees. Running it:
   - installs per-user to `%LOCALAPPDATA%\HrgTracker` (no admin rights),
   - asks for **Server URL** + **Agent Token** (validated; warns on plain `http://`),
   - registers autostart and launches the tray agent (consent dialog on first run).

**Silent / mass deployment** (Intune, GPO, login script), which also starts the agent:

```powershell
HrgTracker-Setup-0.2.0.exe /VERYSILENT /SERVER="https://hrd.perusahaan.co.id" /TOKEN="TOKEN_KARYAWAN"
```

- A silent install **without** a token aborts, unless a previous install's
  configuration exists.
- **Upgrades** keep the existing Server URL + token (run the new setup with no
  parameters); a running agent is stopped and restarted.
- **Uninstall** removes the exe, the token/config, the unsent queue and the
  autostart entry.

> Unsigned executables that use keyboard/mouse hooks may be flagged by
> SmartScreen/antivirus. For a company rollout, sign `HrgTracker.exe` and the
> setup with your code-signing certificate (add a `signtool` step to the workflow).

## Build locally (Windows)

```powershell
cd agent
dotnet publish HrgAgent.csproj -c Release -r win-x64 -p:PublishSingleFile=true -p:SelfContained=true -o dist
iscc /DMyAppVersion=0.2.0 installer\HrgTracker.iss     # needs Inno Setup 6
```

Compile check on Linux/macOS (won't run): `dotnet build -p:EnableWindowsTargeting=true`
(needs Microsoft's official .NET SDK, which includes the Windows Desktop SDK).

## Configuration

`appsettings.json` next to the exe (written by the installer):

```json
{
  "serverUrl": "https://hrd.perusahaan.co.id",
  "agentToken": "TOKEN_KARYAWAN",
  "reportIntervalSeconds": 600,
  "heartbeatSeconds": 60,
  "idleThresholdSeconds": 60,
  "awayThresholdSeconds": 600
}
```

`HRG_SERVER_URL` / `HRG_AGENT_TOKEN` environment variables override the file.
The employee's own choices (consent, autostart) live in `state.json` and are
never reset by upgrades.

## How it talks to the backend

| When | Request |
|------|---------|
| every 60 s (also while paused) | `POST /api/agent/heartbeat` → online / paused |
| every 10 min, on pause, lock, sleep, logoff, quit | `POST /api/agent/activity` → one interval |

Each interval has a `client_id`, so re-sends after a crash are de-duplicated by
the server. Unsent intervals are kept in `%LOCALAPPDATA%\HrgTracker\pending.json`
(written atomically) and retried; intervals the server rejects permanently (HTTP
4xx other than 401/408/429) are dropped instead of retried forever.

## Files

| File | Role |
|------|------|
| `Program.cs` | entry point, per-session single instance, token check, consent |
| `TrayApplicationContext.cs` | tray icon, 1 s loop, pause, lock/sleep/logoff handling, quit |
| `ActivityTracker.cs` | input hooks on a dedicated thread (counts only), active/idle/away timing, today's totals |
| `NativeMethods.cs` | P/Invoke (no key codes are read) |
| `ApiClient.cs` | HTTP to the backend (Sent / Retry / Drop) |
| `OfflineQueue.cs` | durable, single-flight retry queue |
| `StatusForm.cs` | visible status window |
| `Autostart.cs` | per-user HKCU Run entry |
| `Config.cs` | install settings + per-user state |
| `installer/HrgTracker.iss` | Inno Setup one-click installer |
