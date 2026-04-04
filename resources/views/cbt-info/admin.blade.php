<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Informasi CBT</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --panel: #ffffff;
            --ink: #1f2937;
            --muted: #6b7280;
            --line: #dbe3ef;
            --primary: #0f766e;
            --primary-soft: #e6fffb;
            --danger: #b91c1c;
            --danger-soft: #fef2f2;
            --ok: #047857;
            --ok-soft: #ecfdf5;
            --warn: #92400e;
            --warn-soft: #fffbeb;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background:
                radial-gradient(circle at 8% 12%, #e0f2fe 0%, transparent 35%),
                radial-gradient(circle at 92% 4%, #dcfce7 0%, transparent 30%),
                var(--bg);
            color: var(--ink);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 18px;
        }

        .shell {
            width: min(1180px, 100%);
            margin: 0 auto;
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        .sidebar {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px;
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.06);
            position: sticky;
            top: 18px;
        }

        .brand {
            border-bottom: 1px solid var(--line);
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .brand h1 {
            margin: 0;
            font-size: 1.05rem;
        }

        .brand p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .admin-meta {
            margin-top: 8px;
            font-size: 0.86rem;
            color: var(--muted);
        }

        .menu {
            display: grid;
            gap: 8px;
        }

        .menu-btn {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
            text-align: left;
            padding: 10px 12px;
            cursor: pointer;
            transition: all 150ms ease;
            font-weight: 600;
        }

        .menu-btn small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-weight: 400;
            font-size: 0.79rem;
        }

        .menu-btn.active {
            background: var(--primary-soft);
            border-color: #99f6e4;
            color: #115e59;
        }

        .sidebar-actions {
            margin-top: 12px;
            display: grid;
            gap: 8px;
        }

        .content {
            display: grid;
            gap: 12px;
        }

        .banner {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.06);
        }

        .banner h2 {
            margin: 0;
            font-size: clamp(1.1rem, 2vw, 1.45rem);
        }

        .banner p {
            margin: 7px 0 0;
            color: var(--muted);
        }

        .notice {
            margin-top: 10px;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.9rem;
        }

        .notice-ok {
            background: var(--ok-soft);
            color: var(--ok);
        }

        .notice-error {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .panel {
            display: none;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.06);
            animation: fadeIn 180ms ease;
        }

        .panel.active {
            display: block;
        }

        .panel h3 {
            margin: 0;
            font-size: 1.08rem;
        }

        .panel-desc {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .grid {
            margin-top: 12px;
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
            background: #fff;
        }

        .card h4 {
            margin: 0;
            font-size: 0.96rem;
        }

        .card p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .status-pill {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .status-on {
            background: var(--ok-soft);
            color: var(--ok);
        }

        .status-off {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .field {
            margin-top: 10px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.88rem;
            font-weight: 600;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.92rem;
            color: var(--ink);
            background: #fff;
            outline: none;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }

        input:focus,
        textarea:focus {
            border-color: #2dd4bf;
            box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.15);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn-row {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        button,
        .link-btn {
            border: 0;
            border-radius: 10px;
            padding: 10px 12px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }

        .btn-soft {
            background: #f8fafc;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .copy-btn {
            background: #0ea5e9;
            color: #fff;
        }

        .server-grid {
            margin-top: 12px;
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .server-item {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px;
            background: #fff;
        }

        .server-item .name {
            margin: 0;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--muted);
        }

        .server-item .status {
            margin: 6px 0 0;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .status-up { color: var(--ok); }
        .status-down { color: var(--danger); }

        .logout-form {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px dashed var(--line);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 980px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }

            .menu {
                grid-template-columns: 1fr;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .server-grid {
                grid-template-columns: 1fr;
            }

            .btn-row {
                flex-direction: column;
            }

            button,
            .link-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <section class="brand">
                <h1>Panel Admin CBT</h1>
                <p>{{ $info->school }}</p>
                <div class="admin-meta">Login: <strong>{{ $admin->name ?: $admin->username }}</strong></div>
            </section>

            <nav class="menu" aria-label="Menu pengaturan admin">
                <button class="menu-btn active" data-target="panel-token-pin" type="button">
                    Pengaturan Token dan PIN
                    <small>Token CBT, PIN Exambro, status, dan peringatan</small>
                </button>
                <button class="menu-btn" data-target="panel-api" type="button">
                    Pengaturan API
                    <small>API key, endpoint Exambro, dan file konfigurasi</small>
                </button>
                <button class="menu-btn" data-target="panel-web" type="button">
                    Pengaturan WEB
                    <small>Server utama/backup yang tampil di halaman home</small>
                </button>
            </nav>

            <div class="sidebar-actions">
                <a class="link-btn btn-soft" href="{{ route('cbt.index') }}">Lihat Halaman Home</a>
            </div>

            <form class="logout-form" action="{{ route('cbt.admin.logout') }}" method="post">
                @csrf
                <button class="btn-danger" type="submit">Logout Admin</button>
            </form>
        </aside>

        <main class="content">
            <section class="banner">
                <h2>Pengaturan Informasi CBT</h2>
                <p>Kelola token, PIN, API, dan data server website dari satu panel admin.</p>

                @if (session('status'))
                    <div class="notice notice-ok">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="notice notice-error">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section id="panel-token-pin" class="panel active">
                <h3>Pengaturan Token dan PIN</h3>
                <p class="panel-desc">Atur status token Exambro, status PIN, tampilan PIN, peringatan, dan pembaruan Token CBT.</p>

                <div class="grid">
                    <article class="card">
                        <h4>Status Token Exambro</h4>
                        <p>Menentukan apakah token Exambro aktif untuk integrasi aplikasi.</p>
                        <span class="status-pill {{ $exambroActive ? 'status-on' : 'status-off' }}">
                            {{ $exambroActive ? 'AKTIF' : 'NON-AKTIF' }}
                        </span>
                        <form class="btn-row" action="{{ route('cbt.exambro.toggle') }}" method="post">
                            @csrf
                            <button class="{{ $exambroActive ? 'btn-danger' : 'btn-primary' }}" type="submit">
                                {{ $exambroActive ? 'Non-aktifkan Token' : 'Aktifkan Token' }}
                            </button>
                        </form>
                    </article>

                    <article class="card">
                        <h4>Status PIN Exambro</h4>
                        <p>Menentukan apakah PIN Exambro bisa digunakan di aplikasi.</p>
                        <span class="status-pill {{ $exambroPinActive ? 'status-on' : 'status-off' }}">
                            {{ $exambroPinActive ? 'AKTIF' : 'NON-AKTIF' }}
                        </span>
                        <form class="btn-row" action="{{ route('cbt.exambro.pin.toggle') }}" method="post">
                            @csrf
                            <button class="{{ $exambroPinActive ? 'btn-danger' : 'btn-primary' }}" type="submit">
                                {{ $exambroPinActive ? 'Non-aktifkan PIN' : 'Aktifkan PIN' }}
                            </button>
                        </form>
                    </article>

                    <article class="card">
                        <h4>Peringatan Exambro</h4>
                        <p>Saat ON, pesan peringatan tampil di halaman Exambro.</p>
                        <span class="status-pill {{ $exambroWarningValue === 1 ? 'status-on' : 'status-off' }}">
                            {{ $exambroWarningValue === 1 ? 'ON (1)' : 'OFF (0)' }}
                        </span>
                        <form class="btn-row" action="{{ route('cbt.exambro.warning.toggle') }}" method="post">
                            @csrf
                            <button class="{{ $exambroWarningValue === 1 ? 'btn-danger' : 'btn-primary' }}" type="submit">
                                {{ $exambroWarningValue === 1 ? 'OFF-kan Peringatan' : 'ON-kan Peringatan' }}
                            </button>
                        </form>
                    </article>

                    <article class="card">
                        <h4>Tampilan PIN di Halaman Exambro</h4>
                        <p>Hanya mengatur tampilan PIN, tidak mengubah fungsi PIN.</p>
                        <span class="status-pill {{ $exambroTokenVisibleOnPage ? 'status-on' : 'status-off' }}">
                            {{ $exambroTokenVisibleOnPage ? 'TAMPIL' : 'SEMBUNYI' }}
                        </span>
                        <form class="btn-row" action="{{ route('cbt.exambro.token.visibility.toggle') }}" method="post">
                            @csrf
                            <button class="btn-soft" type="submit">
                                {{ $exambroTokenVisibleOnPage ? 'Sembunyikan PIN' : 'Tampilkan PIN' }}
                            </button>
                        </form>
                    </article>
                </div>

                <article class="card" style="margin-top: 12px;">
                    <h4>PIN Exambro Aktif</h4>
                    <p>Sumber PIN: {{ $exambroTokenSource === 'web' ? 'web/server file' : '.env' }}</p>
                    <div class="field">
                        <label for="exambro-token">PIN Exambro</label>
                        <input id="exambro-token" type="text" value="{{ $exambroToken ?: 'Belum ada PIN Exambro' }}" readonly>
                    </div>
                    <div class="btn-row">
                        <form action="{{ route('cbt.exambro.token.generate') }}" method="post" style="width: 100%;">
                            @csrf
                            <button class="btn-primary" type="submit">Generate PIN Exambro</button>
                        </form>
                        <button class="copy-btn" id="copy-exambro-token" type="button">Salin PIN Exambro</button>
                    </div>
                </article>

                <article class="card" style="margin-top: 12px;">
                    <h4>Update Token CBT</h4>
                    <p>Form ini untuk update Token CBT saja. URL server mengikuti data WEB saat ini.</p>
                    <form action="{{ route('cbt.update') }}" method="post">
                        @csrf
                        <div class="field">
                            <label for="token-only">Token CBT</label>
                            <input id="token-only" name="token" type="text" maxlength="6" value="{{ old('token', $info->token) }}" required>
                        </div>
                        <input type="hidden" name="primary_url" value="{{ old('primary_url', $info->cbt_url) }}">
                        <input type="hidden" name="backup_url_1" value="{{ old('backup_url_1', $info->cbt_backup_url_1) }}">
                        <input type="hidden" name="backup_url_2" value="{{ old('backup_url_2', $info->cbt_backup_url_2) }}">
                        <input type="hidden" name="description" value="{{ old('description', $info->description) }}">
                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Simpan Token CBT</button>
                        </div>
                    </form>
                </article>
            </section>

            <section id="panel-api" class="panel">
                <h3>Pengaturan API</h3>
                <p class="panel-desc">Kelola API key Exambro, endpoint siap pakai, dan unduhan konfigurasi aplikasi.</p>

                <article class="card">
                    <h4>API Key Aktif</h4>
                    <p>Sumber: {{ $exambroApiKeySource === 'generated' ? 'generated via panel admin' : '.env' }}</p>
                    <div class="field">
                        <label for="exambro-current-key">API Key</label>
                        <input id="exambro-current-key" type="text" value="{{ $exambroApiKey ?: 'Belum ada API key' }}" readonly>
                    </div>
                    <div class="btn-row">
                        <form action="{{ route('cbt.exambro.api-key.generate') }}" method="post" style="width: 100%;">
                            @csrf
                            <button class="btn-primary" type="submit">Generate API Key Baru</button>
                        </form>
                        <button class="copy-btn" id="copy-exambro-key" type="button">Salin API Key</button>
                    </div>
                </article>

                <article class="card" style="margin-top: 12px;">
                    <h4>Endpoint Exambro Siap Pakai</h4>
                    <div class="field">
                        <label for="exambro-page-url">Halaman Exambro (dengan key)</label>
                        <input id="exambro-page-url" type="text" value="{{ $exambroPageUrl }}" readonly>
                    </div>
                    <div class="field">
                        <label for="exambro-api-url">Endpoint API Exambro (dengan key)</label>
                        <input id="exambro-api-url" type="text" value="{{ $exambroApiUrl }}" readonly>
                    </div>
                    <div class="field">
                        <label for="exambro-config-download-url">Link Download config.json (Aplikasi)</label>
                        <input id="exambro-config-download-url" type="text" value="{{ $exambroConfigDownloadUrl }}" readonly>
                    </div>
                    <div class="btn-row">
                        <a class="link-btn btn-soft" href="{{ $exambroPageUrl }}" target="_blank" rel="noopener noreferrer">Buka Halaman Exambro</a>
                        <a class="link-btn btn-soft" href="{{ route('cbt.exambro.api-key.download') }}">Download Konfigurasi API Key</a>
                        <a class="link-btn btn-soft" href="{{ $exambroConfigDownloadUrl }}">Download config.json (Aplikasi)</a>
                        <button class="copy-btn" id="copy-exambro-page" type="button">Salin Link Halaman</button>
                        <button class="copy-btn" id="copy-exambro-api" type="button">Salin Link API</button>
                        <button class="copy-btn" id="copy-exambro-config-download" type="button">Salin Link config.json</button>
                    </div>
                </article>
            </section>

            <section id="panel-web" class="panel">
                <h3>Pengaturan WEB</h3>
                <p class="panel-desc">Atur token CBT, URL server utama/backup, dan deskripsi yang ditampilkan di halaman home.</p>

                <article class="card">
                    <h4>Form Server dan Konten Home</h4>
                    <form action="{{ route('cbt.update') }}" method="post">
                        @csrf
                        <div class="field">
                            <label for="token">Token CBT</label>
                            <input id="token" name="token" type="password" value="{{ old('token', $info->token) }}" required>
                        </div>

                        <div class="field">
                            <label for="primary_url">URL Server Utama</label>
                            <input id="primary_url" name="primary_url" type="url" value="{{ old('primary_url', $info->cbt_url) }}" required>
                        </div>

                        <div class="field">
                            <label for="backup_url_1">URL Server Backup 1</label>
                            <input id="backup_url_1" name="backup_url_1" type="url" value="{{ old('backup_url_1', $info->cbt_backup_url_1) }}" required>
                        </div>

                        <div class="field">
                            <label for="backup_url_2">URL Server Backup 2</label>
                            <input id="backup_url_2" name="backup_url_2" type="url" value="{{ old('backup_url_2', $info->cbt_backup_url_2) }}" required>
                        </div>

                        <div class="field">
                            <label for="description">Keterangan Halaman Home</label>
                            <textarea id="description" name="description">{{ old('description', $info->description) }}</textarea>
                        </div>

                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Simpan Pengaturan WEB</button>
                        </div>
                    </form>
                </article>

                <section class="server-grid">
                    @foreach ($servers as $server)
                        <article class="server-item">
                            <p class="name">{{ $server['name'] }}</p>
                            <p class="status {{ $server['status_class'] === 'up' ? 'status-up' : 'status-down' }}">
                                {{ $server['status_label'] }}
                            </p>
                            <p style="margin: 6px 0 0; font-size: 0.82rem; color: var(--muted); word-break: break-all;">{{ $server['url'] }}</p>
                        </article>
                    @endforeach
                </section>
            </section>
        </main>
    </div>

    <script>
        (function () {
            var menuButtons = Array.prototype.slice.call(document.querySelectorAll('.menu-btn'));
            var panels = Array.prototype.slice.call(document.querySelectorAll('.panel'));

            function setActive(targetId) {
                menuButtons.forEach(function (btn) {
                    btn.classList.toggle('active', btn.getAttribute('data-target') === targetId);
                });

                panels.forEach(function (panel) {
                    panel.classList.toggle('active', panel.id === targetId);
                });

                if (window.location.hash !== '#' + targetId) {
                    window.location.hash = targetId;
                }
            }

            menuButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setActive(btn.getAttribute('data-target'));
                });
            });

            if (window.location.hash) {
                var hashedId = window.location.hash.replace('#', '');
                var hasPanel = panels.some(function (panel) { return panel.id === hashedId; });
                if (hasPanel) {
                    setActive(hashedId);
                }
            }

            function copyValue(inputEl, buttonEl, fallbackMessage) {
                if (!inputEl || !buttonEl) {
                    return;
                }

                var value = inputEl.value || '';
                var originalText = buttonEl.textContent;

                if (!value) {
                    alert(fallbackMessage);
                    return;
                }

                navigator.clipboard.writeText(value)
                    .then(function () {
                        buttonEl.textContent = 'Tersalin';
                        setTimeout(function () {
                            buttonEl.textContent = originalText;
                        }, 1200);
                    })
                    .catch(function () {
                        inputEl.focus();
                        inputEl.select();
                        document.execCommand('copy');
                        buttonEl.textContent = 'Tersalin';
                        setTimeout(function () {
                            buttonEl.textContent = originalText;
                        }, 1200);
                    });
            }

            var exambroTokenInput = document.getElementById('exambro-token');
            var keyInput = document.getElementById('exambro-current-key');
            var pageInput = document.getElementById('exambro-page-url');
            var apiInput = document.getElementById('exambro-api-url');
            var configInput = document.getElementById('exambro-config-download-url');

            var copyTokenBtn = document.getElementById('copy-exambro-token');
            var copyKeyBtn = document.getElementById('copy-exambro-key');
            var copyPageBtn = document.getElementById('copy-exambro-page');
            var copyApiBtn = document.getElementById('copy-exambro-api');
            var copyConfigBtn = document.getElementById('copy-exambro-config-download');

            if (copyTokenBtn) {
                copyTokenBtn.addEventListener('click', function () {
                    copyValue(exambroTokenInput, copyTokenBtn, 'PIN Exambro kosong.');
                });
            }

            if (copyKeyBtn) {
                copyKeyBtn.addEventListener('click', function () {
                    copyValue(keyInput, copyKeyBtn, 'API Key kosong.');
                });
            }

            if (copyPageBtn) {
                copyPageBtn.addEventListener('click', function () {
                    copyValue(pageInput, copyPageBtn, 'Link halaman Exambro kosong.');
                });
            }

            if (copyApiBtn) {
                copyApiBtn.addEventListener('click', function () {
                    copyValue(apiInput, copyApiBtn, 'Link API Exambro kosong.');
                });
            }

            if (copyConfigBtn) {
                copyConfigBtn.addEventListener('click', function () {
                    copyValue(configInput, copyConfigBtn, 'Link download config kosong.');
                });
            }
        })();
    </script>
</body>
</html>
