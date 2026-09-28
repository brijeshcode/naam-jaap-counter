# Naam Jaap Counter — Server Sync Design

Date: 2026-09-28

## Goal

Make the counter device-independent and offline-capable. Counts live on a
server so a user logs in from any single device, works offline, and auto-syncs
when back online. Per-day history is kept so we can chart daily/monthly/yearly
totals. A day with no chanting stores no row.

## Users & scale

Up to ~100,000 users. Concurrency is not really a problem here because each
account is single-device (see Single-device below), but MySQL with proper
indexes handles the volume regardless.

## Stack

PHP + MySQL (PDO), Alpine.js for frontend state, fetch/AJAX for the API,
Tailwind (CDN) for the login/register screens, existing custom CSS reused for
the counter. Mobile-first. PWA (service worker + manifest) for offline.
Charts via Chart.js (CDN), bar type.

## File layout

```
/naam-jaap/
  index.php     app shell: auth screen + counter + history tab (Alpine + Tailwind CDN + existing CSS)
  api.php       single router: ?action=register|login|logout|push|pull|chart
  db.php        PDO connection + session/token helpers
  sw.js         service worker (cache app shell for offline)
  manifest.json PWA manifest (mobile install)
  i18n.js       language dictionary (en/hi)
  schema.sql    table definitions + seed mantra
```

## Database

```sql
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  session_token CHAR(64) NULL,          -- current active device; NULL when logged out
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE mantras (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  name   VARCHAR(100) NOT NULL
);
INSERT INTO mantras (id, name) VALUES (1, 'Default');  -- only mantra for now, hidden from UI

CREATE TABLE daily_counts (
  user_id   INT NOT NULL,
  mantra_id INT NOT NULL DEFAULT 1,
  day       DATE NOT NULL,
  count     BIGINT UNSIGNED NOT NULL DEFAULT 0,   -- huge headroom, never hits a limit
  PRIMARY KEY (user_id, mantra_id, day),
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (mantra_id) REFERENCES mantras(id)
);
```

`mantra_id` exists now so future multi-mantra needs no migration; all current
writes use `mantra_id = 1`. A `daily_counts` row exists only after a day's count
goes above 0 — that is the "skip empty days" requirement, for free.

## Derived values (never stored)

- Total Jaap = `SUM(count)` for the user (current mantra).
- Malas = `floor(total / 108)`.
- Current bead = `total % 108`.

## Counter rules

- **Increase only.** No UI or API path decreases a count, except per-day reset
  (below). Enforced client-side and server-side.
- **Manual add:** user can enter a number (counts done on a physical mala/clicker)
  and add it to today's count. Same increase-only path.
- **Reset:** resets **today only**, sets today's count to 0, requires a
  confirmation dialog to prevent accidental data loss. Never touches other days.

## Auth & single-device

- Register: `password_hash()`, insert user. Open self-registration.
- Login: `password_verify()`. If the user already has a non-null `session_token`
  (another device active), the API returns a "device active" flag and the client
  shows a confirmation — "Logging in here will log you out from your other
  device. Continue?" On confirm, the client re-calls login with a `force` flag;
  the server then generates a new random 64-char `session_token`, overwriting the
  old one, and returns it. The client stores it in localStorage. The displaced
  device gets a `409` on its next sync and returns to login.
- Every `push`/`pull`/`chart` call includes the token. If it does not match the
  stored token → `409` "logged in on another device"; client stops syncing,
  shows a message, returns to login.
- Logout: set `session_token = NULL`, clear local token.
- **No auto-logout / no session expiry.** The token never times out. It is
  cleared only by: the user tapping logout, a new-device login displacing it, or
  (future) an admin action. A daily mobile user never has to re-login.
- **Admin panel:** future work, not built now. Design leaves room: the admin
  action to force-logout is just `UPDATE users SET session_token = NULL`.
- **Tradeoff (accepted):** unsynced offline counts on a device that has been
  invalidated by a login elsewhere are lost.

## Offline + sync model

