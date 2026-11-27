# Production hardening guide

What ships hardened, and what to do before going live.

## 1. Admin password (done)

Never run with the default password in production. Generate a hash and set it:

```bash
npm run hash-password -- 'YourStrongPassword'     # prints scrypt$...$...
# then in the environment:
export ADMIN_PASSWORD_HASH='scrypt$...$...'       # ADMIN_PASSWORD is then ignored
```

Passwords are hashed with scrypt; the dashboard warns while the default is in use.
Once real admin users exist (Settings → Pengguna Dashboard), set
`ADMIN_BOOTSTRAP=off` to disable the env-password login entirely.

## 2. Users & roles (done)

- **admin**: full access (manage employees, categories, alert settings, users).
- **manager**: read KPI + approve/reject timesheets only.

Create managers from the dashboard (Settings → Pengguna Dashboard) or:

```bash
curl -X POST $URL/api/admin/users -H "authorization: Bearer $ADMIN_TOKEN" \
  -H 'content-type: application/json' \
  -d '{"username":"manajer1","password":"...","role":"manager"}'
```

Sessions are re-validated on every request: deleting a user (or logging out)
revokes access immediately. Login rate limiting counts failures per IP (30 / 10
min) **and** per account (10 / 10 min); a successful login does not reset it.
Behind a reverse proxy set `TRUST_PROXY=1` so the real client IP is used.
A strict Content-Security-Policy and other security headers are sent on every response.

## 3. HTTPS (done, two options)

> Agent tokens travel in an `Authorization` header, so always use HTTPS in production.
> The installer warns when a plain `http://` server URL is entered.

- **Direct**: set `TLS_CERT` and `TLS_KEY` to PEM paths; the server serves HTTPS.
- **Reverse proxy (recommended)**: terminate TLS at nginx/Caddy and proxy to the
  app on localhost. Example (Caddy): `hrd.perusahaan.co.id { reverse_proxy localhost:3000 }`.

## 3b. Docker

A `Dockerfile` is included (Node 22 alpine, non-root, healthcheck, data volume at `/data`):

```bash
docker build -t hrg-timetracking .
docker run -d --name hrg-tt -p 3000:3000 -v hrg-data:/data \
  -e ADMIN_PASSWORD_HASH='scrypt$...' -e TRUST_PROXY=1 hrg-timetracking
```

## 4. PostgreSQL (migration guide)

The app ships with SQLite (`node:sqlite`, zero-dep) which is fine for a single
node and moderate load. For multiple app instances or heavy write volume, move
to PostgreSQL:

1. Create the database and apply [`db/postgres-schema.sql`](../db/postgres-schema.sql)
   (run `CREATE EXTENSION IF NOT EXISTS citext;` first).
2. Swap the data layer in `src/db.mjs` to a Postgres client (e.g. `pg`). The
   query shapes are standard SQL; the main changes are:
   - `datetime('now')` → `now()`, and `julianday(...)` math in
     `offlineAgents`/`agentStates` → `EXTRACT(EPOCH FROM now() - last_seen)/60`.
   - `INSERT ... ON CONFLICT` is already Postgres-compatible syntax.
   - async/await the calls (the rest of the app already awaits route handlers).
3. Point `DATABASE_URL` at Postgres and keep `src/kpi.mjs` / `src/alerts.mjs`
   query strings: they are portable SQL.

> This separation (all SQL lives in `src/db.mjs`) is intentional so the swap is
> contained to one file.

## 5. Data retention (recommended)

Add a periodic job to drop old rows per your policy, e.g.:

```sql
DELETE FROM activity_intervals WHERE started_at < now() - interval '180 days';
DELETE FROM agent_alerts       WHERE at         < now() - interval '180 days';
```

## 6. Backups

Back up the SQLite file (`DB_PATH`) or use managed Postgres backups. The agent's
offline queue means short outages don't lose data, but the server DB is the
system of record.
