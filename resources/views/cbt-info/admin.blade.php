<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Informasi CBT</title>
    <style>
        :root {
            --bg: #f8fafc;
            --ink: #0f172a;
            --muted: #475569;
            --line: #cbd5e1;
            --primary: #1d4ed8;
            --success-bg: #ecfdf3;
            --success-text: #047857;
            --error-bg: #fff1f2;
            --error-text: #be123c;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #f8fafc 0%, #eff6ff 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--ink);
            padding: 20px;
        }

        .container {
            width: min(760px, 100%);
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
            animation: fadeIn 350ms ease-out;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.3rem, 2vw, 1.9rem);
        }

        p {
            color: var(--muted);
            margin: 8px 0 0;
        }

        .school-name {
            margin-top: 10px;
            font-size: clamp(1.05rem, 2vw, 1.35rem);
            font-weight: 800;
            color: var(--ink);
            line-height: 1.25;
        }

        .alert {
            margin-top: 14px;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.95rem;
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        form {
            margin-top: 16px;
            display: grid;
            gap: 14px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 140ms ease, box-shadow 140ms ease;
        }

        input:focus,
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .token-tools {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .token-tools input {
            width: auto;
            margin: 0;
            accent-color: var(--primary);
        }

        .actions {
            margin-top: 4px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .server-grid {
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .server-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
        }

        .server-card.up {
            background: var(--success-bg);
            border-color: #a7f3d0;
        }

        .server-card.down {
            background: var(--error-bg);
            border-color: #fecdd3;
        }

        .server-title {
            margin: 0;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .server-status {
            margin: 6px 0 0;
            font-weight: 700;
            font-size: 1rem;
        }

        .server-status.up {
            color: var(--success-text);
        }

        .server-status.down {
            color: var(--error-text);
        }

        .server-url {
            margin: 6px 0 0;
            font-size: 0.85rem;
            color: var(--muted);
            word-break: break-all;
        }

        button,
        .link {
            border: 0;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            border-radius: 10px;
            padding: 11px 14px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        button {
            background: var(--primary);
            color: #fff;
        }

        .link {
            border: 1px solid var(--line);
            color: var(--ink);
            background: #fff;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 12px;
            }

            .container {
                border-radius: 14px;
                padding: 16px;
            }

            .actions {
                flex-direction: column;
            }

            .server-grid {
                grid-template-columns: 1fr;
            }

            button,
            .link {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>Admin Informasi CBT</h1>
        <p class="school-name">{{ $info->school }}</p>
        <p><strong>{{ $info->app_name }}</strong></p>
        <p>Perbarui token, 3 URL CBT, dan keterangan untuk ditampilkan di halaman utama.</p>
        <p>Login sebagai: <strong>{{ $admin->name ?: $admin->username }}</strong></p>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <!-- SECTION 1: TOKEN EXAMBRO STATUS (CONTROL FUNCTIONALITY) -->
        <div style="margin-top: 16px; padding: 18px; border-radius: 12px; border: 2px solid {{ $exambroActive ? '#10a856' : '#d94747' }}; background: {{ $exambroActive ? 'rgba(16, 168, 86, 0.08)' : 'rgba(217, 71, 71, 0.08)' }};">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1; min-width: 240px;">
                    <p style="margin: 0; font-weight: 800; font-size: 1.08rem; color: var(--text); display: flex; align-items: center; gap: 6px;">
                        <span>🔐</span>
                        <span>{{ $exambroActive ? '✓ TOKEN AKTIF' : '✕ TOKEN NON-AKTIF' }}</span>
                    </p>
                    <p style="margin: 8px 0 0; font-size: 0.85rem; color: var(--muted); line-height: 1.5;">
                        Status TOKEN Exambro menentukan apakah PIN Exambro dapat digunakan untuk fungsi exit di aplikasi Exambro. Ketika {{ $exambroActive ? 'AKTIF' : 'NON-AKTIF' }}, PIN {{ $exambroActive ? 'dapat' : 'tidak dapat' }} digunakan.
                    </p>
                    <p style="margin: 6px 0 0; font-size: 0.78rem; color: #9333ea; background: #f3e8ff; padding: 6px 10px; border-radius: 4px; display: inline-block;">
                        Kontrol: FUNGSIONALITAS Token
                    </p>
                </div>
                <form action="{{ route('cbt.exambro.toggle') }}" method="post" style="margin: 0; flex-shrink: 0;">
                    @csrf
                    <button type="submit" style="background: {{ $exambroActive ? '#d94747' : '#10a856' }}; color: #fff; border: 0; cursor: pointer; border-radius: 8px; padding: 12px 20px; font-weight: 700; font-size: 0.9rem; white-space: nowrap; transition: all 300ms;">
                        {{ $exambroActive ? '⊘ NON-AKTIFKAN' : '✓ AKTIFKAN' }}
                    </button>
                </form>
            </div>
        </div>

        <div style="margin-top: 16px; padding: 16px; border-radius: 12px; border: 1px solid var(--line); background: {{ $exambroWarningValue === 1 ? 'var(--success-bg)' : '#f1f5f9' }};">
                    <!-- SECTION 1.5: PIN EXAMBRO STATUS (CONTROL FUNCTIONALITY) -->
                    <div style="margin-top: 16px; padding: 18px; border-radius: 12px; border: 2px solid {{ $exambroPinActive ? '#ec4899' : '#f87171' }}; background: {{ $exambroPinActive ? 'rgba(236, 72, 153, 0.08)' : 'rgba(248, 113, 113, 0.08)' }};">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                            <div style="flex: 1; min-width: 240px;">
                                <p style="margin: 0; font-weight: 800; font-size: 1.08rem; color: var(--text); display: flex; align-items: center; gap: 6px;">
                                    <span>📌</span>
                                    <span>{{ $exambroPinActive ? '✓ PIN AKTIF' : '✕ PIN NON-AKTIF' }}</span>
                                </p>
                                <p style="margin: 8px 0 0; font-size: 0.85rem; color: var(--muted); line-height: 1.5;">
                                    Status PIN Exambro menentukan apakah kode PIN ini dapat digunakan untuk fungsi exit/keluar dari Exambro. Kontrol ini INDEPENDENT dari TOKEN Status. Ketika PIN {{ $exambroPinActive ? 'AKTIF' : 'NON-AKTIF' }}, peserta {{ $exambroPinActive ? 'dapat' : 'tidak dapat' }} menggunakan PIN ini untuk keluar.
                                </p>
                                <p style="margin: 6px 0 0; font-size: 0.78rem; color: #ec4899; background: #fce7f3; padding: 6px 10px; border-radius: 4px; display: inline-block;">
                                    Kontrol: FUNGSIONALITAS PIN (Independent)
                                </p>
                            </div>
                            <form action="{{ route('cbt.exambro.pin.toggle') }}" method="post" style="margin: 0; flex-shrink: 0;">
                                @csrf
                                <button type="submit" style="background: {{ $exambroPinActive ? '#ec4899' : '#10a856' }}; color: #fff; border: 0; cursor: pointer; border-radius: 8px; padding: 12px 20px; font-weight: 700; font-size: 0.9rem; white-space: nowrap; transition: all 300ms;">
                                    {{ $exambroPinActive ? '⊘ NON-AKTIFKAN' : '✓ AKTIFKAN' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <div style="margin-top: 16px; padding: 16px; border-radius: 12px; border: 1px solid var(--line); background: {{ $exambroWarningValue === 1 ? 'var(--success-bg)' : '#f1f5f9' }};">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div>
                    <p style="margin: 0; font-weight: 700; font-size: 1rem; color: {{ $exambroWarningValue === 1 ? 'var(--success-text)' : 'var(--muted)' }};">
                        Aktifkan Peringatan Exambro: {{ $exambroWarningValue === 1 ? 'ON (1)' : 'OFF (0)' }}
                    </p>
                    <p style="margin: 4px 0 0; font-size: 0.88rem; color: var(--muted);">
                        Saat ON (1), pesan peringatan akan ditampilkan di halaman Exambro. Saat OFF (0), pesan peringatan disembunyikan.
                    </p>
                </div>
                <form action="{{ route('cbt.exambro.warning.toggle') }}" method="post" style="margin: 0;">
                    @csrf
                    <button type="submit" style="background: {{ $exambroWarningValue === 1 ? 'var(--error-text)' : 'var(--success-text)' }}; color: #fff; border: 0; cursor: pointer; border-radius: 10px; padding: 10px 18px; font-weight: 600; font-size: 0.95rem;">
                        {{ $exambroWarningValue === 1 ? 'OFF-kan Peringatan' : 'ON-kan Peringatan' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- SECTION 2: PIN EXAMBRO VISIBILITY (CONTROL DISPLAY ONLY) -->
        <div style="margin-top: 16px; padding: 18px; border-radius: 12px; border: 2px solid {{ $exambroTokenVisibleOnPage ? '#0ea5a5' : '#6b7280' }}; background: {{ $exambroTokenVisibleOnPage ? 'rgba(14, 165, 165, 0.08)' : 'rgba(107, 114, 128, 0.06)' }};">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1; min-width: 240px;">
                    <p style="margin: 0; font-weight: 800; font-size: 1.08rem; color: var(--text); display: flex; align-items: center; gap: 6px;">
                        <span>{{ $exambroTokenVisibleOnPage ? '👁️' : '🙈' }}</span>
                        <span>PIN {{ $exambroTokenVisibleOnPage ? 'TERLIHAT' : 'TERSEMBUNYI' }}</span>
                    </p>
                    <p style="margin: 8px 0 0; font-size: 0.85rem; color: var(--muted); line-height: 1.5;">
                        Kontrol TAMPILAN PIN Exambro di halaman Exambro. Ketika {{ $exambroTokenVisibleOnPage ? 'ON (Terlihat)' : 'OFF (Tersembunyi)' }}, kode PIN {{ $exambroTokenVisibleOnPage ? 'DITAMPILKAN' : 'DISEMBUNYIKAN' }} di halaman. DI HALAMAN HOMEPAGE UTAMA tetap ditampilkan.
                    </p>
                    <p style="margin: 6px 0 0; font-size: 0.78rem; color: #0ea5a5; background: #ccfbf1; padding: 6px 10px; border-radius: 4px; display: inline-block;">
                        Kontrol: TAMPILAN PIN (Display Only)
                    </p>
                </div>
                <form action="{{ route('cbt.exambro.token.visibility.toggle') }}" method="post" style="margin: 0; flex-shrink: 0;">
                    @csrf
                    <button type="submit" style="background: {{ $exambroTokenVisibleOnPage ? '#0ea5a5' : '#6b7280' }}; color: #fff; border: 0; cursor: pointer; border-radius: 8px; padding: 12px 20px; font-weight: 700; font-size: 0.9rem; white-space: nowrap; transition: all 300ms;">
                        {{ $exambroTokenVisibleOnPage ? '🙈 SEMBUNYIKAN PIN' : '👁️ TAMPILKAN PIN' }}
                    </button>
                </form>
            </div>
        </div>

        <section class="server-grid">
            @foreach ($servers as $server)
                <article class="server-card {{ $server['status_class'] }}">
                    <p class="server-title">{{ $server['name'] }}</p>
                    <p class="server-status {{ $server['status_class'] }}">{{ $server['status_label'] }}</p>
                    <p class="server-url">{{ $server['url'] }}</p>
                </article>
            @endforeach
        </section>

        <div style="margin-top: 16px; padding: 16px; border-radius: 12px; border: 1px solid var(--line); background: #f8fafc;">
            <p style="margin: 0; font-weight: 700; font-size: 1rem;">PIN Exambro (Exit PIN)</p>
            <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--muted);">
                PIN Exambro disimpan di sisi web (file server), bukan di tabel database CBT.
            </p>

            <div style="margin-top: 12px; display: grid; gap: 10px;">
                <div>
                    <label for="exambro-token" style="margin-bottom: 6px;">
                        PIN Exambro Aktif
                        <span style="font-weight:400; color: var(--muted); font-size:0.85rem;">
                            (sumber: {{ $exambroTokenSource === 'web' ? 'web/server file' : '.env' }})
                        </span>
                    </label>
                    <input id="exambro-token" type="text" value="{{ $exambroToken ?: 'Belum ada PIN Exambro' }}" readonly>
                </div>

                <form action="{{ route('cbt.exambro.token.generate') }}" method="post" style="margin: 0;">
                    @csrf
                    <button type="submit" style="background:#0f766e;">Generate PIN Exambro</button>
                </form>
            </div>

            <div class="actions" style="margin-top: 10px;">
                <button type="button" id="copy-exambro-token" style="background:#0ea5e9;">Salin PIN Exambro</button>
            </div>
        </div>

        <div style="margin-top: 16px; padding: 16px; border-radius: 12px; border: 1px solid var(--line); background: #f8fafc;">
            <p style="margin: 0; font-weight: 700; font-size: 1rem;">Akses Exambro dari Admin</p>
            <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--muted);">
                Link ini sudah berisi API key. Bisa dibuka langsung dari panel admin atau ditempel ke aplikasi.
            </p>

            @if (empty($exambroApiKey))
                <div class="alert alert-error" style="margin-top: 10px;">
                    API key Exambro belum tersedia. Silakan generate dari tombol di bawah.
                </div>
            @elseif ($exambroApiKeySource === 'env')
                <div class="alert alert-error" style="margin-top: 10px;">
                    Key aktif saat ini berasal dari <strong>.env</strong>, bukan hasil generate panel admin.
                    Klik <strong>Generate API Key Baru</strong> agar config download selalu sinkron.
                </div>
            @else
                <div class="alert alert-success" style="margin-top: 10px;">
                    Key aktif adalah hasil generate panel admin. Config download sudah sinkron dengan server.
                </div>
            @endif

            <div style="margin-top: 12px; display: grid; gap: 10px;">
                <div>
                    <label for="exambro-current-key" style="margin-bottom: 6px;">
                        API Key Aktif
                        @if (!empty($exambroApiKey))
                            <span style="font-weight:400; color: var(--muted); font-size:0.85rem;">
                                (sumber: {{ $exambroApiKeySource === 'generated' ? 'generated via panel admin' : '.env' }})
                            </span>
                        @endif
                    </label>
                    <input id="exambro-current-key" type="text" value="{{ $exambroApiKey ?: 'Belum ada API key' }}" readonly>
                </div>

                <form action="{{ route('cbt.exambro.api-key.generate') }}" method="post" style="margin: 0;">
                    @csrf
                    <button type="submit" style="background:#0369a1;">Generate API Key Baru</button>
                </form>
            </div>

            <div style="margin-top: 12px; display: grid; gap: 10px;">
                <div>
                    <label for="exambro-page-url" style="margin-bottom: 6px;">Halaman Exambro (lengkap key)</label>
                    <input id="exambro-page-url" type="text" value="{{ $exambroPageUrl }}" readonly>
                </div>

                <div>
                    <label for="exambro-api-url" style="margin-bottom: 6px;">Endpoint API Exambro (lengkap key)</label>
                    <input id="exambro-api-url" type="text" value="{{ $exambroApiUrl }}" readonly>
                </div>

                <div>
                    <label for="exambro-config-download-url" style="margin-bottom: 6px;">Link Download config.json untuk Aplikasi</label>
                    <input id="exambro-config-download-url" type="text" value="{{ $exambroConfigDownloadUrl }}" readonly>
                </div>
            </div>

            <div class="actions" style="margin-top: 10px;">
                <a class="link" href="{{ $exambroPageUrl }}" target="_blank" rel="noopener noreferrer">Buka Halaman Exambro</a>
                <a class="link" href="{{ route('cbt.exambro.api-key.download') }}">Download Konfigurasi API Key</a>
                <a class="link" href="{{ $exambroConfigDownloadUrl }}">Download config.json (Aplikasi)</a>
                <button type="button" id="copy-exambro-key" style="background:#0ea5e9;">Salin API Key</button>
                <button type="button" id="copy-exambro-page" style="background:#0ea5e9;">Salin Link Halaman</button>
                <button type="button" id="copy-exambro-api" style="background:#0ea5e9;">Salin Link API</button>
                <button type="button" id="copy-exambro-config-download" style="background:#0ea5e9;">Salin Link Download config.json</button>
            </div>
        </div>

        <form action="{{ route('cbt.update') }}" method="post">
            @csrf

            <div>
                <label for="token">Token CBT</label>
                <input
                    id="token"
                    name="token"
                    type="password"
                    value="{{ old('token', $info->token) }}"
                    required
                >
                <label class="token-tools" for="toggle-token">
                    <input id="toggle-token" type="checkbox">
                    Tampilkan token
                </label>
            </div>

            <div>
                <label for="primary_url">URL Utama</label>
                <input
                    id="primary_url"
                    name="primary_url"
                    type="url"
                    value="{{ old('primary_url', $info->cbt_url) }}"
                    required
                >
            </div>

            <div>
                <label for="backup_url_1">URL Backup 1</label>
                <input
                    id="backup_url_1"
                    name="backup_url_1"
                    type="url"
                    value="{{ old('backup_url_1', $info->cbt_backup_url_1) }}"
                    required
                >
            </div>

            <div>
                <label for="backup_url_2">URL Backup 2</label>
                <input
                    id="backup_url_2"
                    name="backup_url_2"
                    type="url"
                    value="{{ old('backup_url_2', $info->cbt_backup_url_2) }}"
                    required
                >
            </div>

            <div>
                <label for="description">Keterangan</label>
                <textarea id="description" name="description">{{ old('description', $info->description) }}</textarea>
            </div>

            <div class="actions">
                <button type="submit">Simpan Perubahan</button>
                <a class="link" href="{{ route('cbt.index') }}">Kembali ke Halaman Utama</a>
            </div>
        </form>

        <form action="{{ route('cbt.admin.logout') }}" method="post">
            @csrf
            <div class="actions">
                <button type="submit">Logout Admin</button>
            </div>
        </form>
    </main>

    <script>
        (function () {
            const tokenInput = document.getElementById('token');
            const toggle = document.getElementById('toggle-token');
            const exambroTokenInput = document.getElementById('exambro-token');
            const keyInput = document.getElementById('exambro-current-key');
            const pageInput = document.getElementById('exambro-page-url');
            const apiInput = document.getElementById('exambro-api-url');
            const configDownloadInput = document.getElementById('exambro-config-download-url');
            const copyExambroTokenBtn = document.getElementById('copy-exambro-token');
            const copyKeyBtn = document.getElementById('copy-exambro-key');
            const copyPageBtn = document.getElementById('copy-exambro-page');
            const copyApiBtn = document.getElementById('copy-exambro-api');
            const copyConfigDownloadBtn = document.getElementById('copy-exambro-config-download');

            if (!tokenInput || !toggle) {
                return;
            }

            toggle.addEventListener('change', function () {
                tokenInput.type = this.checked ? 'text' : 'password';
            });

            function copyValue(inputEl, buttonEl, fallbackMessage) {
                if (!inputEl || !buttonEl) {
                    return;
                }

                const originalText = buttonEl.textContent;
                const value = inputEl.value || '';

                if (!value) {
                    alert(fallbackMessage);
                    return;
                }

                navigator.clipboard.writeText(value)
                    .then(function () {
                        buttonEl.textContent = 'Tersalin';
                        setTimeout(function () {
                            buttonEl.textContent = originalText;
                        }, 1400);
                    })
                    .catch(function () {
                        inputEl.focus();
                        inputEl.select();
                        document.execCommand('copy');
                        buttonEl.textContent = 'Tersalin';
                        setTimeout(function () {
                            buttonEl.textContent = originalText;
                        }, 1400);
                    });
            }

            if (copyPageBtn) {
                copyPageBtn.addEventListener('click', function () {
                    copyValue(pageInput, copyPageBtn, 'Link halaman Exambro kosong.');
                });
            }

            if (copyExambroTokenBtn) {
                copyExambroTokenBtn.addEventListener('click', function () {
                    copyValue(exambroTokenInput, copyExambroTokenBtn, 'PIN Exambro kosong.');
                });
            }

            if (copyKeyBtn) {
                copyKeyBtn.addEventListener('click', function () {
                    copyValue(keyInput, copyKeyBtn, 'API key Exambro kosong.');
                });
            }

            if (copyApiBtn) {
                copyApiBtn.addEventListener('click', function () {
                    copyValue(apiInput, copyApiBtn, 'Link API Exambro kosong.');
                });
            }

            if (copyConfigDownloadBtn) {
                copyConfigDownloadBtn.addEventListener('click', function () {
                    copyValue(configDownloadInput, copyConfigDownloadBtn, 'Link download config.json kosong.');
                });
            }
        })();
    </script>
</body>
</html>
