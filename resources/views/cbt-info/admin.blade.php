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

            .svr-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ===== Server Config Dark Cards ===== */
        .svr-section {
            margin-top: 16px;
            background: #111827;
            border-radius: 16px;
            padding: 20px;
        }

        .svr-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .svr-card {
            background: #1e293b;
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 0;
        }

        .svr-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: #162032;
            border-bottom: 1px solid #2d3748;
        }

        .svr-head-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .svr-icon { font-size: 1.1rem; }

        .svr-num {
            font-weight: 700;
            color: #f1f5f9;
            font-size: 0.95rem;
        }

        .svr-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .dot-green  { background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,.55); }
        .dot-orange { background: #f59e0b; box-shadow: 0 0 6px rgba(245,158,11,.55); }
        .dot-gray   { background: #6b7280; }

        .svr-body {
            padding: 14px;
            flex: 1;
        }

        .svr-field { margin-bottom: 10px; }

        .svr-label {
            display: block;
            font-size: 0.81rem;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 5px;
        }

        .svr-input {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 8px 10px;
            color: #e2e8f0;
            font-size: 0.88rem;
            outline: none;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }

        .svr-input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56,189,248,.15);
        }

        .svr-advanced { margin-top: 4px; }

        .svr-adv-toggle {
            font-size: 0.80rem;
            color: #64748b;
            cursor: pointer;
            margin-bottom: 8px;
            user-select: none;
            list-style: none;
        }

        .svr-adv-toggle:hover { color: #94a3b8; }

        .svr-btn {
            width: 100%;
            border: 0;
            border-radius: 0;
            padding: 11px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
            transition: opacity 150ms;
            margin: 0;
        }

        .svr-btn:hover { opacity: 0.88; }
        .svr-btn-green  { background: #16a34a; }
        .svr-btn-orange { background: #b45309; }
        .svr-btn-dark   { background: #1f2937; }

        /* Summary */
        .svr-summary {
            margin-top: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
        }

        .summary-title {
            margin: 0 0 12px;
            font-size: 0.95rem;
            color: #1f2937;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .summary-item {
            border-radius: 10px;
            padding: 10px 12px;
            border: 1px solid;
        }

        .summary-aktif   { background: #f0fdf4; border-color: #86efac; }
        .summary-siaga   { background: #fffbeb; border-color: #fcd34d; }
        .summary-offline { background: #f9fafb; border-color: #e5e7eb; }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
        }

        .summary-name {
            font-size: 0.83rem;
            font-weight: 700;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .summary-badge {
            font-size: 0.69rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .badge-aktif   { background: #dcfce7; color: #15803d; }
        .badge-siaga   { background: #fffbeb; color: #b45309; }
        .badge-offline { background: #f3f4f6; color: #6b7280; }

        .summary-url {
            margin: 6px 0 0;
            font-size: 0.78rem;
            color: #6b7280;
            word-break: break-all;
        }

        .summary-stats {
            margin-top: 10px;
            display: grid;
            gap: 8px;
        }

        .summary-stat-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.78rem;
        }

        .summary-stat-label {
            font-weight: 700;
            color: #475569;
        }

        .summary-load-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 88px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .summary-load-badge.low {
            background: #dcfce7;
            color: #15803d;
        }

        .summary-load-badge.medium {
            background: #fef3c7;
            color: #b45309;
        }

        .summary-load-badge.high {
            background: #fee2e2;
            color: #dc2626;
        }

        .summary-gauge {
            position: relative;
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: linear-gradient(90deg, #22c55e 0% 70%, #fde047 70% 90%, #ef4444 90% 100%);
        }

        .summary-gauge-fill {
            position: absolute;
            inset: 0 auto 0 0;
            width: var(--load-percent, 0%);
            background: rgba(15, 23, 42, 0.16);
        }

        .summary-gauge-marker {
            position: absolute;
            top: -3px;
            bottom: -3px;
            width: 3px;
            left: calc(var(--load-percent, 0%) - 1px);
            background: #111827;
            border-radius: 999px;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.55);
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
                <button class="menu-btn" data-target="panel-user-agent" type="button">
                    Pengaturan User-Agent
                    <small>Deteksi Exambro berdasarkan User-Agent client</small>
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
                        <input type="hidden" name="server_name_primary" value="{{ old('server_name_primary', $info->server_name_primary ?? 'Server Utama') }}">
                        <input type="hidden" name="server_name_backup_1" value="{{ old('server_name_backup_1', $info->server_name_backup_1 ?? 'Server 1') }}">
                        <input type="hidden" name="server_name_backup_2" value="{{ old('server_name_backup_2', $info->server_name_backup_2 ?? 'Server 2') }}">
                        <input type="hidden" name="primary_core" value="{{ old('primary_core', $info->server_primary_core ?? 4) }}">
                        <input type="hidden" name="backup1_core" value="{{ old('backup1_core', $info->server_backup1_core ?? 4) }}">
                        <input type="hidden" name="backup2_core" value="{{ old('backup2_core', $info->server_backup2_core ?? 4) }}">
                        <input type="hidden" name="primary_ram" value="{{ old('primary_ram', $info->server_primary_ram ?? '8 GB') }}">
                        <input type="hidden" name="backup1_ram" value="{{ old('backup1_ram', $info->server_backup1_ram ?? '8 GB') }}">
                        <input type="hidden" name="backup2_ram" value="{{ old('backup2_ram', $info->server_backup2_ram ?? '8 GB') }}">
                        <input type="hidden" name="primary_capacity" value="{{ old('primary_capacity', $info->server_primary_capacity ?? 40) }}">
                        <input type="hidden" name="backup1_capacity" value="{{ old('backup1_capacity', $info->server_backup1_capacity ?? 40) }}">
                        <input type="hidden" name="backup2_capacity" value="{{ old('backup2_capacity', $info->server_backup2_capacity ?? 40) }}">
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
                <p class="panel-desc">Kelola nama, URL, dan spesifikasi masing-masing server CBT.</p>

                {{-- Server Cards --}}
                <div class="svr-section">
                    <div class="svr-grid">
                        @foreach ($servers as $idx => $server)
                            @php
                                $isUp       = $server['status_class'] === 'up';
                                $isPrimary  = $idx === 0;
                                $dotClass   = $isUp ? ($isPrimary ? 'dot-green' : 'dot-orange') : 'dot-gray';
                                $btnClass   = $isUp ? ($isPrimary ? 'svr-btn-green' : 'svr-btn-orange') : 'svr-btn-dark';
                                $urlMap     = [$info->cbt_url, $info->cbt_backup_url_1, $info->cbt_backup_url_2];
                                $nameMap    = [
                                    $info->server_name_primary  ?? 'Server Utama',
                                    $info->server_name_backup_1 ?? 'Server 1',
                                    $info->server_name_backup_2 ?? 'Server 2',
                                ];
                                $coreMap    = [$info->server_primary_core ?? 4, $info->server_backup1_core ?? 4, $info->server_backup2_core ?? 4];
                                $ramMap     = [$info->server_primary_ram  ?? '8 GB', $info->server_backup1_ram  ?? '8 GB', $info->server_backup2_ram  ?? '8 GB'];
                                $capMap     = [$info->server_primary_capacity ?? 40, $info->server_backup1_capacity ?? 40, $info->server_backup2_capacity ?? 40];
                            @endphp
                            <form action="{{ route('cbt.server.update', $server['key']) }}" method="post" class="svr-card">
                                @csrf
                                <div class="svr-head">
                                    <div class="svr-head-left">
                                        <span class="svr-icon">🖥</span>
                                        <span class="svr-num">Server {{ $idx + 1 }}</span>
                                    </div>
                                    <span class="svr-dot {{ $dotClass }}"></span>
                                </div>
                                <div class="svr-body">
                                    <div class="svr-field">
                                        <label class="svr-label">Nama Server</label>
                                        <input class="svr-input" name="server_name" type="text" maxlength="60"
                                               value="{{ $nameMap[$idx] }}" required>
                                    </div>
                                    <div class="svr-field">
                                        <label class="svr-label">URL Server</label>
                                        <input class="svr-input" name="server_url" type="url" maxlength="255"
                                               value="{{ $urlMap[$idx] }}" required>
                                    </div>
                                    <details class="svr-advanced">
                                        <summary class="svr-adv-toggle">⚙ Spesifikasi &amp; Kapasitas</summary>
                                        <div class="svr-field">
                                            <label class="svr-label">Core CPU</label>
                                            <input class="svr-input" name="server_core" type="number" min="1" max="256"
                                                   value="{{ $coreMap[$idx] }}" required>
                                        </div>
                                        <div class="svr-field">
                                            <label class="svr-label">RAM</label>
                                            <input class="svr-input" name="server_ram" type="text" maxlength="30"
                                                   value="{{ $ramMap[$idx] }}" required>
                                        </div>
                                        <div class="svr-field">
                                            <label class="svr-label">Kapasitas Peserta</label>
                                            <input class="svr-input" name="server_capacity" type="number" min="1" max="100000"
                                                   value="{{ $capMap[$idx] }}" required>
                                        </div>
                                    </details>
                                </div>
                                <button type="submit" class="svr-btn {{ $btnClass }}">💾 Simpan</button>
                            </form>
                        @endforeach
                    </div>
                </div>

                {{-- Ringkasan Status Server --}}
                <div class="svr-summary">
                    <h4 class="summary-title">🏁 Ringkasan Status Server</h4>
                    <div class="summary-grid">
                        @foreach ($servers as $idx => $server)
                            @php
                                $isUp        = $server['status_class'] === 'up';
                                $isPrimary   = $idx === 0;
                                $statusLabel = $isUp ? ($isPrimary ? 'AKTIF' : 'SIAGA') : 'OFFLINE';
                                $summClass   = $isUp ? ($isPrimary ? 'summary-aktif' : 'summary-siaga') : 'summary-offline';
                                $roleLabel   = $isPrimary ? 'Utama' : 'Backup ' . $idx;
                                $capacity    = max(1, (int) ($server['capacity'] ?? 1));
                                $loginCount  = max(0, (int) ($server['login_count'] ?? 0));
                                $loadPercent = (int) round(($loginCount / $capacity) * 100);
                                $loadClass   = $server['login_indicator'] ?? 'low';
                                $loadLabel   = $server['login_indicator_label'] ?? 'Rendah';
                            @endphp
                            <div class="summary-item {{ $summClass }}">
                                <div class="summary-row">
                                    <span class="summary-name">{{ $server['name'] }} – {{ $roleLabel }}</span>
                                    <span class="summary-badge badge-{{ strtolower($statusLabel) }}">{{ $statusLabel }}</span>
                                </div>
                                <p class="summary-url">{{ $server['url'] }}</p>
                                <div class="summary-stats">
                                    <div class="summary-stat-row">
                                        <span class="summary-stat-label">Peserta Login</span>
                                        <span class="summary-load-badge {{ $loadClass }}">{{ $loginCount }} / {{ $capacity }} ({{ $loadPercent }}%)</span>
                                    </div>
                                    <div class="summary-stat-row">
                                        <span class="summary-stat-label">Indikator CBT</span>
                                        <span class="summary-load-badge {{ $loadClass }}">{{ $loadLabel }}</span>
                                    </div>
                                    <div class="summary-gauge" style="--load-percent: {{ max(0, min(100, $loadPercent)) }}%;">
                                        <div class="summary-gauge-fill"></div>
                                        <div class="summary-gauge-marker"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Keterangan Halaman --}}
                <article class="card" style="margin-top: 16px;">
                    <h4>Keterangan Halaman Home</h4>
                    <form action="{{ route('cbt.update') }}" method="post">
                        @csrf
                        <input type="hidden" name="token" value="{{ $info->token }}">
                        <input type="hidden" name="primary_url" value="{{ $info->cbt_url }}">
                        <input type="hidden" name="backup_url_1" value="{{ $info->cbt_backup_url_1 }}">
                        <input type="hidden" name="backup_url_2" value="{{ $info->cbt_backup_url_2 }}">
                        <input type="hidden" name="server_name_primary" value="{{ $info->server_name_primary ?? 'Server Utama' }}">
                        <input type="hidden" name="server_name_backup_1" value="{{ $info->server_name_backup_1 ?? 'Server 1' }}">
                        <input type="hidden" name="server_name_backup_2" value="{{ $info->server_name_backup_2 ?? 'Server 2' }}">
                        <input type="hidden" name="primary_core" value="{{ $info->server_primary_core ?? 4 }}">
                        <input type="hidden" name="backup1_core" value="{{ $info->server_backup1_core ?? 4 }}">
                        <input type="hidden" name="backup2_core" value="{{ $info->server_backup2_core ?? 4 }}">
                        <input type="hidden" name="primary_ram" value="{{ $info->server_primary_ram ?? '8 GB' }}">
                        <input type="hidden" name="backup1_ram" value="{{ $info->server_backup1_ram ?? '8 GB' }}">
                        <input type="hidden" name="backup2_ram" value="{{ $info->server_backup2_ram ?? '8 GB' }}">
                        <input type="hidden" name="primary_capacity" value="{{ $info->server_primary_capacity ?? 40 }}">
                        <input type="hidden" name="backup1_capacity" value="{{ $info->server_backup1_capacity ?? 40 }}">
                        <input type="hidden" name="backup2_capacity" value="{{ $info->server_backup2_capacity ?? 40 }}">
                        <div class="field">
                            <label for="description">Keterangan / Deskripsi Halaman</label>
                            <textarea id="description" name="description">{{ old('description', $info->description) }}</textarea>
                        </div>
                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Simpan Keterangan</button>
                        </div>
                    </form>
                </article>
            </section>

            <section id="panel-user-agent" class="panel">
                <h3>Pengaturan User-Agent</h3>
                <p class="panel-desc">Atur keyword User-Agent yang dianggap sebagai aplikasi Exambro untuk auto-redirect ke halaman Exambro.</p>

                <article class="card">
                    <h4>Deteksi Client Exambro</h4>
                    <form action="{{ route('cbt.user-agent.update') }}" method="post">
                        @csrf
                        <div class="field" style="display: flex; align-items: center; gap: 10px;">
                            <input id="user_agent_detection_enabled" name="user_agent_detection_enabled" type="checkbox" value="1" {{ $userAgentDetectionEnabled ? 'checked' : '' }} style="width: auto;">
                            <label for="user_agent_detection_enabled" style="margin: 0;">Aktifkan deteksi User-Agent untuk redirect otomatis ke /exambro</label>
                        </div>

                        <div class="field">
                            <label for="user_agent_patterns">Keyword User-Agent (satu baris satu keyword, contoh: exambro)</label>
                            <textarea id="user_agent_patterns" name="user_agent_patterns" rows="6" required>{{ old('user_agent_patterns', $userAgentPatterns) }}</textarea>
                        </div>

                        <p style="margin: 0 0 12px; color: var(--muted); font-size: 0.88rem;">
                            Cocok jika User-Agent mengandung salah satu keyword di atas (case-insensitive).
                        </p>

                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Simpan Pengaturan User-Agent</button>
                        </div>
                    </form>
                </article>
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
