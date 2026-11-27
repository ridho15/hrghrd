-- PostgreSQL schema for hrghrd (production).
-- The app currently ships with SQLite (src/db.mjs). This file is the equivalent
-- Postgres DDL for a production migration — see docs/PRODUCTION.md.

CREATE TABLE IF NOT EXISTS employees (
  id            BIGSERIAL PRIMARY KEY,
  name          TEXT NOT NULL,
  email         TEXT UNIQUE,
  department    TEXT,
  position      TEXT,
  agent_token   TEXT UNIQUE NOT NULL,
  target_hours  REAL NOT NULL DEFAULT 8,
  timezone      TEXT NOT NULL DEFAULT 'Asia/Jakarta',
  active        BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS activity_intervals (
  id              BIGSERIAL PRIMARY KEY,
  employee_id     BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
  client_id       TEXT,                    -- agent-generated id (idempotent retries)
  hostname        TEXT,
  started_at      TIMESTAMPTZ NOT NULL,
  ended_at        TIMESTAMPTZ NOT NULL,
  active_seconds  INTEGER NOT NULL DEFAULT 0,
  idle_seconds    INTEGER NOT NULL DEFAULT 0,
  keystrokes      BIGINT NOT NULL DEFAULT 0,
  mouse_moves     BIGINT NOT NULL DEFAULT 0,
  foreground_app  TEXT,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_intervals_emp_time ON activity_intervals(employee_id, started_at);
CREATE UNIQUE INDEX IF NOT EXISTS uq_intervals_start ON activity_intervals(employee_id, started_at);
CREATE UNIQUE INDEX IF NOT EXISTS uq_intervals_client ON activity_intervals(employee_id, client_id)
  WHERE client_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS agent_status (
  employee_id   BIGINT PRIMARY KEY REFERENCES employees(id) ON DELETE CASCADE,
  hostname      TEXT,
  agent_version TEXT,
  paused        BOOLEAN NOT NULL DEFAULT FALSE,
  last_seen     TIMESTAMPTZ NOT NULL
);

CREATE TABLE IF NOT EXISTS app_categories (
  app_name   CITEXT PRIMARY KEY,          -- requires: CREATE EXTENSION IF NOT EXISTS citext;
  category   TEXT NOT NULL DEFAULT 'neutral',
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS app_settings (
  key   TEXT PRIMARY KEY,
  value TEXT
);

CREATE TABLE IF NOT EXISTS users (
  id            BIGSERIAL PRIMARY KEY,
  username      CITEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'manager',
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS agent_alerts (
  id              BIGSERIAL PRIMARY KEY,
  employee_id     BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
  type            TEXT NOT NULL,
  offline_minutes INTEGER,
  at              TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_alerts_time ON agent_alerts(at DESC);

CREATE TABLE IF NOT EXISTS timesheets (
  id              BIGSERIAL PRIMARY KEY,
  employee_id     BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
  period_start    DATE NOT NULL,
  period_end      DATE NOT NULL,
  tracked_seconds INTEGER NOT NULL DEFAULT 0,
  note            TEXT,
  status          TEXT NOT NULL DEFAULT 'submitted',
  reviewer_note   TEXT,
  reviewed_at     TIMESTAMPTZ,
  submitted_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_timesheets_emp ON timesheets(employee_id, submitted_at DESC);
CREATE INDEX IF NOT EXISTS idx_timesheets_status ON timesheets(status, submitted_at DESC);
