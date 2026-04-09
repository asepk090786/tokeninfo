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

        /* ===== Server Config Modern Cards ===== */
        .svr-section {
            margin-top: 16px;
            background: linear-gradient(180deg, #f8fbff 0%, #f1f6ff 100%);
            border: 1px solid #dbe7fb;
            border-radius: 16px;
            padding: 16px;
        }

        .svr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 14px;
        }

        .svr-card {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid #d9e5f6;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        .svr-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: #eef4ff;
            border-bottom: 1px solid #d9e5f6;
        }

        .svr-head-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .svr-icon { font-size: 1.1rem; }

        .svr-num {
            font-weight: 700;
            color: #1e3a8a;
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
        .dot-gray   { background: #94a3b8; }

        .svr-body {
            padding: 14px;
            flex: 1;
        }

        .svr-field { margin-bottom: 10px; }

        .svr-label {
            display: block;
            font-size: 0.81rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 5px;
        }

        .svr-input {
            width: 100%;
            background: #f8fbff;
            border: 1px solid #cbd9ee;
            border-radius: 8px;
            padding: 8px 10px;
            color: #1e293b;
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
            color: #475569;
            cursor: pointer;
            margin-bottom: 8px;
            user-select: none;
            list-style: none;
        }

        .svr-adv-toggle:hover { color: #94a3b8; }

        .svr-btn {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 10px;
            font-size: 0.86rem;
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

        .svr-action-wrap {
            padding: 10px;
            background: #f8fbff;
            border-top: 1px solid #d9e5f6;
        }

        .svr-timer-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 8px;
        }

        .svr-status-bar {
            padding: 8px 10px;
            background: #f8fbff;
            color: #475569;
            font-size: 0.78rem;
            border-bottom: 1px solid #d9e5f6;
        }

        .bulk-selection-card {
            margin-top: 12px;
            border: 1px solid #d1fae5;
            background: #ecfdf5;
        }

        .bulk-selection-actions {
            margin-top: 10px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 8px;
        }

        /* ===== Server Mirror Management — New Card Design ===== */
        .svr-section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .svr-section-icon {
            font-size: 1.6rem;
            line-height: 1;
        }

        .svr-section-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--ink);
        }

        .svr-card-new {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #dde6f7;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .svr-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            background: linear-gradient(135deg, #f0f6ff 0%, #e8f0ff 100%);
            border-bottom: 1px solid #dde6f7;
        }

        .svr-card-head-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .svr-card-icon {
            font-size: 1.5rem;
            line-height: 1;
        }

        .svr-card-name {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
        }

        .svr-card-mirror-label {
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 500;
        }

        .svr-card-status-badge {
            font-size: 0.78rem;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 999px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-active  { background: #dcfce7; color: #15803d; border: 1.5px solid #86efac; }
        .badge-standby { background: #fffbeb; color: #b45309; border: 1.5px solid #fcd34d; }
        .badge-offline-card { background: #f3f4f6; color: #6b7280; border: 1.5px solid #d1d5db; }

        .svr-card-status-section {
            padding: 12px 16px;
            background: #fafcff;
            border-bottom: 1px solid #eef2fb;
        }

        .svr-status-line {
            font-size: 0.88rem;
            color: #374151;
            line-height: 1.9;
        }

        .svr-status-bullet {
            margin-right: 6px;
            color: #94a3b8;
        }

        .svr-text-ok   { color: #16a34a; }
        .svr-text-warn { color: #b45309; }

        .svr-timer-info-text {
            font-size: 0.78rem;
            color: #b45309;
            margin-left: 14px;
        }

        .svr-card-body {
            padding: 14px 16px;
        }

        .svr-card-field {
            margin-bottom: 12px;
        }

        .svr-card-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .svr-card-input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 0.88rem;
            color: #1e293b;
            background: #fff;
            outline: none;
            transition: border-color 120ms ease, box-shadow 120ms ease;
            box-sizing: border-box;
        }

        .svr-card-input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        .svr-card-advanced > summary.svr-card-adv-toggle {
            font-size: 0.83rem;
            color: #475569;
            cursor: pointer;
            list-style: none;
            margin-bottom: 4px;
        }

        .svr-card-advanced > summary.svr-card-adv-toggle:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        .svr-card-primary-actions {
            padding: 10px 16px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            border-top: 1px solid #f1f5fd;
        }

        .svr-action-btn {
            border: 0;
            border-radius: 9px;
            padding: 11px 10px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
            transition: opacity 150ms, transform 80ms;
            text-align: center;
        }

        .svr-action-btn:active { transform: scale(0.97); }

        .btn-save-changes     { background: #0d9488; }
        .btn-save-changes:hover { background: #0f766e; }
        .btn-deactivate-hide  { background: #d97706; }
        .btn-deactivate-hide:hover { background: #b45309; }

        .svr-card-dropdown-wrap {
            padding: 8px 16px;
            border-top: 1px dashed #e2e8f0;
            position: relative;
        }

        .btn-disable-remove {
            width: 100%;
            background: #dc2626;
            color: #fff;
            border: 0;
            border-radius: 9px;
            padding: 11px 14px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 150ms;
        }

        .btn-disable-remove:hover { background: #b91c1c; }

        .svr-dropdown-arrow {
            font-size: 0.72rem;
            transition: transform 200ms;
            display: inline-block;
        }

        .svr-dropdown-arrow.open { transform: rotate(180deg); }

        .svr-dropdown-menu {
            display: none;
            background: #fff;
            border: 1px solid #fecaca;
            border-radius: 8px;
            margin-top: 6px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .svr-dropdown-menu.open { display: block; }

        .svr-dropdown-item {
            display: block;
            width: 100%;
            text-align: left;
            background: transparent;
            border: 0;
            border-bottom: 1px solid #fef2f2;
            padding: 10px 14px;
            font-size: 0.85rem;
            color: #374151;
            cursor: pointer;
            transition: background 120ms;
        }

        .svr-dropdown-item:last-child { border-bottom: 0; }
        .svr-dropdown-item:hover { background: #fef2f2; color: #dc2626; }
        .svr-dropdown-item-danger { color: #dc2626; }
        .svr-dropdown-item-danger:hover { background: #fee2e2; }

        .svr-card-timer-section {
            padding: 10px 16px 14px;
            border-top: 1px solid #f1f5fd;
        }

        .svr-timer-row {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .svr-timer-input { flex: 1; min-width: 0; }

        .svr-timer-btn {
            border: 0;
            border-radius: 8px;
            padding: 9px 16px;
            font-size: 0.84rem;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            white-space: nowrap;
            transition: opacity 150ms;
        }

        .svr-timer-btn:hover { opacity: 0.88; }
        .btn-timer-set   { background: #d97706; }
        .btn-timer-reset { background: #16a34a; }

        .svr-timer-hint {
            margin-top: 5px;
            font-size: 0.77rem;
            color: #94a3b8;
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
                <button class="menu-btn" data-target="panel-web" type="button">
                    Pengaturan WEB
                    <small>Server utama/backup yang tampil di halaman home</small>
                </button>
                <button class="menu-btn" data-target="panel-user-agent" type="button">
                    Pengaturan User-Agent
                    <small>Deteksi Exambro berdasarkan User-Agent client</small>
                </button>
                <button class="menu-btn" data-target="panel-version-sync" type="button">
                    Sinkronisasi Version
                    <small>Atur key, timeout, dan eksekusi sinkron version.json</small>
                </button>
                <button class="menu-btn" data-target="panel-version-sync-servers" type="button">
                    Server JSON Sync
                    <small>Kelola daftar server khusus tujuan sinkron version.json</small>
                </button>
            </nav>

            <div class="sidebar-actions">
                <a class="link-btn btn-soft" href="{{ route('cbt.index') }}">Lihat Halaman Home</a>
                <a class="link-btn btn-soft" href="{{ route('cbt.exambro.page') }}" target="_blank" rel="noopener noreferrer">Buka Halaman Exambro</a>
                @if ($loadBalancerLinkAvailable)
                    <a class="link-btn btn-soft" href="{{ route('cbt.lb') }}" target="_blank" rel="noopener noreferrer">Buka Link Load Balancing</a>
                @else
                    <span class="link-btn btn-soft" style="opacity:0.6; cursor:not-allowed;">Link Load Balancing aktif jika mirror valid lebih dari 1</span>
                @endif
                <button
                    id="btn-flush-cache"
                    type="button"
                    class="btn-soft"
                    style="width:100%;text-align:center;"
                    onclick="flushCacheFromAdmin()"
                >Sync Token dari DB</button>
                <span id="flush-result" style="display:none;font-size:0.82rem;padding:6px 8px;border-radius:8px;text-align:center;"></span>
            </div>

            <form class="logout-form" action="{{ route('cbt.admin.logout') }}" method="post">
                @csrf
                <button class="btn-danger" type="submit">Logout Admin</button>
            </form>
        </aside>

        <main class="content">
            <section class="banner">
                <h2>Pengaturan Informasi CBT</h2>
                <p>Kelola token, PIN, dan data server website dari satu panel admin.</p>

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
                    <p>
                        Sumber PIN:
                        {{ $exambroTokenSource === 'file' ? 'file server' : ($exambroTokenSource === 'web' ? 'web/server file' : '.env') }}
                    </p>
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
                    <h4>PIN Darurat Exit Exambro</h4>
                    <p>PIN ini khusus untuk emergency exit di aplikasi Neo_Exam. Sumber saat ini: {{ $exambroEmergencyExitPinSource === 'file' ? 'file server' : '.env' }}.</p>
                    <form action="{{ route('cbt.exambro.exit-emergency-pin.update') }}" method="post">
                        @csrf
                        <div class="field">
                            <label for="exambro-exit-emergency-pin">PIN Darurat Exit</label>
                            <input
                                id="exambro-exit-emergency-pin"
                                name="exambro_exit_emergency_pin"
                                type="text"
                                maxlength="20"
                                pattern="[A-Za-z0-9_-]{4,20}"
                                value="{{ old('exambro_exit_emergency_pin', $exambroEmergencyExitPin) }}"
                                required
                            >
                            @error('exambro_exit_emergency_pin')
                                <small style="color:#fca5a5;">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Simpan PIN Darurat Exit</button>
                        </div>
                    </form>
                </article>

                <article class="card" style="margin-top: 12px;">
                    <h4>Update Token CBT</h4>
                    <p>Form ini untuk update Token CBT saja. URL server mengikuti data WEB saat ini.</p>
                    <form action="{{ route('cbt.update') }}" method="post">
                        @csrf
                        @php
                            $serverOne = $servers[0] ?? null;
                            $serverTwo = $servers[1] ?? null;
                            $serverThree = $servers[2] ?? null;
                        @endphp
                        <div class="field">
                            <label for="token-only">Token CBT</label>
                            <input id="token-only" name="token" type="text" maxlength="6" value="{{ old('token', $info->token) }}" required>
                        </div>
                        <input type="hidden" name="primary_url" value="{{ old('primary_url', $serverOne['url'] ?? $info->cbt_url) }}">
                        <input type="hidden" name="backup_url_1" value="{{ old('backup_url_1', $serverTwo['url'] ?? $info->cbt_backup_url_1) }}">
                        <input type="hidden" name="backup_url_2" value="{{ old('backup_url_2', $serverThree['url'] ?? $info->cbt_backup_url_2) }}">
                        <input type="hidden" name="server_name_primary" value="{{ old('server_name_primary', $serverOne['name'] ?? ($info->server_name_primary ?? 'Server Utama')) }}">
                        <input type="hidden" name="server_name_backup_1" value="{{ old('server_name_backup_1', $serverTwo['name'] ?? ($info->server_name_backup_1 ?? 'Server 1')) }}">
                        <input type="hidden" name="server_name_backup_2" value="{{ old('server_name_backup_2', $serverThree['name'] ?? ($info->server_name_backup_2 ?? 'Server 2')) }}">
                        <input type="hidden" name="primary_core" value="{{ old('primary_core', $serverOne['core'] ?? ($info->server_primary_core ?? 4)) }}">
                        <input type="hidden" name="backup1_core" value="{{ old('backup1_core', $serverTwo['core'] ?? ($info->server_backup1_core ?? 4)) }}">
                        <input type="hidden" name="backup2_core" value="{{ old('backup2_core', $serverThree['core'] ?? ($info->server_backup2_core ?? 4)) }}">
                        <input type="hidden" name="primary_ram" value="{{ old('primary_ram', $serverOne['ram'] ?? ($info->server_primary_ram ?? '8 GB')) }}">
                        <input type="hidden" name="backup1_ram" value="{{ old('backup1_ram', $serverTwo['ram'] ?? ($info->server_backup1_ram ?? '8 GB')) }}">
                        <input type="hidden" name="backup2_ram" value="{{ old('backup2_ram', $serverThree['ram'] ?? ($info->server_backup2_ram ?? '8 GB')) }}">
                        <input type="hidden" name="primary_capacity" value="{{ old('primary_capacity', $serverOne['capacity'] ?? ($info->server_primary_capacity ?? 40)) }}">
                        <input type="hidden" name="backup1_capacity" value="{{ old('backup1_capacity', $serverTwo['capacity'] ?? ($info->server_backup1_capacity ?? 40)) }}">
                        <input type="hidden" name="backup2_capacity" value="{{ old('backup2_capacity', $serverThree['capacity'] ?? ($info->server_backup2_capacity ?? 40)) }}">
                        <input type="hidden" name="description" value="{{ old('description', $info->description) }}">
                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Simpan Token CBT</button>
                        </div>
                    </form>
                </article>
            </section>

            <section id="panel-web" class="panel">
                <h3>Pengaturan WEB</h3>
                <p class="panel-desc">Kelola jumlah mirror web, alamat mirror, dan status mirror yang dipakai untuk load balancing dari `mirror_list.json`.</p>

                <article class="card" style="margin-top: 12px;">
                    <h4>Mirror List Load Balancing</h4>
                    <p>File <strong>mirror_list.json</strong> otomatis dibuat dari data di panel ini. Setiap tambah, edit, hapus, hide, atau toggle LB pada mirror akan langsung menyinkronkan daftar mirror load balancing.</p>
                    <div class="grid" style="margin-top: 12px;">
                        <div class="field">
                            <label>URL mirror_list.json</label>
                            <input type="text" value="{{ $mirrorListUrl }}" readonly>
                        </div>
                        <div class="field">
                            <label>Jumlah Mirror Valid</label>
                            <input type="text" value="{{ $loadBalancerMirrorCount }} mirror" readonly>
                        </div>
                        <div class="field">
                            <label>Status Link Load Balancing</label>
                            <input type="text" value="{{ $loadBalancerLinkAvailable ? 'AKTIF' : 'MENUNGGU MINIMAL 2 MIRROR' }}" readonly>
                        </div>
                    </div>
                    <div class="btn-row">
                        <a class="link-btn btn-soft" href="{{ $mirrorListUrl }}" target="_blank" rel="noopener noreferrer">Buka mirror_list.json</a>
                        @if ($loadBalancerLinkAvailable)
                            <a class="link-btn btn-soft" href="{{ route('cbt.lb') }}" target="_blank" rel="noopener noreferrer">Tes Link Load Balancing</a>
                        @else
                            <span class="link-btn btn-soft" style="opacity:0.6; cursor:not-allowed;">Tambah minimal 2 mirror valid agar link LB aktif</span>
                        @endif
                    </div>
                </article>

                <article class="card" style="margin-top: 12px;">
                    <h4>Tambah Mirror Baru</h4>
                    <form action="{{ route('cbt.server.add') }}" method="post">
                        @csrf
                        <div class="grid" style="margin-top: 8px;">
                            <div class="field">
                                <label for="new-server-name">Nama Mirror</label>
                                <input id="new-server-name" name="server_name" type="text" maxlength="60" placeholder="Contoh: Mirror Lab 4">
                            </div>
                            <div class="field">
                                <label for="new-server-url">URL Mirror</label>
                                <input id="new-server-url" name="server_url" type="url" maxlength="255" required placeholder="https://cbt4.sekolah.sch.id">
                            </div>
                            <div class="field">
                                <label for="new-server-core">Core CPU</label>
                                <input id="new-server-core" name="server_core" type="number" min="1" max="256" value="4">
                            </div>
                            <div class="field">
                                <label for="new-server-ram">RAM</label>
                                <input id="new-server-ram" name="server_ram" type="text" maxlength="30" value="8 GB">
                            </div>
                            <div class="field">
                                <label for="new-server-capacity">Kapasitas Peserta</label>
                                <input id="new-server-capacity" name="server_capacity" type="number" min="1" max="100000" value="40">
                            </div>
                        </div>
                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Tambah Mirror</button>
                        </div>
                    </form>
                </article>

                <article class="card bulk-selection-card">
                    <h4>Kontrol Massal Pilihan Exambro</h4>
                    <p>Atur disable/enable tombol pemilihan server Exambro untuk semua mirror sekaligus.</p>
                    <form action="{{ route('cbt.server.selection.all.timer') }}" method="post">
                        @csrf
                        <div class="field">
                            <label for="bulk-disable-minutes">Disable Semua Selama (menit)</label>
                            <input id="bulk-disable-minutes" name="disable_minutes" type="number" min="1" max="1440" placeholder="Contoh: 30">
                        </div>
                        <div class="bulk-selection-actions">
                            <button class="btn-soft" type="submit" name="action" value="set_timer" style="background:#f59e0b;color:#fff;border:0;">Set Timer Semua</button>
                            <button class="btn-soft" type="submit" name="action" value="disable_all" style="background:#dc2626;color:#fff;border:0;">Disable Semua</button>
                            <button class="btn-soft" type="submit" name="action" value="enable_all" style="background:#16a34a;color:#fff;border:0;">Enable Semua</button>
                        </div>
                    </form>
                </article>

                <div class="svr-section">
                    <div class="svr-section-header">
                        <span class="svr-section-icon">☁️</span>
                        <h4 class="svr-section-title">Server Mirror Management</h4>
                    </div>
                    <div class="svr-grid">
                        @foreach ($servers as $idx => $server)
                            @php
                                $isUp = $server['status_class'] === 'up';
                                $isHidden = (($server['hidden'] ?? false) === true);
                                $selectionEnabled = (($server['selection_enabled'] ?? true) === true);
                                $selectionRuntimeEnabled = (($server['selection_runtime_enabled'] ?? true) === true);
                                $selectionTimedDisabled = (($server['selection_timed_disabled'] ?? false) === true);
                                $selectionDisabledUntil = $server['selection_disabled_until'] ?? null;
                                $lbEnabled = (($server['lb_enabled'] ?? false) === true);
                                $cardStatusText  = $isUp ? ($idx === 0 ? 'ACTIVE' : 'STANDBY') : 'OFFLINE';
                                $cardStatusClass = $isUp ? ($idx === 0 ? 'badge-active' : 'badge-standby') : 'badge-offline-card';
                            @endphp

                            <div class="svr-card-new">
                                {{-- Card Header --}}
                                <div class="svr-card-head">
                                    <div class="svr-card-head-left">
                                        <span class="svr-card-icon">🖥</span>
                                        <div>
                                            <div class="svr-card-name">{{ $server['name'] }}</div>
                                            <div class="svr-card-mirror-label">[Mirror {{ $idx + 1 }}]</div>
                                        </div>
                                    </div>
                                    <span class="svr-card-status-badge {{ $cardStatusClass }}">{{ $cardStatusText }}</span>
                                </div>

                                {{-- Status Info --}}
                                <div class="svr-card-status-section">
                                    <div class="svr-status-line">
                                        <span class="svr-status-bullet">•</span>
                                        Status Tampil: <strong class="{{ $isHidden ? 'svr-text-warn' : 'svr-text-ok' }}">{{ $isHidden ? 'HIDDEN' : 'VISIBLE' }}</strong>
                                    </div>
                                    <div class="svr-status-line">
                                        <span class="svr-status-bullet">•</span>
                                        Status LB: <strong class="{{ $lbEnabled ? 'svr-text-ok' : 'svr-text-warn' }}">{{ $lbEnabled ? 'AKTIF' : 'NONAKTIF' }}</strong>
                                    </div>
                                    <div class="svr-status-line">
                                        <span class="svr-status-bullet">•</span>
                                        Pilih di Exambro: <strong class="{{ $selectionRuntimeEnabled ? 'svr-text-ok' : 'svr-text-warn' }}">{{ $selectionRuntimeEnabled ? 'AKTIF' : 'NONAKTIF' }}</strong>
                                        @if ($selectionTimedDisabled && !empty($selectionDisabledUntil))
                                            <div class="svr-timer-info-text">Timer sampai: {{ $selectionDisabledUntil }}</div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Main Update Form --}}
                                <form action="{{ route('cbt.server.update', $server['key']) }}" method="post">
                                    @csrf
                                    <div class="svr-card-body">
                                        <div class="svr-card-field">
                                            <label class="svr-card-label">Nama Mirror</label>
                                            <input class="svr-card-input" name="server_name" type="text" maxlength="60" value="{{ $server['name'] }}" required>
                                        </div>
                                        <div class="svr-card-field">
                                            <label class="svr-card-label">URL Mirror</label>
                                            <input class="svr-card-input" name="server_url" type="url" maxlength="255" value="{{ $server['url'] }}" required>
                                        </div>
                                        <details class="svr-card-advanced">
                                            <summary class="svr-card-adv-toggle">Spesifikasi dan Kapasitas &rsaquo;</summary>
                                            <div style="margin-top: 8px;">
                                                <div class="svr-card-field">
                                                    <label class="svr-card-label">Core CPU</label>
                                                    <input class="svr-card-input" name="server_core" type="number" min="1" max="256" value="{{ $server['core'] ?? 4 }}" required>
                                                </div>
                                                <div class="svr-card-field">
                                                    <label class="svr-card-label">RAM</label>
                                                    <input class="svr-card-input" name="server_ram" type="text" maxlength="30" value="{{ $server['ram'] ?? '8 GB' }}" required>
                                                </div>
                                                <div class="svr-card-field">
                                                    <label class="svr-card-label">Kapasitas Peserta</label>
                                                    <input class="svr-card-input" name="server_capacity" type="number" min="1" max="100000" value="{{ $server['capacity'] ?? 40 }}" required>
                                                </div>
                                            </div>
                                        </details>
                                    </div>

                                    {{-- Primary Action Buttons --}}
                                    <div class="svr-card-primary-actions">
                                        <button type="submit" class="svr-action-btn btn-save-changes">Save Changes</button>
                                        <button type="button" class="svr-action-btn btn-deactivate-hide"
                                            onclick="document.getElementById('visibility-server-{{ $server['key'] }}').submit()">
                                            Deactivate/Hide Options
                                        </button>
                                    </div>
                                </form>

                                {{-- Disable / Remove Dropdown --}}
                                <div class="svr-card-dropdown-wrap">
                                    <button type="button" class="btn-disable-remove"
                                        onclick="toggleSvrDropdown('{{ $server['key'] }}')">
                                        Disable / Remove Mirror Options
                                        <span class="svr-dropdown-arrow" id="svr-arrow-{{ $server['key'] }}">▼</span>
                                    </button>
                                    <div class="svr-dropdown-menu" id="svr-dropdown-menu-{{ $server['key'] }}">
                                        <button type="submit" form="lb-server-{{ $server['key'] }}" class="svr-dropdown-item">
                                            {{ $lbEnabled ? 'Disable Load Balancer' : 'Enable Load Balancer' }}
                                        </button>
                                        <button type="submit" form="selection-toggle-server-{{ $server['key'] }}" class="svr-dropdown-item">
                                            {{ $selectionEnabled ? 'Disable Exambro Option' : 'Enable Exambro Option' }}
                                        </button>
                                        @if (count($servers) > 1)
                                            <button type="submit" form="delete-server-{{ $server['key'] }}" class="svr-dropdown-item svr-dropdown-item-danger">
                                                Delete Mirror
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Timer Section --}}
                                <div class="svr-card-timer-section">
                                    <div class="svr-timer-row">
                                        <input id="timer-minutes-{{ $server['key'] }}" type="number" min="1" max="1440"
                                            class="svr-card-input svr-timer-input" placeholder="Disable minutes">
                                        <button type="button" class="svr-timer-btn btn-timer-set"
                                            onclick="submitServerSelectionTimer('{{ $server['key'] }}')">Set</button>
                                        <button type="submit" form="selection-timer-reset-server-{{ $server['key'] }}"
                                            class="svr-timer-btn btn-timer-reset">Reset</button>
                                    </div>
                                    <div class="svr-timer-hint">Disable minutes (contoh 30)</div>
                                </div>

                                {{-- Hidden Forms --}}
                                <form id="visibility-server-{{ $server['key'] }}" action="{{ route('cbt.server.visibility.toggle', $server['key']) }}" method="post" style="display:none;">
                                    @csrf
                                </form>
                                <form id="lb-server-{{ $server['key'] }}" action="{{ route('cbt.server.lb.toggle', $server['key']) }}" method="post" style="display:none;">
                                    @csrf
                                </form>
                                <form id="selection-toggle-server-{{ $server['key'] }}" action="{{ route('cbt.server.selection.toggle', $server['key']) }}" method="post" style="display:none;">
                                    @csrf
                                </form>
                                <form id="selection-timer-set-server-{{ $server['key'] }}" action="{{ route('cbt.server.selection.timer', $server['key']) }}" method="post" style="display:none;">
                                    @csrf
                                    <input type="hidden" name="disable_minutes" id="selection-timer-hidden-minutes-{{ $server['key'] }}">
                                </form>
                                <form id="selection-timer-reset-server-{{ $server['key'] }}" action="{{ route('cbt.server.selection.timer', $server['key']) }}" method="post" style="display:none;">
                                    @csrf
                                    <input type="hidden" name="clear_timer" value="1">
                                </form>
                                @if (count($servers) > 1)
                                    <form id="delete-server-{{ $server['key'] }}" action="{{ route('cbt.server.delete', $server['key']) }}" method="post" style="display:none;">
                                        @csrf
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="svr-summary">
                    <h4 class="summary-title">Ringkasan Status Server</h4>
                    <div class="summary-grid">
                        @foreach ($servers as $idx => $server)
                            @php
                                $isUp = $server['status_class'] === 'up';
                                $statusLabel = $isUp ? ($idx === 0 ? 'AKTIF' : 'SIAGA') : 'OFFLINE';
                                $summClass = $isUp ? ($idx === 0 ? 'summary-aktif' : 'summary-siaga') : 'summary-offline';
                                $capacity = max(1, (int) ($server['capacity'] ?? 1));
                                $activeCount = max(0, (int) ($server['active_user_count'] ?? 0));
                                $loadPercent = (int) round(($activeCount / $capacity) * 100);
                                $loadClass = $server['login_indicator'] ?? 'low';
                                $loadLabel = $server['login_indicator_label'] ?? 'Rendah';
                            @endphp
                            <div class="summary-item {{ $summClass }}">
                                <div class="summary-row">
                                    <span class="summary-name">{{ $server['name'] }}</span>
                                    <span class="summary-badge badge-{{ strtolower($statusLabel) }}">{{ $statusLabel }}</span>
                                </div>
                                <p class="summary-url">{{ $server['url'] }}</p>
                                <div class="summary-stats">
                                    <div class="summary-stat-row">
                                        <span class="summary-stat-label">Peserta Aktif (2m)</span>
                                        <span class="summary-load-badge {{ $loadClass }}">{{ $activeCount }} / {{ $capacity }} ({{ $loadPercent }}%)</span>
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
                        @php
                            $serverOne = $servers[0] ?? null;
                            $serverTwo = $servers[1] ?? null;
                            $serverThree = $servers[2] ?? null;
                        @endphp
                        <input type="hidden" name="token" value="{{ $info->token }}">
                        <input type="hidden" name="primary_url" value="{{ $serverOne['url'] ?? $info->cbt_url }}">
                        <input type="hidden" name="backup_url_1" value="{{ $serverTwo['url'] ?? $info->cbt_backup_url_1 }}">
                        <input type="hidden" name="backup_url_2" value="{{ $serverThree['url'] ?? $info->cbt_backup_url_2 }}">
                        <input type="hidden" name="server_name_primary" value="{{ $serverOne['name'] ?? ($info->server_name_primary ?? 'Server Utama') }}">
                        <input type="hidden" name="server_name_backup_1" value="{{ $serverTwo['name'] ?? ($info->server_name_backup_1 ?? 'Server 1') }}">
                        <input type="hidden" name="server_name_backup_2" value="{{ $serverThree['name'] ?? ($info->server_name_backup_2 ?? 'Server 2') }}">
                        <input type="hidden" name="primary_core" value="{{ $serverOne['core'] ?? ($info->server_primary_core ?? 4) }}">
                        <input type="hidden" name="backup1_core" value="{{ $serverTwo['core'] ?? ($info->server_backup1_core ?? 4) }}">
                        <input type="hidden" name="backup2_core" value="{{ $serverThree['core'] ?? ($info->server_backup2_core ?? 4) }}">
                        <input type="hidden" name="primary_ram" value="{{ $serverOne['ram'] ?? ($info->server_primary_ram ?? '8 GB') }}">
                        <input type="hidden" name="backup1_ram" value="{{ $serverTwo['ram'] ?? ($info->server_backup1_ram ?? '8 GB') }}">
                        <input type="hidden" name="backup2_ram" value="{{ $serverThree['ram'] ?? ($info->server_backup2_ram ?? '8 GB') }}">
                        <input type="hidden" name="primary_capacity" value="{{ $serverOne['capacity'] ?? ($info->server_primary_capacity ?? 40) }}">
                        <input type="hidden" name="backup1_capacity" value="{{ $serverTwo['capacity'] ?? ($info->server_backup1_capacity ?? 40) }}">
                        <input type="hidden" name="backup2_capacity" value="{{ $serverThree['capacity'] ?? ($info->server_backup2_capacity ?? 40) }}">
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

            <section id="panel-version-sync" class="panel">
                <h3>Sinkronisasi Version.json</h3>
                <p class="panel-desc">Panel ini khusus pengaturan key sinkron, timeout, dan eksekusi sinkron manual tanpa CLI.</p>

                <article class="card" style="margin-top: 12px;">
                    <h4>Status Sinkronisasi</h4>
                    <div class="grid" style="margin-top: 12px;">
                        <div class="field">
                            <label>Status Fitur</label>
                            <input type="text" value="{{ $versionSyncSettings['enabled'] ? 'AKTIF' : 'NONAKTIF' }}" readonly>
                        </div>
                        <div class="field">
                            <label>Versi Saat Ini</label>
                            <input type="text" value="{{ $currentConfigVersion !== '' ? $currentConfigVersion : 'Belum ada' }}" readonly>
                        </div>
                        <div class="field">
                            <label>Jumlah Target Server JSON</label>
                            <input type="text" value="{{ count($versionSyncTargets) }} target" readonly>
                        </div>
                        <div class="field">
                            <label>Timeout Request Sinkron</label>
                            <input type="text" value="{{ $versionSyncSettings['timeout_seconds'] }} detik" readonly>
                        </div>
                    </div>

                    <div class="field" style="margin-top: 10px;">
                        <label>Daftar Target Sinkron</label>
                        <textarea readonly>@if (count($versionSyncTargets) === 0)
Tidak ada target mirror valid.
@else
@foreach($versionSyncTargets as $target)
- {{ $target['name'] }} ({{ $target['host'] }})
{{ $target['sync_endpoint'] }}
@endforeach
@endif</textarea>
                    </div>
                </article>

                <article class="card" style="margin-top: 12px;">
                    <h4>Pengaturan Sinkron</h4>
                    <form action="{{ route('cbt.version.sync.settings.update') }}" method="post">
                        @csrf
                        <div class="field" style="display: flex; align-items: center; gap: 10px;">
                            <input id="version_sync_enabled" name="version_sync_enabled" type="checkbox" value="1" {{ $versionSyncSettings['enabled'] ? 'checked' : '' }} style="width: auto;">
                            <label for="version_sync_enabled" style="margin: 0;">Aktifkan sinkronisasi version.json ke daftar server JSON Sync</label>
                        </div>

                        <div class="field">
                            <label for="version_sync_key">Sync Key Antar Server (min 16 karakter)</label>
                            <input
                                id="version_sync_key"
                                name="version_sync_key"
                                type="text"
                                maxlength="128"
                                pattern="[A-Za-z0-9_-]{16,128}"
                                value="{{ old('version_sync_key', $versionSyncSettings['key']) }}"
                                placeholder="Contoh: SYNC_KEY_2026_SERVER_CLUSTER"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="version_sync_timeout_seconds">Timeout Request (1-5 detik)</label>
                            <input
                                id="version_sync_timeout_seconds"
                                name="version_sync_timeout_seconds"
                                type="number"
                                min="1"
                                max="5"
                                value="{{ old('version_sync_timeout_seconds', $versionSyncSettings['timeout_seconds']) }}"
                                required
                            >
                        </div>

                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Simpan Pengaturan Sinkron</button>
                        </div>
                    </form>

                    <form action="{{ route('cbt.version.sync.now') }}" method="post" style="margin-top: 10px;">
                        @csrf
                        <div class="btn-row">
                            <button class="btn-soft" type="submit">Sinkronkan Sekarang ke Semua Server JSON</button>
                        </div>
                    </form>
                </article>
            </section>

            <section id="panel-version-sync-servers" class="panel">
                <h3>Server JSON Sync</h3>
                <p class="panel-desc">Panel ini terpisah dari server LB. Daftar server di sini hanya dipakai untuk sinkronisasi version.json.</p>

                <article class="card" style="margin-top: 12px;">
                    <h4>Daftar Server Tujuan Sinkron</h4>
                    <p>Format per baris: <strong>Nama|URL</strong> atau langsung <strong>URL</strong>. Contoh: <em>Node 2|https://red2.example.sch.id</em>.</p>
                    <form action="{{ route('cbt.version.sync.servers.update') }}" method="post">
                        @csrf
                        <div class="field">
                            <label for="version_sync_servers_text">Daftar Server JSON Sync</label>
                            <textarea id="version_sync_servers_text" name="version_sync_servers_text" rows="8" placeholder="Node 2|https://red2.example.sch.id&#10;Node 3|https://red3.example.sch.id">{{ old('version_sync_servers_text', $versionSyncServersText) }}</textarea>
                        </div>
                        <div class="btn-row">
                            <button class="btn-primary" type="submit">Simpan Daftar Server JSON Sync</button>
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

            var copyTokenBtn = document.getElementById('copy-exambro-token');

            if (copyTokenBtn) {
                copyTokenBtn.addEventListener('click', function () {
                    copyValue(exambroTokenInput, copyTokenBtn, 'PIN Exambro kosong.');
                });
            }
        })();
    </script>

    <script>
        function flushCacheFromAdmin() {
            var btn = document.getElementById('btn-flush-cache');
            var result = document.getElementById('flush-result');
            btn.disabled = true;
            btn.textContent = 'Memproses...';

            fetch('{{ route('cbt.admin.flush-cache') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                result.style.display = 'block';
                if (data.status === 'ok') {
                    result.style.background = '#ecfdf5';
                    result.style.color = '#047857';
                    result.textContent = 'Cache di-refresh! Token terbaru dari DB aktif.';
                } else {
                    result.style.background = '#fef2f2';
                    result.style.color = '#b91c1c';
                    result.textContent = data.message || 'Gagal refresh cache.';
                }
            })
            .catch(function () {
                result.style.display = 'block';
                result.style.background = '#fef2f2';
                result.style.color = '#b91c1c';
                result.textContent = 'Gagal terhubung ke server.';
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = 'Sync Token dari DB';
            });
        }

        function submitServerSelectionTimer(serverKey) {
            var minutesInput = document.getElementById('timer-minutes-' + serverKey);
            var hiddenMinutesInput = document.getElementById('selection-timer-hidden-minutes-' + serverKey);
            var form = document.getElementById('selection-timer-set-server-' + serverKey);

            if (!minutesInput || !hiddenMinutesInput || !form) {
                return;
            }

            var minutes = parseInt(minutesInput.value, 10);
            if (!Number.isFinite(minutes) || minutes <= 0) {
                alert('Isi durasi disable (menit) dengan angka lebih dari 0.');
                minutesInput.focus();
                return;
            }

            hiddenMinutesInput.value = String(minutes);
            form.submit();
        }

        function toggleSvrDropdown(serverKey) {
            var menu  = document.getElementById('svr-dropdown-menu-' + serverKey);
            var arrow = document.getElementById('svr-arrow-' + serverKey);
            var isOpen = menu.classList.contains('open');

            // Close all dropdowns first
            document.querySelectorAll('.svr-dropdown-menu.open').forEach(function (m) {
                m.classList.remove('open');
            });
            document.querySelectorAll('.svr-dropdown-arrow.open').forEach(function (a) {
                a.classList.remove('open');
            });

            if (!isOpen) {
                menu.classList.add('open');
                if (arrow) { arrow.classList.add('open'); }
            }
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.svr-card-dropdown-wrap')) {
                document.querySelectorAll('.svr-dropdown-menu.open').forEach(function (m) {
                    m.classList.remove('open');
                });
                document.querySelectorAll('.svr-dropdown-arrow.open').forEach(function (a) {
                    a.classList.remove('open');
                });
            }
        });
    </script>
</body>
</html>
