<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ea580c">
    <link rel="manifest" href="manifest.json">
    <title>Naam Jaap Counter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="module" src="util.js"></script>
    <script type="module" src="i18n.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        :root {
            --bg-main: #f9f6f0; --bg-card: #ffffff; --text-main: #2c2520; --text-muted: #786d66;
            --primary: #ea580c; --primary-light: #ffedd5; --bead-off: #e5e0d8; --bead-border: #c4b9ae;
            --bead-on: #f97316; --bead-glow: rgba(249, 115, 22, 0.4); --border: #ebdcd0;
        }
        body.dark-mode {
            --bg-main: #120e0c; --bg-card: #1c1714; --text-main: #f4efe9; --text-muted: #a3968e;
            --primary: #ff7830; --primary-light: #2c1a10; --bead-off: #2c2420; --bead-border: #423730;
            --bead-on: #ff7830; --bead-glow: rgba(255, 120, 48, 0.5); --border: #332924;
        }
        body {
            background-color: var(--bg-main); color: var(--text-main); min-height: 100vh;
            display: flex; flex-direction: column; justify-content: space-between;
            transition: background-color 0.3s, color 0.3s; user-select: none; -webkit-user-select: none;
        }
        header {
            background-color: var(--bg-card); padding: 1rem 1.25rem; display: flex;
            justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.02); gap: 0.5rem;
        }
        .brand { font-size: 1.1rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 0.4rem; }
        .hdr-actions { display: flex; align-items: center; gap: 0.35rem; }
        .icon-btn {
            background: none; border: none; font-size: 1.1rem; cursor: pointer; padding: 0.4rem;
            border-radius: 50%; outline: none; display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); transition: background 0.2s;
        }
        .icon-btn:hover { background-color: rgba(0,0,0,0.05); }
        .sync-flag { font-size: 0.68rem; color: #ef4444; font-weight: 600; }
        main {
            flex: 1; display: flex; flex-direction: column; align-items: center; padding: 1rem;
            max-width: 420px; width: 100%; margin: 0 auto; justify-content: space-around; gap: 1rem;
        }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; width: 100%; }
        .card {
            background-color: var(--bg-card); border: 1px solid var(--border); padding: 0.85rem 1rem;
            border-radius: 1.25rem; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.01);
        }
        .stat-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.25rem; }
        .stat-val-total { font-size: 1.85rem; font-weight: 800; color: var(--primary); }
        .stat-val-mala { font-size: 1.85rem; font-weight: 800; color: #b45309; }
        body.dark-mode .stat-val-mala { color: #f59e0b; }
        .mala-canvas-space { position: relative; width: 320px; height: 320px; display: flex; align-items: center; justify-content: center; margin: 0.75rem 0; }
        .beads-circle-hull { position: absolute; width: 320px; height: 320px; top: 0; left: 0; border-radius: 50%; pointer-events: none; }
        .bead {
            position: absolute; width: 7.5px; height: 7.5px; background-color: var(--bead-off);
            border: 1px solid var(--bead-border); border-radius: 50%; top: 50%; left: 50%;
            margin-top: -3.75px; margin-left: -3.75px; transform-origin: center center;
            transition: background-color 0.15s, transform 0.15s, box-shadow 0.15s;
        }
        .bead.active { background-color: var(--bead-on); border-color: var(--bead-on); transform: scale(1.4); box-shadow: 0 0 6px var(--bead-glow); }
        .tap-btn {
            position: relative; z-index: 10; width: 236px; height: 236px; border-radius: 50%;
            background: linear-gradient(135deg, #f97316, #ea580c); border: 8px solid var(--primary-light);
            color: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center;
            cursor: pointer; outline: none; box-shadow: 0 12px 32px rgba(234, 88, 12, 0.2); transition: transform 0.08s ease;
        }
        body.dark-mode .tap-btn { background: linear-gradient(135deg, #ff7830, #e05300); box-shadow: 0 12px 32px rgba(255, 120, 48, 0.15); }
        .tap-btn:active { transform: scale(0.96); }
        .tap-btn .action-txt { font-size: 0.8rem; font-weight: 700; letter-spacing: 0.1em; opacity: 0.85; text-transform: uppercase; }
        .tap-btn .count-display { font-size: 4.5rem; font-weight: 900; line-height: 1.1; margin: 0.15rem 0; font-variant-numeric: tabular-nums; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .tap-btn .progress-badge { font-size: 0.75rem; background-color: rgba(255, 255, 255, 0.18); padding: 3px 12px; border-radius: 999px; font-weight: 600; backdrop-filter: blur(4px); }
        .sync-box { width: 100%; text-align: left; }
        .sync-box h3 { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.02em; }
        .input-row { display: flex; gap: 0.5rem; }
        input[type="number"], input[type="text"], input[type="password"] {
            flex: 1; padding: 0.75rem 1rem; border-radius: 0.85rem; border: 1px solid var(--border);
            background-color: var(--bg-card); color: var(--text-main); outline: none; font-size: 0.95rem; transition: border-color 0.2s;
        }
        input:focus { border-color: var(--primary); }
        .sync-btn {
            background-color: var(--text-main); color: var(--bg-main); border: none; padding: 0.75rem 1.25rem;
            border-radius: 0.85rem; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: transform 0.1s, opacity 0.2s; outline: none;
        }
        body.dark-mode .sync-btn { background-color: var(--primary); color: #ffffff; }
        .sync-btn:active { transform: scale(0.97); }
        .action-link-bar { width: 100%; text-align: center; }
        .reset-link { font-size: 0.75rem; color: #ef4444; background: none; border: none; font-weight: 600; cursor: pointer; opacity: 0.8; outline: none; transition: opacity 0.2s; }
        .reset-link:hover { opacity: 1; text-decoration: underline; }
        footer { padding: 0.75rem; text-align: center; font-size: 0.72rem; color: var(--text-muted); font-weight: 500; }
        [x-cloak] { display: none !important; }
        /* Bottom navigation */
        .bottom-nav { display: flex; border-top: 1px solid var(--border); background-color: var(--bg-card); }
        .bottom-nav button {
            flex: 1; background: none; border: none; padding: 0.75rem; cursor: pointer;
            font-size: 0.8rem; font-weight: 600; color: var(--text-muted); outline: none;
            display: flex; flex-direction: column; align-items: center; gap: 0.15rem;
        }
        .bottom-nav button.active { color: var(--primary); }
        /* History */
        .history-wrap { width: 100%; max-width: 420px; margin: 0 auto; padding: 1rem; }
        .chart-controls { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
        .chart-controls button {
            background: var(--bg-card); border: 1px solid var(--border); color: var(--text-main);
            width: 2.25rem; height: 2.25rem; border-radius: 0.75rem; font-size: 1.1rem; cursor: pointer; outline: none;
        }
        .chart-controls button:disabled { opacity: 0.35; cursor: default; }
        .chart-range { font-weight: 700; font-size: 0.9rem; }
        .chart-types { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .chart-types button {
            flex: 1; padding: 0.5rem; border-radius: 0.75rem; border: 1px solid var(--border);
            background: var(--bg-card); color: var(--text-muted); font-weight: 600; font-size: 0.8rem; cursor: pointer; outline: none;
        }
        .chart-types button.active { background: var(--primary); color: #fff; border-color: var(--primary); }
    </style>
</head>
<body x-data="app()" x-init="init()" :class="dark ? 'dark-mode' : ''">

    <!-- ===================== AUTH VIEW ===================== -->
    <div x-cloak x-show="view==='auth'" class="min-h-screen w-full flex flex-col items-center justify-center p-6"
         style="background-color: var(--bg-main);">
        <div class="w-full max-w-sm rounded-3xl p-6 shadow-xl" style="background-color: var(--bg-card); border: 1px solid var(--border);">
            <div class="text-2xl font-extrabold mb-1 flex items-center gap-2" style="color: var(--primary);">🙏 <span x-text="t('appTitle')"></span></div>
            <p class="text-sm mb-5" style="color: var(--text-muted);" x-text="authMode==='login' ? t('login') : t('register')"></p>

            <label class="block text-xs font-semibold mb-1" style="color: var(--text-muted);" x-text="t('username')"></label>
            <input type="text" x-model="username" autocomplete="username" class="w-full mb-3" />

            <label class="block text-xs font-semibold mb-1" style="color: var(--text-muted);" x-text="t('password')"></label>
            <input type="password" x-model="password" autocomplete="current-password" class="w-full mb-4"
                   @keydown.enter="authMode==='login' ? doLogin(false) : doRegister()" />

            <p x-show="authError" x-text="authErrMsg()" class="text-sm mb-3" style="color:#ef4444;"></p>

            <button data-testid="auth-submit" class="sync-btn w-full mb-3" style="justify-content:center;"
                    @click="authMode==='login' ? doLogin(false) : doRegister()"
                    x-text="authMode==='login' ? t('login') : t('register')"></button>

            <button data-testid="auth-toggle" class="text-sm w-full" style="color: var(--primary); background:none; border:none; cursor:pointer;"
                    @click="authMode = authMode==='login' ? 'register' : 'login'; authError=''"
                    x-text="authMode==='login' ? t('register') : t('login')"></button>

            <div class="mt-5 flex items-center justify-center gap-3 text-sm">
                <select x-model="lang" @change="setLang(lang)" class="rounded-lg px-2 py-1"
                        style="background-color: var(--bg-card); color: var(--text-main); border:1px solid var(--border);">
                    <option value="en">English</option>
                    <option value="hi">हिन्दी</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ===================== APP VIEW ===================== -->
    <template x-if="view==='app'">
    <div style="display:flex; flex-direction:column; min-height:100vh; width:100%;">
        <header>
            <div class="brand">🙏 <span x-text="t('appTitle')"></span></div>
            <div class="hdr-actions">
                <span class="sync-flag" x-show="syncMsg" x-text="syncMsg"></span>
                <select x-model="lang" @change="setLang(lang)" class="icon-btn" style="font-size:0.8rem;padding:0.25rem;">
                    <option value="en">EN</option>
                    <option value="hi">हि</option>
                </select>
                <button class="icon-btn" @click="toggleK()" :title="'1,000 / 1K'" x-text="useK ? '1K' : '1,0'"></button>
                <button class="icon-btn" data-testid="refresh" @click="refresh()" :title="t('refresh')">⟳</button>
                <button class="icon-btn" @click="toggleTheme()" x-text="dark ? '☀️' : '🌙'"></button>
                <button class="icon-btn" @click="logout()" :title="t('logout')">⎋</button>
            </div>
        </header>

        <main x-show="subview==='counter'">
            <div class="stats-grid">
                <div class="card">
                    <div class="stat-label" x-text="t('totalJaap')"></div>
                    <div class="stat-val-total" data-testid="total" x-text="fmt(total)"></div>
                </div>
                <div class="card">
                    <div class="stat-label" x-text="t('totalMalas')"></div>
                    <div class="stat-val-mala" data-testid="malas" x-text="fmt(malas)"></div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="card">
                    <div class="stat-label" x-text="t('todayJaap')"></div>
                    <div class="stat-val-total" data-testid="today-total" x-text="fmt(today)"></div>
                </div>
                <div class="card">
                    <div class="stat-label" x-text="t('todayMalas')"></div>
                    <div class="stat-val-mala" data-testid="today-malas" x-text="fmt(todayMalas)"></div>
                </div>
            </div>

            <div class="mala-canvas-space">
                <div class="beads-circle-hull" id="beadsCircleHull" x-init="buildBeads($el)"></div>
                <button class="tap-btn" data-testid="tap" @click="tap()">
                    <span class="action-txt" x-text="t('tapToCount')"></span>
                    <span class="count-display" data-testid="current" x-text="current"></span>
                    <span class="progress-badge" x-text="current + ' / 108'"></span>
                </button>
            </div>

            <div class="card sync-box">
                <h3 x-text="t('addPhysical')"></h3>
                <div class="input-row">
                    <input type="number" data-testid="manual" x-model="manualVal" :placeholder="t('addCount')" min="1" inputmode="numeric" />
                    <button class="sync-btn" data-testid="add" @click="addManual()" x-text="t('addCount')"></button>
                </div>
            </div>

            <div class="action-link-bar">
                <button class="reset-link" data-testid="reset" @click="resetToday()" x-text="t('resetToday')"></button>
            </div>
        </main>

        <!-- HISTORY VIEW -->
        <section x-show="subview==='history'" style="flex:1;">
            <div class="history-wrap">
                <div class="chart-types">
                    <button :class="chartType==='weekly' ? 'active' : ''" data-testid="ct-weekly" @click="setChartType('weekly')" x-text="t('weekly')"></button>
                    <button :class="chartType==='monthly' ? 'active' : ''" data-testid="ct-monthly" @click="setChartType('monthly')" x-text="t('monthly')"></button>
                    <button :class="chartType==='yearly' ? 'active' : ''" data-testid="ct-yearly" @click="setChartType('yearly')" x-text="t('yearly')"></button>
                </div>
                <div class="chart-controls">
                    <button data-testid="chart-older" @click="pageChart(-1)">‹</button>
                    <span class="chart-range" data-testid="chart-range" x-text="chartRange"></span>
                    <button data-testid="chart-newer" @click="pageChart(1)" :disabled="chartCursor===0">›</button>
                </div>
                <canvas id="njChart" height="240"></canvas>
            </div>
        </section>

        <nav class="bottom-nav">
            <button :class="subview==='counter' ? 'active' : ''" data-testid="nav-counter" @click="subview='counter'">
                <span>🧮</span><span x-text="t('appTitle').split(' ')[0]"></span>
            </button>
            <button :class="subview==='history' ? 'active' : ''" data-testid="nav-history" @click="openHistory()">
                <span>📊</span><span x-text="t('history')"></span>
            </button>
        </nav>
    </div>
    </template>

    <script>
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');
    // Draw each bar's value above it.
    const barLabels = {
      id: 'barLabels',
      afterDatasetsDraw(chart) {
        const { ctx } = chart;
        chart.getDatasetMeta(0).data.forEach((bar, i) => {
          const v = chart.data.datasets[0].data[i];
          if (!v) return;
          ctx.save();
          ctx.fillStyle = '#333';
          ctx.font = '600 11px sans-serif';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'bottom';
          ctx.fillText(v, bar.x, bar.y - 2);
          ctx.restore();
        });
      }
    };
    function app() {
      return {
        view: 'auth', authMode: 'login', subview: 'counter',
        chartType: 'weekly', chartCursor: 0, chart: null, chartRange: '',
        lang: localStorage.getItem('nj_lang') || 'en',
        useK: localStorage.getItem('nj_k') === '1',
        dark: localStorage.getItem('nj_d') === 'true',
        token: localStorage.getItem('nj_token') || '',
        username: '', password: '', authError: '',
        manualVal: '',
        local: { days: {}, dirty: {} },
        total: 0, today: 0, todayMalas: 0, current: 0, malas: 0,
        lastSync: 0, syncMsg: '', _syncStarted: false,

        t(k) { return (window.NJ_I18N[this.lang] || window.NJ_I18N.en)[k] || k; },
        fmt(n) { return window.NJUtil.formatNumber(n, this.useK); },
        authErrMsg() {
          const m = { bad_credentials: 'Invalid username or password.',
                      username_taken: 'That username is already taken.',
                      invalid: 'Username required and password must be at least 4 characters.' };
          return m[this.authError] || this.authError;
        },
        setLang(l) { this.lang = l; localStorage.setItem('nj_lang', l); },
        toggleK() { this.useK = !this.useK; localStorage.setItem('nj_k', this.useK ? '1' : '0'); },
        toggleTheme() { this.dark = !this.dark; localStorage.setItem('nj_d', this.dark); },

        async init() {
          this.loadLocal();
          if (this.token) { this.view = 'app'; this.recompute(); this.pull().catch(() => {}); this.startSync(); }
          else { this.recompute(); }
        },

        todayKey() { return new Date().toISOString().slice(0, 10); },
        loadLocal() { try { this.local = JSON.parse(localStorage.getItem('nj_local')) || { days: {}, dirty: {} }; } catch (e) {} },
        saveLocal() { localStorage.setItem('nj_local', JSON.stringify(this.local)); },

        recompute() {
          this.total = Object.values(this.local.days).reduce((a, b) => a + Number(b), 0);
          this.today = Number(this.local.days[this.todayKey()] || 0);
          this.todayMalas = Math.floor(this.today / 108);
          const d = window.NJUtil.deriveStats(this.total);
          this.malas = d.malas; this.current = d.current;
          const beads = document.querySelectorAll('.bead');
          beads.forEach((b, i) => b.classList.toggle('active', i < this.current));
        },

        async api(action, body, qs = '') {
          try {
            const r = await fetch('api.php?action=' + action + qs, {
              method: 'POST', headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(body || {})
            });
            return { status: r.status, data: await r.json() };
          } catch (e) {
            return { status: 0, data: null };   // offline / network failure
          }
        },

        async doRegister() {
          const { data } = await this.api('register', { username: this.username, password: this.password });
          if (!data) { this.authError = 'Network error.'; return; }
          if (!data.ok) { this.authError = data.error; return; }
          this.afterLogin(data);
        },
        async doLogin(force) {
          const { data } = await this.api('login', { username: this.username, password: this.password, force: force ? 1 : 0 });
          if (!data) { this.authError = 'Network error.'; return; }
          if (data.device_active) { if (confirm(this.t('deviceActiveConfirm'))) return this.doLogin(true); return; }
          if (!data.ok) { this.authError = data.error; return; }
          this.afterLogin(data);
        },
        afterLogin(data) {
          this.token = data.token; localStorage.setItem('nj_token', this.token);
          this.local = { days: {}, dirty: {} }; this.saveLocal();
          this.username = ''; this.password = ''; this.authError = '';
          this.view = 'app'; this.syncMsg = '';
          this.recompute(); this.pull().catch(() => {}); this.startSync();
        },

        async pull() {
          const { status, data } = await this.api('pull', { token: this.token });
          if (status === 409) return this.forceLogout();
          if (status === 0 || !data) return;             // offline: keep local
          this.local.days = data.days || {}; this.local.dirty = {}; this.saveLocal(); this.recompute();
        },

        bump(n) {
          const k = this.todayKey(); const prev = this.total;
          this.local.days[k] = Number(this.local.days[k] || 0) + n;
          this.local.dirty[k] = 1; this.saveLocal(); this.recompute();
          if (navigator.vibrate) navigator.vibrate(40);
          this.maybeSyncOnMala(prev, this.total);
        },
        tap() { this.bump(1); },
        addManual() { const v = parseInt(this.manualVal); if (!v || v <= 0) return; this.manualVal = ''; this.bump(v); },

        async resetToday() {
          if (!confirm(this.t('resetConfirm'))) return;
          const k = this.todayKey();
          this.local.days[k] = 0; delete this.local.dirty[k]; this.saveLocal(); this.recompute();
          await this.api('reset_day', { token: this.token, day: k });
        },

        async logout() { await this.api('logout', { token: this.token }); this.forceLogout(); },
        forceLogout() {
          localStorage.removeItem('nj_token'); this.token = ''; this.view = 'auth';
          this.authMode = 'login'; this.syncMsg = this.t('loggedElsewhere');
        },

        // Build the 108-bead ring into the hull element when the app view renders.
        buildBeads(el) {
          if (el.childElementCount) return;
          const beadCount = 108, radius = 146;
          for (let i = 0; i < beadCount; i++) {
            const bead = document.createElement('div');
            bead.className = 'bead';
            const radians = (i * (2 * Math.PI / beadCount)) - (Math.PI / 2);
            bead.style.left = '50%'; bead.style.top = '50%';
            bead.style.transform = 'translate(' + (Math.cos(radians) * radius) + 'px, ' + (Math.sin(radians) * radius) + 'px)';
            el.appendChild(bead);
          }
          this.recompute();
        },

        // ---- Sync engine ----
        async sync() {
          if (!navigator.onLine || !this.token) return;
          const payload = window.NJUtil.dirtyPayload(this.local.days, this.local.dirty);
          if (Object.keys(payload).length === 0) return;
          const { status, data } = await this.api('push', { token: this.token, days: payload });
          if (status === 409) return this.forceLogout();
          if (status === 0 || !data) { this.syncMsg = this.t('notSynced'); return; }
          if (data.ok) {
            // Clear dirty only for days whose value we actually pushed; taps that
            // arrived during the await leave days[k] > pushed value -> stay dirty.
            for (const k of Object.keys(payload)) {
              if (Number(this.local.days[k] || 0) <= payload[k]) delete this.local.dirty[k];
            }
            this.saveLocal(); this.lastSync = Date.now(); this.syncMsg = '';
          }
        },
        maybeSyncOnMala(prev, next) {
          if (window.NJUtil.shouldSyncOnMala(prev, next, Date.now() - this.lastSync)) this.sync();
        },
        startSync() {
          if (this._syncStarted) return;
          this._syncStarted = true;
          setInterval(() => this.sync(), 5 * 60 * 1000);            // 5-min timer
          window.addEventListener('online', () => this.sync());     // reconnect
          document.addEventListener('visibilitychange', () => {     // flush on hide
            if (document.visibilityState === 'hidden') {
              const p = window.NJUtil.dirtyPayload(this.local.days, this.local.dirty);
              if (Object.keys(p).length && navigator.sendBeacon) {
                navigator.sendBeacon('api.php?action=push',
                  new Blob([JSON.stringify({ token: this.token, days: p })], { type: 'application/json' }));
              }
            }
          });
        },
        async refresh() { await this.sync(); await this.pull(); },

        // ---- History / charts ----
        openHistory() { this.subview = 'history'; this.chartCursor = 0; this.drawChart(); },
        setChartType(t) { this.chartType = t; this.chartCursor = 0; this.drawChart(); },
        pageChart(d) { this.chartCursor = Math.max(0, this.chartCursor - d); this.drawChart(); },
        async drawChart() {
          const { status, data } = await this.api('chart', { token: this.token },
            '&type=' + this.chartType + '&cursor=' + this.chartCursor);
          if (status === 409) return this.forceLogout();
          if (!data || !data.labels) return;
          this.chartRange = data.range || '';
          const ctx = document.getElementById('njChart');
          if (this.chart) this.chart.destroy();
          this.chart = new Chart(ctx, {
            type: 'bar',
            data: { labels: data.labels, datasets: [{ label: this.t(this.chartType), data: data.values, backgroundColor: '#f97316', borderRadius: 6 }] },
            options: { responsive: true, layout: { padding: { top: 18 } }, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            plugins: [barLabels]
          });
        }
      };
    }
    </script>
</body>
</html>