Client is authoritative for the active session; single-device means no merge
conflicts. Local store (localStorage/IndexedDB) holds per-day counts plus a
"dirty days" set (days changed since last successful push).

**Seed (fresh login or Refresh button):** `pull` → overwrite local from server.
Refresh pushes any pending dirty days first, then pulls.

**Push payload:** `{ token, days: { "YYYY-MM-DD": count, ... } }` for dirty days.
Server upserts each with `count = GREATEST(count, incoming)` (monotonic guard),
clears dirty on success.

**Sync triggers (when online):**
1. Every 5 minutes, if any day is dirty → push.
2. On mala completion (today's count crosses a multiple of 108) → push
   immediately, **unless** less than 100 s since the last push, in which case
   skip and let the 5-min timer handle it (batches rapid tapping / bulk adds).
3. On reconnect (`online` event) → push.
4. On `pagehide`/`visibilitychange` → `navigator.sendBeacon` flush.

Offline: taps just update local per-day count + dirty set; nothing blocks.

## API (`api.php?action=...`), all JSON

| action     | method | input | output / effect |
|------------|--------|-------|-----------------|
| `register` | POST | username, password | create user + login; `{token, total, today}` |
| `login`    | POST | username, password, force? | verify; if a device is already active and `force` not set → `{device_active:true}` (client confirms); with `force` → rotate token, displace other device; `{token, total, today}` |
| `logout`   | POST | token | clear server token |
| `push`     | POST | token, days{} | `GREATEST` upsert per day; `{ok, total}`; 409 if token stale |
| `pull`     | POST | token | `{ total, days: recent per-day counts }` to seed local |
| `chart`    | GET  | token, type=daily\|monthly\|yearly, cursor | aggregated buckets for the requested window |

Server day = `CURDATE()` (server timezone). Documented limitation: "today" is the
server's day, not each user's local day. Since counts are per-day and single
device, this only shifts where a near-midnight tap lands.

## History tab (bottom nav) — charts

Bar charts via Chart.js (CDN), mobile-first.

- **Daily:** last 7 days; top arrows page to older/newer 7-day windows.
- **Monthly:** last 12 months (SUM per year-month); arrows page 12 at a time.
- **Yearly:** last 10 years (SUM per year); arrows page 10 at a time.

`chart` action returns the requested bucket set given `type` + `cursor` (the
window offset). Aggregation via `GROUP BY day` / `DATE_FORMAT(day,'%Y-%m')` /
`YEAR(day)`.

## Number display (frontend only)

- Default: thousands separator via `Intl.NumberFormat` → `12,345`.
- Toggle: K-notation → `12.3K` (one small formatter). Device setting in
  localStorage. No effect on stored/synced values.

## Language (i18n)

English default, toggle to Hindi. Simple key→string dictionary in `i18n.js`
(`en`, `hi`); Alpine binds text to the active dictionary. Choice stored in
localStorage (device setting).

## Error handling

- Push fails (network): keep dirty days, retry next trigger; show "not synced"
  indicator. Nothing dropped.
- 409 stale token: message + return to login.
- 401 (no/invalid session): return to login.
- Duplicate username / bad login: inline message.

## Testing

- `schema.sql` loads cleanly; seed mantra present.
- Assert-based self-checks for the tricky logic: mala rollover + derived
  (total → malas, current bead); the "mala push unless <100 s since last push"
  gate; `GREATEST` monotonic upsert; chart bucketing (7/12/10 windows +
  cursor paging).
- Manual: register; tap past 108; go offline, tap, come back online → auto-sync;
  Refresh pulls server data; login on a second device → first device gets 409;
  reset clears today only; language toggle switches text; empty day makes no row;
  install as PWA on mobile.

## Explicitly out of scope (now)

- Admin panel (future; force-logout is just clearing `session_token`).
- Multi-mantra UI (schema ready, UI later).
- Password reset / email.
- Per-user timezones (server day only).
- Multi-device concurrent use (single device by design).
- Leaderboard / other users' data.

## Note

Not currently a git repository — spec saved, not committed. Needs a PHP + MySQL
host to run; PWA offline requires serving over HTTPS.
