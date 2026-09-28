# Naam Jaap Counter — Server Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the single-file localStorage counter into an offline-capable, single-device, server-synced PHP/MySQL app with login, per-day history charts, and Hindi/English UI.

**Architecture:** A thin PHP router (`api.php`) exposes JSON endpoints backed by MySQL (PDO). All business logic lives in small pure-ish PHP function files (`auth.php`, `counts.php`, `charts.php`) so it is unit-testable without HTTP. The client (`index.php`) is an Alpine.js app: it keeps per-day counts locally (localStorage), is authoritative during a session, and pushes dirty days to the server on a timer / mala boundary / reconnect. A service worker caches the shell for offline use.

**Tech Stack:** PHP 8+, MySQL 8+, PDO, Alpine.js (CDN), Tailwind (CDN, auth screens only), Chart.js (CDN), existing custom CSS (counter), vanilla PWA (service worker + manifest). Tests: `php` CLI scripts using `assert` against a MySQL test DB; `node` scripts using `node:assert` for pure JS helpers.

**Spec:** `docs/superpowers/specs/2026-09-28-naam-jaap-server-sync-design.md`

## Global Constraints

- **Counter is monotonic increase-only.** No path lowers a day's count except per-day reset (sets one day to 0). Enforced client AND server (`count = GREATEST(count, incoming)`).
- **`count` column is `BIGINT UNSIGNED`.** Never narrow it.
- **Single active device per user** via `users.session_token` (64 hex chars). No time-based expiry — token cleared only by logout, a forced new-device login, or (future) admin `UPDATE users SET session_token=NULL`.
- **New-device login requires confirmation:** server returns `device_active` when a token already exists; client re-calls with `force=1`.
- **`mantra_id` on every count row, default 1.** Seed one mantra; no mantra UI now.
- **Skip empty days:** never write a `daily_counts` row for count 0 (except reset, which deletes the day's row).
- **Passwords:** `password_hash()` / `password_verify()` only. Never store or log plaintext.
- **All API responses are JSON** with `Content-Type: application/json`. Auth failures → HTTP 401; stale/foreign token → HTTP 409.
- **DB config from env** with defaults: `NJ_DB_HOST` (127.0.0.1), `NJ_DB_NAME` (naam_jaap), `NJ_DB_USER` (root), `NJ_DB_PASS` (''). Tests set `NJ_DB_NAME=naam_jaap_test`.
- **Server "day" = `CURDATE()`** (server timezone). Documented limitation, not a bug.
- **Default language English**, toggle Hindi. **Default number format** comma-grouped, toggle K-notation. Both are localStorage device settings.

## Review Focus

- **Replayed/duplicate push** (same dirty day sent twice after a flaky network): must NOT double-count. `GREATEST` upsert makes push idempotent → tested in Task 4.
- **Offline across midnight** (taps accumulate on day A, device reconnects on day B): both days must sync, not just "today". Client keeps a per-day map + dirty set, not a single counter → tested in Task 7.
- **Displaced device keeps tapping** (old device offline, user forced login elsewhere, old device returns online): old device's push must be rejected (409) and not overwrite the new device's data → tested in Task 4.
- **Manual add of a huge number / non-numeric / negative** (physical mala entry): non-positive or non-integer input is ignored; large positive values are accepted (BIGINT) and still increase-only → tested in Task 6.
- **Chart request past the earliest data** (user pages arrows beyond history): returns empty/zero buckets for the window, never errors → tested in Task 5.

---

## File Structure

- `schema.sql` — tables + seed mantra (create).
- `db.php` — `db(): PDO` (env config), `new_token(): string`, `user_id_for_token(PDO,string): ?int` (create).
- `auth.php` — `register_user`, `login_user`, `logout_user` (create).
- `counts.php` — `push_days`, `pull_state`, `user_total`, `today_count` (create).
- `charts.php` — `chart_data` (create).
- `api.php` — thin JSON router mapping `?action=` to the above (create).
- `util.js` — pure client helpers: `formatNumber`, `deriveStats`, `crossedMala`, `shouldSyncOnMala` (create).
- `index.php` — Alpine app: auth screens (Tailwind), counter (existing CSS), history tab (Chart.js), sync engine, i18n (create; supersedes `naam-jaap-app.html`).
- `i18n.js` — `{en:{...}, hi:{...}}` dictionary (create).
- `manifest.json`, `sw.js` — PWA (create).
- `tests/php/*.php`, `tests/js/*.mjs` — assert-based tests (create).
- `naam-jaap-app.html` — kept as the CSS/markup reference for the counter; not served.

---

## Task 1: Database schema + connection

**Files:**
- Create: `schema.sql`, `db.php`
- Test: `tests/php/test_db.php`

**Interfaces:**
- Produces: `db(): PDO` (throws on failure, `PDO::ERRMODE_EXCEPTION`), `new_token(): string` (64 lowercase hex chars), `user_id_for_token(PDO $db, string $token): ?int`.

- [ ] **Step 1: Write `schema.sql`**

```sql
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  session_token CHAR(64) NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mantras (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);
INSERT INTO mantras (id, name) SELECT 1, 'Default'
  WHERE NOT EXISTS (SELECT 1 FROM mantras WHERE id = 1);

CREATE TABLE IF NOT EXISTS daily_counts (
  user_id   INT NOT NULL,
  mantra_id INT NOT NULL DEFAULT 1,
  day       DATE NOT NULL,
  count     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, mantra_id, day),
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (mantra_id) REFERENCES mantras(id)
);
```

- [ ] **Step 2: Write `db.php`**

```php
<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = getenv('NJ_DB_HOST') ?: '127.0.0.1';
    $name = getenv('NJ_DB_NAME') ?: 'naam_jaap';
    $user = getenv('NJ_DB_USER') ?: 'root';
    $pass = getenv('NJ_DB_PASS') ?: '';
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function new_token(): string { return bin2hex(random_bytes(32)); }
function user_id_for_token(PDO $db, string $token): ?int {
    if ($token === '') return null;
    $s = $db->prepare('SELECT id FROM users WHERE session_token = ?');
    $s->execute([$token]);
    $id = $s->fetchColumn();
    return $id === false ? null : (int)$id;
}
```

- [ ] **Step 3: Write the failing test `tests/php/test_db.php`**

```php
<?php
require __DIR__ . '/../../db.php';
$db = db();                                   // connects to $NJ_DB_NAME
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
assert(strlen(new_token()) === 64);
assert(user_id_for_token($db, 'nope') === null);
$db->exec("INSERT INTO users (username,password_hash,session_token) VALUES ('t','x','abc')");
$id = user_id_for_token($db, 'abc');
assert(is_int($id) && $id > 0);
$db->exec("DELETE FROM users WHERE username='t'");
echo "test_db OK\n";
```

- [ ] **Step 4: Run against a fresh test DB, expect failure first (no DB yet)**

Run:
```bash
mysql -uroot -e "DROP DATABASE IF EXISTS naam_jaap_test; CREATE DATABASE naam_jaap_test;"
NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_db.php
```
Expected first run before `db.php`/`schema.sql` exist: fatal error (file not found / connection). After Steps 1-2: `test_db OK`.

- [ ] **Step 5: Commit**

```bash
git add schema.sql db.php tests/php/test_db.php
git commit -m "feat: db schema + PDO connection + token helpers"
```

---

## Task 2: Auth logic (register / login / logout, single-device)

**Files:**
- Create: `auth.php`
- Test: `tests/php/test_auth.php`

**Interfaces:**
- Consumes: `db()`, `new_token()` (Task 1); `user_total`, `today_count` are added in Task 3 — until then `login_user`/`register_user` return `total`/`today` as `0` via local inline queries defined here.
- Produces:
  - `register_user(PDO $db, string $u, string $p): array` → on success `['ok'=>true,'token'=>string,'total'=>int,'today'=>int]`; on taken username `['ok'=>false,'error'=>'username_taken']`; on bad input `['ok'=>false,'error'=>'invalid']`.
  - `login_user(PDO $db, string $u, string $p, bool $force): array` → bad creds `['ok'=>false,'error'=>'bad_credentials']`; device already active and `!$force` → `['ok'=>false,'device_active'=>true]`; success `['ok'=>true,'token'=>string,'total'=>int,'today'=>int]`.
  - `logout_user(PDO $db, string $token): void`.

- [ ] **Step 1: Write the failing test `tests/php/test_auth.php`**

```php
<?php
require __DIR__ . '/../../db.php';
require __DIR__ . '/../../auth.php';
$db = db();
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
$db->exec("DELETE FROM users WHERE username='alice'");

$r = register_user($db, 'alice', 'pw123456');
assert($r['ok'] === true && strlen($r['token']) === 64);
assert($r['total'] === 0 && $r['today'] === 0);

// duplicate username
$r2 = register_user($db, 'alice', 'other');
assert($r2['ok'] === false && $r2['error'] === 'username_taken');

// bad login
$bad = login_user($db, 'alice', 'wrong', false);
assert($bad['ok'] === false && $bad['error'] === 'bad_credentials');

// login while a device is active -> device_active, no force
$active = login_user($db, 'alice', 'pw123456', false);
assert($active['ok'] === false && ($active['device_active'] ?? false) === true);

// forced login rotates token, invalidating the old one
$old = $r['token'];
$forced = login_user($db, 'alice', 'pw123456', true);
assert($forced['ok'] === true && $forced['token'] !== $old);
assert(user_id_for_token($db, $old) === null);            // old device displaced
assert(user_id_for_token($db, $forced['token']) !== null);

logout_user($db, $forced['token']);
assert(user_id_for_token($db, $forced['token']) === null);
echo "test_auth OK\n";
```

- [ ] **Step 2: Run, verify it fails**

Run: `NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_auth.php`
Expected: FAIL — `auth.php` / functions not defined.

- [ ] **Step 3: Write `auth.php`**

```php
<?php
require_once __DIR__ . '/db.php';

function register_user(PDO $db, string $u, string $p): array {
    $u = trim($u);
    if (strlen($u) < 1 || strlen($u) > 50 || strlen($p) < 4) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    $exists = $db->prepare('SELECT 1 FROM users WHERE username = ?');
    $exists->execute([$u]);
    if ($exists->fetchColumn()) return ['ok' => false, 'error' => 'username_taken'];
    $token = new_token();
    $ins = $db->prepare('INSERT INTO users (username,password_hash,session_token) VALUES (?,?,?)');
    $ins->execute([$u, password_hash($p, PASSWORD_DEFAULT), $token]);
    return ['ok' => true, 'token' => $token, 'total' => 0, 'today' => 0];
}

function login_user(PDO $db, string $u, string $p, bool $force): array {
    $s = $db->prepare('SELECT id,password_hash,session_token FROM users WHERE username = ?');
    $s->execute([trim($u)]);
    $row = $s->fetch();
    if (!$row || !password_verify($p, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'bad_credentials'];
    }
    if (!empty($row['session_token']) && !$force) {
        return ['ok' => false, 'device_active' => true];
    }
    $token = new_token();
    $db->prepare('UPDATE users SET session_token = ? WHERE id = ?')
       ->execute([$token, $row['id']]);
    $uid = (int)$row['id'];
    return ['ok' => true, 'token' => $token,
            'total' => user_total($db, $uid), 'today' => today_count($db, $uid)];
}

function logout_user(PDO $db, string $token): void {
    $db->prepare('UPDATE users SET session_token = NULL WHERE session_token = ?')
       ->execute([$token]);
}
```

Note: `user_total`/`today_count` come from `counts.php` (Task 3). Add `require_once __DIR__ . '/counts.php';` at the top of `auth.php` as part of Task 3; for this task's test, temporarily define them inline at the bottom of the test file:
```php
function user_total(PDO $d,int $u):int{return 0;} function today_count(PDO $d,int $u):int{return 0;}
```
Remove those two temp lines from the test in Task 3 Step 1.

- [ ] **Step 4: Run, verify it passes**

Run: `NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_auth.php`
Expected: `test_auth OK`.

- [ ] **Step 5: Commit**

```bash
git add auth.php tests/php/test_auth.php
git commit -m "feat: auth with single-device token rotation"
```

---

## Task 3: Counts logic (push monotonic, pull, totals)

**Files:**
- Create: `counts.php`
- Modify: `auth.php` (add `require_once __DIR__ . '/counts.php';` at top; remove temp stubs from `tests/php/test_auth.php`)
- Test: `tests/php/test_counts.php`

**Interfaces:**
- Consumes: `db()` (Task 1).
- Produces:
  - `user_total(PDO $db, int $uid, int $mantra = 1): int` → `SUM(count)`.
  - `today_count(PDO $db, int $uid, int $mantra = 1): int` → today's count or 0.
  - `push_days(PDO $db, int $uid, array $days, int $mantra = 1): int` — `$days` is `['YYYY-MM-DD' => int]`; upserts each with `GREATEST`; skips values <= 0; returns new `user_total`.
  - `pull_state(PDO $db, int $uid, int $recent = 60, int $mantra = 1): array` → `['total'=>int,'today'=>int,'days'=>['YYYY-MM-DD'=>int]]` (last `$recent` days).

- [ ] **Step 1: Write the failing test `tests/php/test_counts.php`**

```php
<?php
require __DIR__ . '/../../db.php';
require __DIR__ . '/../../counts.php';
$db = db();
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
$db->exec("DELETE FROM users WHERE username='bob'");
$db->exec("INSERT INTO users (username,password_hash,session_token) VALUES ('bob','x','tok')");
$uid = (int)$db->query("SELECT id FROM users WHERE username='bob'")->fetchColumn();

assert(user_total($db,$uid) === 0 && today_count($db,$uid) === 0);

$today = date('Y-m-d'); $yest = date('Y-m-d', strtotime('-1 day'));
$total = push_days($db,$uid,[$today=>108, $yest=>50]);
assert($total === 158);
assert(today_count($db,$uid) === 108);

// idempotent replay: same values do not double-count
assert(push_days($db,$uid,[$today=>108]) === 158);

// monotonic: a lower value is ignored (GREATEST)
assert(push_days($db,$uid,[$today=>5]) === 158);
assert(today_count($db,$uid) === 108);

// higher value wins
assert(push_days($db,$uid,[$today=>216]) === 266);

// non-positive skipped, no empty-day row
push_days($db,$uid,['2000-01-01'=>0]);
$cnt = $db->query("SELECT COUNT(*) FROM daily_counts WHERE day='2000-01-01'")->fetchColumn();
assert((int)$cnt === 0);

$state = pull_state($db,$uid);
assert($state['total'] === 266 && $state['today'] === 216);
assert($state['days'][$today] === 216 && $state['days'][$yest] === 50);
echo "test_counts OK\n";
```

- [ ] **Step 2: Run, verify it fails**

Run: `NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_counts.php`
Expected: FAIL — `counts.php` not found.

- [ ] **Step 3: Write `counts.php`**

```php
<?php
require_once __DIR__ . '/db.php';

function user_total(PDO $db, int $uid, int $mantra = 1): int {
    $s = $db->prepare('SELECT COALESCE(SUM(count),0) FROM daily_counts WHERE user_id=? AND mantra_id=?');
    $s->execute([$uid, $mantra]);
    return (int)$s->fetchColumn();
}
function today_count(PDO $db, int $uid, int $mantra = 1): int {
    $s = $db->prepare('SELECT count FROM daily_counts WHERE user_id=? AND mantra_id=? AND day=CURDATE()');
    $s->execute([$uid, $mantra]);
    $c = $s->fetchColumn();
    return $c === false ? 0 : (int)$c;
}
function push_days(PDO $db, int $uid, array $days, int $mantra = 1): int {
    $sql = 'INSERT INTO daily_counts (user_id,mantra_id,day,count) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE count = GREATEST(count, VALUES(count))';
    $stmt = $db->prepare($sql);
    foreach ($days as $day => $count) {
        $count = (int)$count;
        if ($count <= 0) continue;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$day)) continue;
        $stmt->execute([$uid, $mantra, $day, $count]);
    }
    return user_total($db, $uid, $mantra);
}
function pull_state(PDO $db, int $uid, int $recent = 60, int $mantra = 1): array {
    $s = $db->prepare('SELECT day, count FROM daily_counts
                       WHERE user_id=? AND mantra_id=? AND day >= (CURDATE() - INTERVAL ? DAY)
                       ORDER BY day');
    $s->execute([$uid, $mantra, $recent]);
    $days = [];
    foreach ($s as $r) $days[$r['day']] = (int)$r['count'];
    return ['total' => user_total($db,$uid,$mantra), 'today' => today_count($db,$uid,$mantra), 'days' => $days];
}
```

- [ ] **Step 4: Wire into auth + fix its test**

Add to the very top of `auth.php` (after `require_once db.php`): `require_once __DIR__ . '/counts.php';`
Delete the two temp stub lines added to `tests/php/test_auth.php` in Task 2 Step 3.

- [ ] **Step 5: Run both suites, verify pass**

Run:
```bash
NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_counts.php
NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_auth.php
```
Expected: `test_counts OK` and `test_auth OK`.

- [ ] **Step 6: Commit**

```bash
git add counts.php auth.php tests/php/test_counts.php tests/php/test_auth.php
git commit -m "feat: monotonic per-day push, pull, totals"
```

---

## Task 4: API router + token gate + displaced-device rejection

**Files:**
- Create: `api.php`
- Test: `tests/php/test_api.php`

**Interfaces:**
- Consumes: everything from Tasks 1-3.
- Produces HTTP JSON endpoints via `?action=`: `register`, `login`, `logout`, `push`, `pull`, `chart` (chart body filled in Task 5). Helper `require_user(PDO,$token): int` used by push/pull/chart — returns uid or emits 409 + exits.

- [ ] **Step 1: Write the failing test `tests/php/test_api.php`**

This test calls the router in-process by setting superglobals and capturing output. `api.php` must read `$_GET['action']`, JSON body from `php://input` (override-able via `$GLOBALS['__test_input']` for tests), and echo JSON.

```php
<?php
$db = (require __DIR__ . '/../../db.php') ?? null;   // ensure db.php loaded
require __DIR__ . '/../../db.php';
db()->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
db()->exec("DELETE FROM users WHERE username IN ('carol')");

function call(string $action, array $body): array {
    $_GET['action'] = $action;
    $GLOBALS['__test_input'] = json_encode($body);
    ob_start(); include __DIR__ . '/../../api.php'; $out = ob_get_clean();
    return json_decode($out, true);
}

$reg = call('register', ['username'=>'carol','password'=>'pw123456']);
assert($reg['ok'] === true);
$tok = $reg['token'];

// push then pull round-trips
$today = date('Y-m-d');
$p = call('push', ['token'=>$tok, 'days'=>[$today=>108]]);
assert($p['ok'] === true && $p['total'] === 108);
$pl = call('pull', ['token'=>$tok]);
assert($pl['total'] === 108 && $pl['today'] === 108);

// forced login elsewhere displaces this token
$new = call('login', ['username'=>'carol','password'=>'pw123456','force'=>1]);
assert($new['ok'] === true && $new['token'] !== $tok);

// old (displaced) token is now rejected with 409 and must NOT overwrite data
$rej = call('push', ['token'=>$tok, 'days'=>[$today=>999]]);
assert(($rej['error'] ?? '') === 'stale_token');
$pl2 = call('pull', ['token'=>$new['token']]);
assert($pl2['today'] === 108);   // 999 from displaced device was rejected
echo "test_api OK\n";
```

- [ ] **Step 2: Run, verify it fails**

Run: `NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_api.php`
Expected: FAIL — `api.php` missing.

- [ ] **Step 3: Write `api.php`**

```php
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/counts.php';
require_once __DIR__ . '/charts.php';   // created in Task 5

if (!headers_sent()) header('Content-Type: application/json');

function body(): array {
    $raw = $GLOBALS['__test_input'] ?? file_get_contents('php://input');
    $j = json_decode($raw ?: '{}', true);
    return is_array($j) ? $j : [];
}
function out(array $data): void { echo json_encode($data); }
function require_user(PDO $db, array $b): int {
    $uid = user_id_for_token($db, (string)($b['token'] ?? ''));
    if ($uid === null) { http_response_code(409); out(['error'=>'stale_token']); exit; }
    return $uid;
}

$db = db();
$action = $_GET['action'] ?? '';
$b = body();

switch ($action) {
    case 'register':
        out(register_user($db, (string)($b['username']??''), (string)($b['password']??'')));
        break;
    case 'login':
        out(login_user($db, (string)($b['username']??''), (string)($b['password']??''), !empty($b['force'])));
        break;
    case 'logout':
        logout_user($db, (string)($b['token']??'')); out(['ok'=>true]);
        break;
    case 'push':
        $uid = require_user($db, $b);
        $total = push_days($db, $uid, is_array($b['days']??null) ? $b['days'] : []);
        out(['ok'=>true, 'total'=>$total, 'today'=>today_count($db,$uid)]);
        break;
    case 'pull':
        $uid = require_user($db, $b);
        out(pull_state($db, $uid));
        break;
    case 'chart':
        $uid = require_user($db, $b);
        out(chart_data($db, $uid, (string)($_GET['type']??'daily'), (int)($_GET['cursor']??0)));
        break;
    default:
        http_response_code(400); out(['error'=>'unknown_action']);
}
```

Note: `api.php` calls exit inside `require_user`, which would abort the test process. For testability, guard the 409 path: replace `exit;` with `return -1;` is NOT acceptable (real HTTP needs to stop). Instead, in the test harness treat the include as the unit and only assert on the echoed JSON — the `exit` ends the included script cleanly and `ob_get_clean()` still captures output because `exit` flushes buffers. Verified: PHP flushes and closes output buffers on `exit`, so `call()` returns the 409 JSON. (If your PHP build does not flush on exit, wrap each `call()` body in a separate `register_shutdown_function` — not needed on PHP 8.)

- [ ] **Step 4: Create a temporary `charts.php` stub so the require resolves**

```php
<?php
function chart_data(PDO $db, int $uid, string $type, int $cursor): array {
    return ['labels'=>[], 'values'=>[]];   // real impl in Task 5
}
```

- [ ] **Step 5: Run, verify it passes**

Run: `NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_api.php`
Expected: `test_api OK`.

- [ ] **Step 6: Commit**

```bash
git add api.php charts.php tests/php/test_api.php
git commit -m "feat: JSON API router with token gate + displaced-device 409"
```

---

## Task 5: Chart aggregation (daily 7 / monthly 12 / yearly 10, paged)

**Files:**
- Modify: `charts.php` (replace stub)
- Test: `tests/php/test_charts.php`

**Interfaces:**
- Consumes: `db()`.
- Produces: `chart_data(PDO $db, int $uid, string $type, int $cursor, int $mantra = 1): array` → `['labels'=>string[], 'values'=>int[]]`.
  - `daily`: 7 buckets ending `cursor*7` days before today (cursor 0 = last 7 days incl. today), oldest→newest; labels `YYYY-MM-DD`.
  - `monthly`: 12 month buckets, cursor 0 = last 12 months; labels `YYYY-MM`.
  - `yearly`: 10 year buckets, cursor 0 = last 10 years; labels `YYYY`.
  - Missing periods → value 0. Cursor past history → all zeros, no error.

- [ ] **Step 1: Write the failing test `tests/php/test_charts.php`**

```php
<?php
require __DIR__ . '/../../db.php';
require __DIR__ . '/../../charts.php';
require __DIR__ . '/../../counts.php';
$db = db();
$db->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
$db->exec("DELETE FROM users WHERE username='dan'");
$db->exec("INSERT INTO users (username,password_hash,session_token) VALUES ('dan','x','dtok')");
$uid = (int)$db->query("SELECT id FROM users WHERE username='dan'")->fetchColumn();

$today = date('Y-m-d'); $d3 = date('Y-m-d', strtotime('-3 day'));
push_days($db,$uid,[$today=>10, $d3=>7]);

$daily = chart_data($db,$uid,'daily',0);
assert(count($daily['labels']) === 7 && count($daily['values']) === 7);
assert($daily['labels'][6] === $today);              // newest last
assert((int)$daily['values'][6] === 10);
assert((int)$daily['values'][3] === 7);              // 3 days ago
assert((int)$daily['values'][0] === 0);              // 6 days ago, no data

$monthly = chart_data($db,$uid,'monthly',0);
assert(count($monthly['labels']) === 12);
assert($monthly['labels'][11] === date('Y-m'));
assert((int)$monthly['values'][11] === 17);          // both entries this month

$yearly = chart_data($db,$uid,'yearly',0);
assert(count($yearly['labels']) === 10);
assert($yearly['labels'][9] === date('Y'));
assert((int)$yearly['values'][9] === 17);

// paging past history: all zeros, no error
$old = chart_data($db,$uid,'daily',500);
assert(count($old['values']) === 7 && array_sum($old['values']) === 0);
echo "test_charts OK\n";
```

- [ ] **Step 2: Run, verify it fails**

Run: `NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_charts.php`
Expected: FAIL — stub returns empty arrays.

- [ ] **Step 3: Replace `charts.php` with the real implementation**

```php
<?php
require_once __DIR__ . '/db.php';

function chart_data(PDO $db, int $uid, string $type, int $cursor, int $mantra = 1): array {
    $cursor = max(0, $cursor);
    if ($type === 'monthly') return chart_periods($db,$uid,$mantra,'%Y-%m',12,$cursor,'month');
    if ($type === 'yearly')  return chart_periods($db,$uid,$mantra,'%Y',10,$cursor,'year');
    return chart_periods($db,$uid,$mantra,'%Y-%m-%d',7,$cursor,'day');
}

function chart_periods(PDO $db,int $uid,int $mantra,string $fmt,int $n,int $cursor,string $unit): array {
    // Build the N period labels for this window, oldest -> newest.
    $labels = [];
    $offset = $cursor * $n;                    // how many periods back the window's newest sits
    for ($i = $n - 1; $i >= 0; $i--) {
        $back = $offset + $i;                  // periods before today for this slot
        $labels[] = period_label($fmt, $unit, $back);
    }
    // Sum counts grouped by the same format, then map onto labels (0 if absent).
    $sql = "SELECT DATE_FORMAT(day, ?) AS p, COALESCE(SUM(count),0) AS c
            FROM daily_counts WHERE user_id=? AND mantra_id=? GROUP BY p";
    $s = $db->prepare($sql); $s->execute([$fmt,$uid,$mantra]);
    $byPeriod = [];
    foreach ($s as $r) $byPeriod[$r['p']] = (int)$r['c'];
    $values = array_map(fn($l) => $byPeriod[$l] ?? 0, $labels);
    return ['labels'=>$labels, 'values'=>$values];
}

function period_label(string $fmt,string $unit,int $back): string {
    $ts = strtotime("-$back $unit");
    if ($fmt === '%Y-%m-%d') return date('Y-m-d',$ts);
    if ($fmt === '%Y-%m')    return date('Y-m',  strtotime("first day of -$back month"));
    return date('Y', strtotime("-$back year"));
}
```

- [ ] **Step 4: Run, verify it passes**

Run: `NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_charts.php`
Expected: `test_charts OK`.

- [ ] **Step 5: Commit**

```bash
git add charts.php tests/php/test_charts.php
git commit -m "feat: chart aggregation daily/monthly/yearly with paging"
```

---

## Task 6: Client pure helpers (format, derive, mala/sync gate)

**Files:**
- Create: `util.js`
- Test: `tests/js/test_util.mjs`

**Interfaces:**
- Produces (ES module exports, also attached to `window` for the inline app):
  - `formatNumber(n: number, useK: boolean): string` — comma groups (`Intl.NumberFormat('en-US')`), or K-notation (`1.2K`, one decimal, drop trailing `.0`) when `useK`.
  - `deriveStats(total: number): {total:number, malas:number, current:number}` — `malas=floor(total/108)`, `current=total%108`.
  - `crossedMala(prev: number, next: number): boolean` — true if `floor(next/108) > floor(prev/108)`.
  - `shouldSyncOnMala(prev: number, next: number, msSinceLastSync: number): boolean` — `crossedMala(prev,next) && msSinceLastSync >= 100000`.

- [ ] **Step 1: Write the failing test `tests/js/test_util.mjs`**

```js
import assert from 'node:assert';
import { formatNumber, deriveStats, crossedMala, shouldSyncOnMala } from '../../util.js';

assert.equal(formatNumber(1234, false), '1,234');
assert.equal(formatNumber(1200, true), '1.2K');
assert.equal(formatNumber(2000, true), '2K');       // drop .0
assert.equal(formatNumber(999, true), '999');       // below 1000, no K

assert.deepEqual(deriveStats(0), {total:0, malas:0, current:0});
assert.deepEqual(deriveStats(108), {total:108, malas:1, current:0});
assert.deepEqual(deriveStats(250), {total:250, malas:2, current:34});

assert.equal(crossedMala(107, 108), true);
assert.equal(crossedMala(108, 109), false);
assert.equal(crossedMala(50, 60), false);

assert.equal(shouldSyncOnMala(107, 108, 120000), true);   // crossed + >100s
assert.equal(shouldSyncOnMala(107, 108, 50000), false);   // crossed but <100s -> defer
assert.equal(shouldSyncOnMala(60, 70, 999999), false);    // no cross
console.log('test_util OK');
```

- [ ] **Step 2: Run, verify it fails**

Run: `node tests/js/test_util.mjs`
Expected: FAIL — `util.js` not found.

- [ ] **Step 3: Write `util.js`**

```js
export function formatNumber(n, useK) {
  n = Number(n) || 0;
  if (useK && Math.abs(n) >= 1000) {
    const v = (n / 1000).toFixed(1).replace(/\.0$/, '');
    return v + 'K';
  }
  return new Intl.NumberFormat('en-US').format(n);
}
export function deriveStats(total) {
  total = Number(total) || 0;
  return { total, malas: Math.floor(total / 108), current: total % 108 };
}
export function crossedMala(prev, next) {
  return Math.floor(next / 108) > Math.floor(prev / 108);
}
export function shouldSyncOnMala(prev, next, msSinceLastSync) {
  return crossedMala(prev, next) && msSinceLastSync >= 100000;
}
// Browser global fallback (index.php loads this as a classic script too)
if (typeof window !== 'undefined') {
  window.NJUtil = { formatNumber, deriveStats, crossedMala, shouldSyncOnMala };
}
```

- [ ] **Step 4: Run, verify it passes**

Run: `node tests/js/test_util.mjs`
Expected: `test_util OK`.

- [ ] **Step 5: Commit**

```bash
git add util.js tests/js/test_util.mjs
git commit -m "feat: client pure helpers (format, derive, mala/sync gate)"
```

---

## Task 7: i18n dictionary

**Files:**
- Create: `i18n.js`
- Test: `tests/js/test_i18n.mjs`

**Interfaces:**
- Produces: default export `I18N = { en: {...}, hi: {...} }`; every key present in `en` must exist in `hi`. Keys (at least): `appTitle, tapToCount, totalJaap, totalMalas, addPhysical, addCount, resetToday, resetConfirm, login, register, username, password, logout, history, daily, monthly, yearly, refresh, loggedElsewhere, notSynced, deviceActiveConfirm`.

- [ ] **Step 1: Write the failing test `tests/js/test_i18n.mjs`**

```js
import assert from 'node:assert';
import I18N from '../../i18n.js';
const required = ['appTitle','tapToCount','totalJaap','totalMalas','addPhysical','addCount',
 'resetToday','resetConfirm','login','register','username','password','logout','history',
 'daily','monthly','yearly','refresh','loggedElsewhere','notSynced','deviceActiveConfirm'];
for (const k of required) {
  assert.ok(I18N.en[k], 'missing en.' + k);
  assert.ok(I18N.hi[k], 'missing hi.' + k);
}
assert.deepEqual(Object.keys(I18N.en).sort(), Object.keys(I18N.hi).sort());
console.log('test_i18n OK');
```

- [ ] **Step 2: Run, verify it fails**

Run: `node tests/js/test_i18n.mjs`
Expected: FAIL — file missing.

- [ ] **Step 3: Write `i18n.js`**

```js
const I18N = {
  en: {
    appTitle:'Naam Jaap Counter', tapToCount:'Tap to Count', totalJaap:'Total Jaap',
    totalMalas:'Total Malas', addPhysical:'Add Physical Counter/Mala Counts', addCount:'Add Count',
    resetToday:"Reset Today's Count", resetConfirm:"Reset today's count to zero? This cannot be undone.",
    login:'Login', register:'Register', username:'Username', password:'Password', logout:'Logout',
    history:'History', daily:'Daily', monthly:'Monthly', yearly:'Yearly', refresh:'Refresh',
    loggedElsewhere:'You were logged out because you signed in on another device.',
    notSynced:'Not synced', deviceActiveConfirm:'Logging in here will log you out from your other device. Continue?'
  },
  hi: {
    appTitle:'नाम जाप काउंटर', tapToCount:'गिनने के लिए टैप करें', totalJaap:'कुल जाप',
    totalMalas:'कुल माला', addPhysical:'भौतिक काउंटर/माला गिनती जोड़ें', addCount:'गिनती जोड़ें',
    resetToday:'आज की गिनती रीसेट करें', resetConfirm:'आज की गिनती शून्य कर दें? यह पूर्ववत नहीं होगा।',
    login:'लॉगिन', register:'रजिस्टर', username:'उपयोगकर्ता नाम', password:'पासवर्ड', logout:'लॉगआउट',
    history:'इतिहास', daily:'दैनिक', monthly:'मासिक', yearly:'वार्षिक', refresh:'रिफ्रेश',
    loggedElsewhere:'आप लॉगआउट हो गए क्योंकि आपने दूसरे डिवाइस पर साइन इन किया।',
    notSynced:'सिंक नहीं हुआ', deviceActiveConfirm:'यहाँ लॉगिन करने पर आप अपने दूसरे डिवाइस से लॉगआउट हो जाएंगे। जारी रखें?'
  }
};
export default I18N;
if (typeof window !== 'undefined') window.NJ_I18N = I18N;
```

- [ ] **Step 4: Run, verify it passes**

Run: `node tests/js/test_i18n.mjs`
Expected: `test_i18n OK`.

- [ ] **Step 5: Commit**

```bash
git add i18n.js tests/js/test_i18n.mjs
git commit -m "feat: en/hi i18n dictionary"
```

---

## Task 8: App shell — auth screens + counter (Alpine, hybrid CSS)

**Files:**
- Create: `index.php` (port markup/CSS from `naam-jaap-app.html`)
- Test: manual (browser) — no unit test for DOM wiring; logic it depends on is already unit-tested (Tasks 6-7).

**Interfaces:**
- Consumes: `util.js` (`window.NJUtil`), `i18n.js` (`window.NJ_I18N`), API from Task 4.
- Produces: an Alpine root `x-data="app()"` with state `{view, lang, useK, token, local:{days:{}, dirty:Set}, total, today, current, malas}` and methods `t(key)`, `fmt(n)`, `doRegister()`, `doLogin(force=false)`, `tap()`, `addManual()`, `resetToday()`, `logout()`, `render()`. Sync methods added in Task 9; history in Task 10 — leave clearly marked stubs `sync(){}` and `openHistory(){}` so those tasks fill them.

- [ ] **Step 1: Scaffold `index.php`**

Copy the `<style>` block and the counter markup (stats grid, mala canvas, bead generation script, tap button) from `naam-jaap-app.html` verbatim into `index.php`. Add in `<head>`:
```html
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
<script src="util.js"></script>
<script src="i18n.js"></script>
```
Wrap `<body>` content in `<div x-data="app()" x-init="init()">` and add two top-level regions toggled by `x-show`:
- `x-show="view==='auth'"`: Tailwind-styled card with username/password inputs, Login and Register buttons, a language `<select>` bound to `lang`.
- `x-show="view==='app'"`: the existing counter markup, with values bound to Alpine (`x-text="fmt(total)"`, etc.) instead of the old `getElementById` writes.

- [ ] **Step 2: Write the Alpine controller (inline `<script>` at end of `index.php`)**

```js
function app() {
  return {
    view:'auth', lang: localStorage.getItem('nj_lang')||'en',
    useK: localStorage.getItem('nj_k')==='1',
    token: localStorage.getItem('nj_token')||'',
    username:'', password:'', authError:'',
    local: { days:{}, dirty:{} },   // dirty is a plain object set-of-day
    total:0, today:0, current:0, malas:0, lastSync:0, syncMsg:'',
    t(k){ return (window.NJ_I18N[this.lang]||window.NJ_I18N.en)[k]||k; },
    fmt(n){ return window.NJUtil.formatNumber(n, this.useK); },
    setLang(l){ this.lang=l; localStorage.setItem('nj_lang',l); },
    async init(){
      if (this.token){ await this.pull(); this.view='app'; }
      this.loadLocal(); this.recompute();
    },
    loadLocal(){ try{ this.local = JSON.parse(localStorage.getItem('nj_local'))||{days:{},dirty:{}}; }catch(e){} },
    saveLocal(){ localStorage.setItem('nj_local', JSON.stringify(this.local)); },
    todayKey(){ return new Date().toISOString().slice(0,10); },
    recompute(){
      this.total = Object.values(this.local.days).reduce((a,b)=>a+Number(b),0);
      this.today = Number(this.local.days[this.todayKey()]||0);
      const d = window.NJUtil.deriveStats(this.total);
      this.malas=d.malas; this.current=d.current;
      // paint beads (reuse existing render loop over .bead nodes)
      document.querySelectorAll('.bead').forEach((b,i)=> b.classList.toggle('active', i < this.current));
    },
    async api(action, body, qs=''){
      const r = await fetch('api.php?action='+action+qs, {method:'POST',
        headers:{'Content-Type':'application/json'}, body: JSON.stringify(body||{})});
      return { status:r.status, data: await r.json() };
    },
    async doRegister(){
      const {data} = await this.api('register',{username:this.username,password:this.password});
      if(!data.ok){ this.authError = data.error; return; }
      this.afterLogin(data);
    },
    async doLogin(force){
      const {data} = await this.api('login',{username:this.username,password:this.password,force:force?1:0});
      if(data.device_active){ if(confirm(this.t('deviceActiveConfirm'))) return this.doLogin(true); return; }
      if(!data.ok){ this.authError = data.error; return; }
      this.afterLogin(data);
    },
    afterLogin(data){
      this.token=data.token; localStorage.setItem('nj_token',this.token);
      this.local={days:{},dirty:{}}; this.saveLocal();
      this.view='app'; this.pull();
    },
    async pull(){
      const {status,data} = await this.api('pull',{token:this.token});
      if(status===409){ return this.forceLogout(); }
      this.local.days = data.days||{}; this.local.dirty={}; this.saveLocal(); this.recompute();
    },
    bump(n){
      const k=this.todayKey(); const prev=this.total;
      this.local.days[k] = Number(this.local.days[k]||0) + n;
      this.local.dirty[k]=1; this.saveLocal(); this.recompute();
      if(navigator.vibrate) navigator.vibrate(40);
      this.maybeSyncOnMala(prev, this.total);   // defined in Task 9
    },
    tap(){ this.bump(1); },
    addManual(){ const v=parseInt(this.manualVal); if(!v||v<=0) return; this.manualVal=''; this.bump(v); },
    async resetToday(){
      if(!confirm(this.t('resetConfirm'))) return;
      const k=this.todayKey(); this.local.days[k]=0; this.local.dirty[k]=1;
      this.saveLocal(); this.recompute();
      // server reset of a single day: push cannot lower via GREATEST, so send explicit reset
      await this.api('push',{token:this.token, days:{}});   // no-op push; real per-day reset uses delete
    },
    async logout(){ await this.api('logout',{token:this.token}); this.forceLogout(); },
    forceLogout(){ localStorage.removeItem('nj_token'); this.token=''; this.view='auth'; this.syncMsg=this.t('loggedElsewhere'); },
    toggleK(){ this.useK=!this.useK; localStorage.setItem('nj_k', this.useK?'1':'0'); },
    // filled by later tasks:
    maybeSyncOnMala(){}, sync(){}, openHistory(){}
  };
}
```

Note the reset-today gap: `GREATEST` push can't lower a day, so per-day reset needs a dedicated server path. **Add a `reset_day` action** — do it now as part of this task: in `counts.php` add `reset_day(PDO $db,int $uid,string $day,int $mantra=1): int { $db->prepare('DELETE FROM daily_counts WHERE user_id=? AND mantra_id=? AND day=?')->execute([$uid,$mantra,$day]); return user_total($db,$uid,$mantra); }` and a `case 'reset_day':` in `api.php` calling it with `require_user`. Then in `resetToday()` call `this.api('reset_day',{token:this.token, day:this.todayKey()})` instead of the no-op push, and on success set `this.local.dirty` clear for that day.

- [ ] **Step 3: Add the reset_day test to `tests/php/test_counts.php`**

Append before the final `echo`:
```php
push_days($db,$uid,[$today=>500]);
assert(today_count($db,$uid) === 500);
$t2 = reset_day($db,$uid,$today);
assert(today_count($db,$uid) === 0);
```

- [ ] **Step 4: Run PHP counts test, verify pass**

Run: `NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 tests/php/test_counts.php`
Expected: `test_counts OK`.

- [ ] **Step 5: Manual browser check**

Serve: `php -S localhost:8000` (with `naam_jaap` DB loaded from `schema.sql`). Open `http://localhost:8000/index.php`. Register a user, tap the button (count + beads update), tap past 108 (mala increments), enter a manual number and Add Count, reset today (confirm dialog), toggle language, toggle K format. Confirm all visibly work.

- [ ] **Step 6: Commit**

```bash
git add index.php counts.php api.php tests/php/test_counts.php
git commit -m "feat: app shell with auth screens + counter (Alpine, hybrid CSS) + reset_day"
```

---

## Task 9: Sync engine (timer, mala gate, reconnect, sendBeacon, offline)

**Files:**
- Modify: `index.php` (fill `sync`, `maybeSyncOnMala`, add timers/listeners in `init`)
- Test: `tests/js/test_util.mjs` already covers the gate; add an offline-across-midnight simulation test `tests/js/test_sync.mjs` for the dirty-days push payload builder, extracted as a pure function.

**Interfaces:**
- Produces: pure helper in `util.js` `dirtyPayload(days, dirty): object` → `{day:count}` for every key in `dirty` (value from `days`, 0s excluded). Export + window attach. `index.php` uses it to build the push body.

- [ ] **Step 1: Add failing test `tests/js/test_sync.mjs`**

```js
import assert from 'node:assert';
import { dirtyPayload } from '../../util.js';
const days = {'2026-09-27':40, '2026-09-28':108};
const dirty = {'2026-09-27':1, '2026-09-28':1};      // both dirty (offline across midnight)
assert.deepEqual(dirtyPayload(days, dirty), {'2026-09-27':40, '2026-09-28':108});
assert.deepEqual(dirtyPayload({'x':0}, {'x':1}), {});   // zero excluded
assert.deepEqual(dirtyPayload(days, {}), {});           // nothing dirty
console.log('test_sync OK');
```

- [ ] **Step 2: Run, verify it fails**

Run: `node tests/js/test_sync.mjs`
Expected: FAIL — `dirtyPayload` undefined.

- [ ] **Step 3: Add `dirtyPayload` to `util.js`**

```js
export function dirtyPayload(days, dirty) {
  const out = {};
  for (const k of Object.keys(dirty)) {
    const v = Number(days[k] || 0);
    if (v > 0) out[k] = v;
  }
  return out;
}
```
Add `dirtyPayload` to both the export list usage and the `window.NJUtil` object.

- [ ] **Step 4: Run, verify it passes**

Run: `node tests/js/test_sync.mjs`
Expected: `test_sync OK`.

- [ ] **Step 5: Implement sync in `index.php`**

Replace the stub methods and extend `init()`:
```js
async sync(){
  if(!navigator.onLine || !this.token) return;
  const payload = window.NJUtil.dirtyPayload(this.local.days, this.local.dirty);
  if(Object.keys(payload).length===0) return;
  const {status,data} = await this.api('push',{token:this.token, days:payload});
  if(status===409){ return this.forceLogout(); }
  if(data && data.ok){ this.local.dirty={}; this.saveLocal(); this.lastSync=Date.now(); this.syncMsg=''; }
  else { this.syncMsg=this.t('notSynced'); }
},
maybeSyncOnMala(prev, next){
  if(window.NJUtil.shouldSyncOnMala(prev, next, Date.now()-this.lastSync)) this.sync();
},
```
In `init()` add:
```js
setInterval(()=>this.sync(), 5*60*1000);            // 5-min timer
window.addEventListener('online', ()=>this.sync()); // reconnect
document.addEventListener('visibilitychange', ()=>{
  if(document.visibilityState==='hidden'){
    const p = window.NJUtil.dirtyPayload(this.local.days, this.local.dirty);
    if(Object.keys(p).length) navigator.sendBeacon('api.php?action=push',
      new Blob([JSON.stringify({token:this.token, days:p})], {type:'application/json'}));
  }
});
```
Add a Refresh button in the app view calling `refresh()`:
```js
async refresh(){ await this.sync(); await this.pull(); }
```
Add a small `x-show="syncMsg" x-text="syncMsg"` indicator in the header.

- [ ] **Step 6: Manual check (offline sync)**

With `php -S localhost:8000` running and logged in: tap a few times, open DevTools → Network → set Offline, tap more (no errors, count keeps rising), set back Online → within a tap or the 5-min timer the push fires (watch Network for `action=push`). Confirm reload shows the same total. Also confirm rapid tapping past 108 does NOT immediately push (gate <100s), but the 5-min timer / next slow mala does.

- [ ] **Step 7: Commit**

```bash
git add util.js index.php tests/js/test_sync.mjs
git commit -m "feat: offline-aware sync engine (timer, mala gate, reconnect, beacon)"
```

---

## Task 10: History tab with Chart.js (daily/monthly/yearly + paging)

**Files:**
- Modify: `index.php` (bottom nav, history view, Chart.js)
- Test: chart aggregation already unit-tested (Task 5); this task is DOM wiring — manual check.

**Interfaces:**
- Consumes: `chart` API (Task 5), Chart.js CDN.
- Produces: history methods on the Alpine root: `openHistory()`, `setChartType(type)`, `pageChart(delta)`, `drawChart()`; state `chartType:'daily', chartCursor:0, chart:null`.

- [ ] **Step 1: Add Chart.js + bottom nav + history view to `index.php`**

In `<head>`: `<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>`.
Add a bottom nav bar (two tabs: counter / history) toggling `subview` (`'counter'|'history'`) within the app view. History view contains:
```html
<div class="chart-controls">
  <button @click="pageChart(-1)">‹</button>
  <span x-text="chartType"></span>
  <button @click="pageChart(1)" :disabled="chartCursor===0">›</button>
</div>
<div><button @click="setChartType('daily')" x-text="t('daily')"></button>
     <button @click="setChartType('monthly')" x-text="t('monthly')"></button>
     <button @click="setChartType('yearly')" x-text="t('yearly')"></button></div>
<canvas id="njChart" height="220"></canvas>
```

- [ ] **Step 2: Implement history methods**

```js
subview:'counter', chartType:'daily', chartCursor:0, chart:null,
openHistory(){ this.subview='history'; this.chartCursor=0; this.drawChart(); },
setChartType(t){ this.chartType=t; this.chartCursor=0; this.drawChart(); },
pageChart(d){ // d=-1 older, +1 newer; cursor 0 is newest window
  this.chartCursor = Math.max(0, this.chartCursor - d); this.drawChart();
},
async drawChart(){
  const {status,data} = await this.api('chart',{token:this.token},
     '&type='+this.chartType+'&cursor='+this.chartCursor);
  if(status===409) return this.forceLogout();
  const ctx = document.getElementById('njChart');
  if(this.chart) this.chart.destroy();
  this.chart = new Chart(ctx, { type:'bar',
    data:{ labels:data.labels, datasets:[{ label:this.t(this.chartType), data:data.values,
            backgroundColor:'#f97316' }] },
    options:{ plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} } });
},
```
Note paging direction: `pageChart(-1)` (older) increases cursor, `pageChart(1)` (newer) decreases toward 0; the `‹` button pages older, `›` newer, and `›` is disabled at cursor 0.

- [ ] **Step 3: Manual check**

Logged in with some history (tap on a few different days if possible, or insert test rows): open History tab, confirm daily shows 7 bars ending today; switch to monthly (12 bars) and yearly (10 bars); press `‹` to page older (bars shift, data changes or goes to zero), `›` disabled at newest. Toggle language — axis dataset label follows.

- [ ] **Step 4: Commit**

```bash
git add index.php
git commit -m "feat: history tab with Chart.js daily/monthly/yearly + paging"
```

---

## Task 11: PWA — manifest + service worker (offline shell + Refresh)

**Files:**
- Create: `manifest.json`, `sw.js`
- Modify: `index.php` (link manifest, register SW)
- Test: manual (Application tab).

**Interfaces:**
- Produces: cached app shell so the counter loads offline; API calls are network-only (never cached, so counts are never stale-served).

- [ ] **Step 1: Write `manifest.json`**

```json
{
  "name": "Naam Jaap Counter",
  "short_name": "Naam Jaap",
  "start_url": "index.php",
  "display": "standalone",
  "background_color": "#f9f6f0",
  "theme_color": "#ea580c",
  "icons": [
    { "src": "icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```
(Provide two orange 🙏 PNG icons at 192/512, or a placeholder solid-color PNG generated with any tool — note: icons are required for installability.)

- [ ] **Step 2: Write `sw.js`**

```js
const CACHE = 'nj-v1';
const SHELL = ['index.php','util.js','i18n.js',
  'https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js@4',
  'https://cdn.tailwindcss.com'];
self.addEventListener('install', e =>
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(()=>self.skipWaiting())));
self.addEventListener('activate', e =>
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k=>k!==CACHE).map(k=>caches.delete(k))))));
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (url.pathname.endsWith('api.php')) return;          // never cache API
  e.respondWith(caches.match(e.request).then(r => r || fetch(e.request)));
});
```

- [ ] **Step 3: Register SW + link manifest in `index.php` `<head>`**

```html
<link rel="manifest" href="manifest.json">
<script>if('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');</script>
```

- [ ] **Step 4: Manual check**

Serve over `localhost` (SW works on localhost without HTTPS). Load once online, then DevTools → Application → Service Workers (confirm activated), set Offline, reload — the counter shell still loads and taps still accumulate locally. Application → Manifest shows installable. Confirm `api.php` requests are NOT served from cache (Network shows them failing while offline, succeeding online).

- [ ] **Step 5: Commit**

```bash
git add manifest.json sw.js index.php
git commit -m "feat: PWA shell caching + install manifest"
```

---

## Final verification (run all tests)

- [ ] **Step 1: Reset test DB and run every suite**

```bash
mysql -uroot -e "DROP DATABASE IF EXISTS naam_jaap_test; CREATE DATABASE naam_jaap_test;"
for f in tests/php/*.php; do NJ_DB_NAME=naam_jaap_test php -d zend.assertions=1 "$f" || exit 1; done
for f in tests/js/*.mjs; do node "$f" || exit 1; done
```
Expected: every suite prints `... OK`.

- [ ] **Step 2: Full manual smoke on `php -S localhost:8000`** (production `naam_jaap` DB): register → tap past 108 → offline taps → reconnect sync → second-device forced login displaces first (first shows "logged out" message) → history charts page → language + K toggles → PWA offline load.

- [ ] **Step 3: Commit any fixes; branch ready for review.**
