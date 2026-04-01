<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exambro Client - Interactive Dashboard</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #dde1e8;
            --panel: #eff2f7;
            --text: #233049;
            --muted: #7d8aa0;
            --line: #c8d0dd;
            --good: #10a856;
            --good-bg: #c8f0dc;
            --bad: #d94747;
            --bad-bg: #f8d3d3;
            --accent-start: #3f7de8;
            --accent-end: #7a58ea;
            --transition: all 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background: linear-gradient(165deg, #d5dae2 0%, #e7ebf2 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 0;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        .app-shell {
            width: min(760px, 100%);
            margin: 0 auto;
            min-height: 100vh;
            background: var(--bg);
            border-left: 1px solid #bcc6d4;
            border-right: 1px solid #bcc6d4;
            display: flex;
            flex-direction: column;
        }

        .hero {
            background: linear-gradient(100deg, var(--accent-start), var(--accent-end));
            color: #fff;
            padding: 14px 14px 12px;
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease;
            flex: 1;
            min-width: 0;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .brand-logo svg {
            width: 20px;
            height: 20px;
            fill: #f8fbff;
        }

        .brand-content {
            min-width: 0;
        }

        .brand-title {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: 0.2px;
            line-height: 1;
        }

        .brand-subtitle {
            margin-top: 2px;
            opacity: 0.95;
            font-size: 0.85rem;
            font-weight: 700;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .refresh-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 10px;
            border-radius: 7px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }

        .refresh-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .refresh-btn:active {
            transform: scale(0.96);
        }

        .refresh-btn.loading {
            cursor: not-allowed;
            opacity: 0.8;
        }

        .refresh-btn svg {
            width: 12px;
            height: 12px;
        }

        .refresh-btn.loading svg {
            animation: spin 1s linear infinite;
        }

        .settings-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border-radius: 7px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            cursor: pointer;
            transition: var(--transition);
        }

        .settings-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .settings-toggle svg {
            width: 14px;
            height: 14px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .status-badge.live {
            background: rgba(34, 197, 94, 0.3);
        }

        .status-badge .indicator {
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: #fff;
            display: inline-block;
        }

        .status-badge.live .indicator {
            background: #22c55e;
            animation: pulse 2s ease-in-out infinite;
        }

        .hero-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 10px;
            animation: slideIn 0.5s ease 0.1s both;
        }

        .hero-left {
            flex: 1;
            min-width: 0;
        }

        .hero-title {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.1;
            margin: 0;
        }

        .hero-subtitle {
            font-size: 0.8rem;
            margin-top: 2px;
            opacity: 0.85;
        }

        .hero-right {
            flex-shrink: 0;
        }

        .token-code {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.1px;
            text-align: right;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            padding: 6px 10px;
            box-shadow: 0 3px 10px rgba(29, 78, 216, 0.18);
            transition: var(--transition);
            animation: slideIn 0.5s ease 0.15s both;
        }

        .token-code:hover {
            background: rgba(255, 255, 255, 0.28);
        }

        .token-code .label {
            opacity: 0.85;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .token-code .value {
            font-size: 0.95rem;
            font-weight: 800;
            color: #ffffff;
            text-shadow: 0 1px 8px rgba(15, 23, 42, 0.35);
            font-variant-numeric: tabular-nums;
        }

        .token-code.hidden .value {
            font-family: monospace;
        }

        .token-code.hidden-by-admin {
            opacity: 0.7;
            border-style: dashed;
        }

        .hero-meta {
            margin-top: 8px;
            font-size: 0.75rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .indicator-dot {
            width: 5px;
            height: 5px;
            border-radius: 999px;
        }

        .indicator-dot.active {
            background: #22c55e;
            animation: pulse 2s ease-in-out infinite;
        }

        .indicator-dot.inactive {
            background: #fb7185;
        }

        .settings-panel {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
            z-index: 100;
        }

        .settings-panel.active {
            display: block;
            animation: slideIn 0.3s ease;
        }

        .settings-panel-content {
            padding: 10px;
        }

        .settings-item {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #f0f0f0;
        }

        .settings-item:last-child {
            margin-bottom: 0;
        }

        .settings-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .toggle-checkbox {
            display: flex;
            align-items: center;
            cursor: pointer;
            gap: 6px;
        }

        .toggle-checkbox input {
            cursor: pointer;
        }

        .toggle-checkbox label {
            font-size: 0.8rem;
            cursor: pointer;
        }

        .interval-control {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .interval-input {
            width: 50px;
            padding: 4px 6px;
            border: 1px solid var(--line);
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .interval-input:focus {
            outline: none;
            border-color: var(--accent-start);
            box-shadow: 0 0 0 2px rgba(63, 125, 232, 0.1);
        }

        .content {
            padding: 12px;
            display: grid;
            gap: 10px;
            flex: 1;
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }

        .alert {
            border-radius: 10px;
            border: 1px solid transparent;
            padding: 10px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }

        .alert.error {
            border-color: #fecaca;
            background: #fff1f2;
            color: #b91c1c;
        }

        .alert.info {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .alert.success {
            border-color: #bbf7d0;
            background: #f0fdf4;
            color: #166534;
        }

        .skeleton-card {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: linear-gradient(-90deg, #f0f4fa 25%, #e0e8f5 50%, #f0f4fa 75%);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 60px;
        }

        .server-card {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #eef2f7;
            box-shadow: 0 2px 0 rgba(15, 23, 42, 0.02);
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            text-align: left;
            cursor: pointer;
            transition: var(--transition);
            animation: slideIn 0.3s ease;
        }

        .server-card:hover:not([disabled]) {
            transform: translateY(-3px);
            border-color: #9cb4da;
            box-shadow: 0 10px 28px rgba(42, 58, 88, 0.12);
        }

        .server-card:active:not([disabled]) {
            transform: translateY(-2px);
        }

        .server-card[disabled] {
            cursor: not-allowed;
            opacity: 0.6;
            pointer-events: none;
        }

        .server-main {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }

        .server-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .server-icon svg {
            width: 20px;
            height: 20px;
        }

        .server-icon.primary {
            background: #d8e8ff;
            color: #3b82f6;
        }

        .server-icon.backup1 {
            background: #ebe2ff;
            color: #8b5cf6;
        }

        .server-icon.backup2 {
            background: #d8f7f2;
            color: #0ea5a5;
        }

        .server-info {
            min-width: 0;
            flex: 1;
        }

        .server-name {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .server-caption {
            margin-top: 2px;
            font-size: 0.8rem;
            color: #55657f;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .server-side {
            text-align: right;
            flex-shrink: 0;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 70px;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.8rem;
            font-weight: 700;
            transition: var(--transition);
        }

        .status-pill.up {
            background: var(--good-bg);
            color: var(--good);
        }

        .status-pill.down {
            background: var(--bad-bg);
            color: var(--bad);
        }

        .status-meta {
            margin-top: 4px;
            color: #6d7b92;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .footer {
            text-align: center;
            color: #7d8aa0;
            font-size: 0.75rem;
            padding: 10px 12px;
            border-top: 1px solid #c8d0dd;
        }

        .footer a {
            color: #55657f;
            text-decoration: none;
            border-bottom: 1px dashed #8ea1c0;
        }

        @media (max-width: 640px) {
            .brand-title { font-size: 1.1rem; }
            .hero-title { font-size: 1rem; }
            .hero { padding: 12px; }
            .hero-top { margin-bottom: 8px; }
            .token-code { font-size: 0.75rem; padding: 5px 8px; }
            .token-code .label { font-size: 0.65rem; }
            .token-code .value { font-size: 0.85rem; }
            .refresh-btn { padding: 5px 8px; font-size: 0.65rem; }
        }

        @media (max-width: 420px) {
            .brand-title { font-size: 1rem; }
            .server-card { padding: 10px; }
            .server-name { font-size: 0.95rem; }
            .server-icon { width: 36px; height: 36px; }
        }
    </style>
</head>
<body>
    <main class="app-shell">
        <header class="hero" id="hero">
            <div class="hero-top">
                <div class="brand">
                    <div class="brand-logo" aria-hidden="true">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4v2h2a1 1 0 1 1 0 2H8a1 1 0 0 1 0-2h2v-2H6a2 2 0 0 1-2-2V5zm2 0v10h12V5H6zm2 2h8a1 1 0 0 1 0 2H8a1 1 0 1 1 0-2zm0 3h5a1 1 0 0 1 0 2H8a1 1 0 1 1 0-2z"/>
                        </svg>
                    </div>
                    <div class="brand-content">
                        <p class="brand-title">{{ $appName ?? 'Exambro' }}</p>
                        <p class="brand-subtitle" id="school-name">{{ $schoolName ?? 'Memuat...' }}</p>
                    </div>
                </div>
                <div class="hero-actions">
                    <button id="refresh-btn" class="refresh-btn" title="Refresh data">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                            <path d="M21 3v5h-5"></path>
                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                            <path d="M3 21v-5h5"></path>
                        </svg>
                        <span>Refresh</span>
                    </button>
                    <div style="position: relative;">
                        <button class="settings-toggle" id="settings-toggle" title="Settings">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l1.72-1.34c.15-.12.2-.34.1-.51l-1.63-2.82c-.1-.18-.32-.24-.51-.17l-2.03.8c-.42-.32-.9-.6-1.42-.8l-.3-2.16c-.04-.21-.21-.35-.42-.35h-3.26c-.21 0-.38.14-.42.35l-.3 2.16c-.52.2-1 .48-1.42.8l-2.03-.8c-.19-.07-.41 0-.51.17l-1.63 2.82c-.1.17-.05.39.1.51l1.72 1.34c-.05.3-.07.62-.07.94s.02.64.07.94l-1.72 1.34c-.15.12-.2.34-.1.51l1.63 2.82c.1.18.32.24.51.17l2.03-.8c.42.32.9.6 1.42.8l.3 2.16c.04.21.21.35.42.35h3.26c.21 0 .38-.14.42-.35l.3-2.16c.52-.2 1-.48 1.42-.8l2.03.8c.19.07.41 0 .51-.17l1.63-2.82c.1-.17.05-.39-.1-.51l-1.72-1.34zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                            </svg>
                        </button>
                        <div class="settings-panel" id="settings-panel">
                            <div class="settings-panel-content">
                                <div class="settings-item">
                                    <label class="settings-label">Auto Refresh</label>
                                    <div class="toggle-checkbox">
                                        <input type="checkbox" id="auto-refresh-toggle" checked>
                                        <label for="auto-refresh-toggle">Enabled</label>
                                    </div>
                                </div>
                                <div class="settings-item">
                                    <label class="settings-label">Refresh Interval</label>
                                    <div class="interval-control">
                                        <input type="number" id="interval-input" class="interval-input" value="20" min="5" max="120">
                                        <span style="font-size: 0.8rem;">sec</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hero-info">
                <div class="hero-left">
                    <h2 class="hero-title">Pilih Server Ujian</h2>
                    <p class="hero-subtitle">Ketuk server yang tersedia untuk memulai koneksi</p>
                </div>
                <div class="hero-right">
                    <div class="token-code" id="token-code">
                        <span class="label">PIN EXIT</span>
                        <span class="value" id="token-value">......</span>
                    </div>
                </div>
            </div>

            <div class="hero-meta">
                <div class="status-indicator">
                    <span>Status Token:</span>
                    <span class="indicator-dot" id="token-indicator"></span>
                    <span id="token-status">-</span>
                </div>
                <span>•</span>
                <div id="cbt-token-note">Token Soal: -</div>
                <span>•</span>
                <span id="checked-at">Dicek: -</span>
            </div>

            @if (!empty($canTogglePinVisibility) && $canTogglePinVisibility === true)
                <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.12); border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); font-size: 0.75rem;">
                    <form action="{{ route('cbt.exambro.token.visibility.toggle') }}" method="post" style="margin: 0;">
                        @csrf
                        <button type="submit" style="background: rgba(2,6,23,0.3); color: #fff; border: 1px solid rgba(255,255,255,0.3); border-radius: 5px; padding: 4px 8px; font-size: 0.7rem; font-weight: 600; cursor: pointer;">
                            {{ !empty($exambroTokenVisibleOnPage) && $exambroTokenVisibleOnPage ? '🔒 PIN Visible' : '🔐 PIN Hidden' }}
                        </button>
                    </form>
                </div>
            @endif
        </header>

        <section class="content" id="server-list"></section>

        <div class="footer">
            <p id="footer-note">Tersambung dengan baik • Sistem aktif</p>
        </div>
    </main>

    <template id="server-card-template">
        <button type="button" class="server-card">
            <div class="server-main">
                <div class="server-icon"></div>
                <div class="server-info">
                    <p class="server-name"></p>
                    <p class="server-caption"></p>
                </div>
            </div>
            <div class="server-side">
                <span class="status-pill"></span>
                <p class="status-meta"></p>
            </div>
        </button>
    </template>

    <template id="skeleton-template">
        <div class="skeleton-card"></div>
    </template>

    <script>
        // ============================================
        // Configuration & State
        // ============================================
        const config = {
            apiKey: '{{ addslashes(request()->query("key", "")) }}',
            apiBase: '{{ url("api/exambro-info") }}',
            refreshInterval: 20000,
            autoRefresh: true,
            parseTokenStatus: parseTokenStatusValue,
            parseWarningStatus: parseWarningStatusValue
        };

        let state = {
            isLoading: false,
            lastUpdate: null,
            refreshInterval: null,
            tokenStatus: false
        };

        // ============================================
        // DOM Elements
        // ============================================
        const elements = {
            refreshBtn: document.getElementById('refresh-btn'),
            settingsToggle: document.getElementById('settings-toggle'),
            settingsPanel: document.getElementById('settings-panel'),
            autoRefreshToggle: document.getElementById('auto-refresh-toggle'),
            intervalInput: document.getElementById('interval-input'),
            serverList: document.getElementById('server-list'),
            schoolName: document.getElementById('school-name'),
            tokenCode: document.getElementById('token-code'),
            tokenValue: document.getElementById('token-value'),
            tokenIndicator: document.getElementById('token-indicator'),
            tokenStatus: document.getElementById('token-status'),
            cbtTokenNote: document.getElementById('cbt-token-note'),
            checkedAt: document.getElementById('checked-at'),
            footerNote: document.getElementById('footer-note')
        };

        // ============================================
        // Utility Functions
        // ============================================
        function serverIconSvg() {
            return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor"><path d="M4 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm2 0v4h12V4H6zm-2 12a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4zm2 0v4h12v-4H6zm2-11h3a1 1 0 0 1 0 2H8a1 1 0 0 1 0-2zm0 12h3a1 1 0 0 1 0 2H8a1 1 0 1 1 0-2z"/></svg>';
        }

        function parseTokenStatusValue(val) {
            if (val === true || val === 1 || val === '1' || val === 'true' || val === 'active') return true;
            return false;
        }

        function parseWarningStatusValue(val) {
            return Number(val) === 1;
        }

        function createAlert(type, message) {
            const box = document.createElement('div');
            box.className = 'alert ' + type;
            box.textContent = message;
            return box;
        }

        function showSkeletonLoading() {
            elements.serverList.innerHTML = '';
            const template = document.getElementById('skeleton-template');
            for (let i = 0; i < 3; i++) {
                const skeleton = template.content.cloneNode(true);
                elements.serverList.appendChild(skeleton);
            }
        }

        function setLoading(loading) {
            state.isLoading = loading;
            elements.refreshBtn.classList.toggle('loading', loading);
            elements.refreshBtn.disabled = loading;
        }

        // ============================================
        // Event Listeners
        // ============================================
        elements.refreshBtn.addEventListener('click', () => {
            if (!state.isLoading) load();
        });

        elements.settingsToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            elements.settingsPanel.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.settings-toggle') && !e.target.closest('.settings-panel')) {
                elements.settingsPanel.classList.remove('active');
            }
        });

        elements.autoRefreshToggle.addEventListener('change', () => {
            config.autoRefresh = elements.autoRefreshToggle.checked;
            rescheduleRefresh();
        });

        elements.intervalInput.addEventListener('change', () => {
            const val = parseInt(elements.intervalInput.value, 10);
            if (val >= 5 && val <= 120) {
                config.refreshInterval = val * 1000;
                rescheduleRefresh();
            } else {
                elements.intervalInput.value = config.refreshInterval / 1000;
            }
        });

        // ============================================
        // Rendering Functions
        // ============================================
        function render(data) {
            const tokenActive = config.parseTokenStatus(data.exambro_active || data.token_status);
            const showWarning = config.parseWarningStatus(data.warning ?? data.peringatan ?? 1);
            
            state.tokenStatus = tokenActive;

            // Update header
            elements.schoolName.textContent = data.school || 'Sekolah Ujian';
            
            // Update token indicator
            elements.tokenIndicator.className = 'indicator-dot ' + (tokenActive ? 'active' : 'inactive');
            elements.tokenStatus.textContent = tokenActive ? 'AKTIF' : 'NON-AKTIF';

            // Update PIN/Token code
            const showPin = data.show_exambro_token_on_page === true || data.show_exambro_token_on_page === 1 || data.show_exambro_token_on_page === '1';
            if (showPin) {
                elements.tokenValue.textContent = data.token || '-';
                elements.tokenCode.classList.remove('hidden-by-admin');
            } else {
                elements.tokenValue.textContent = '••••••';
                elements.tokenCode.classList.add('hidden-by-admin');
            }

            elements.cbtTokenNote.textContent = 'Token Soal: ' + (data.cbt_token || data.token_soal || '-');

            // Clear server list
            elements.serverList.innerHTML = '';

            // Add alert if needed
            if (showWarning) {
                if (!tokenActive) {
                    elements.serverList.appendChild(
                        createAlert('error', '⚠️ Token Exambro non-aktif. Server Online tetap dapat dipilih.')
                    );
                } else {
                    elements.serverList.appendChild(
                        createAlert('info', '✓ Server yang Online dapat dipilih.')
                    );
                }
            }

            // Build server list
            const servers = Array.isArray(data.servers) && data.servers.length > 0
                ? data.servers.map(s => ({
                    key: s.key,
                    label: s.name,
                    url: s.url,
                    status: s.status,
                    selectable: s.selectable === true
                }))
                : [
                    { key: 'primary', label: 'Server Utama', url: data.server_utama, status: data.server_utama_status, selectable: data.server_utama_status === 'up' && !!data.server_utama },
                    { key: 'backup1', label: 'Server 2', url: data.server_backup1, status: data.server_backup1_status, selectable: data.server_backup1_status === 'up' && !!data.server_backup1 },
                    { key: 'backup2', label: 'Server 3', url: data.server_backup2, status: data.server_backup2_status, selectable: data.server_backup2_status === 'up' && !!data.server_backup2 }
                ];

            servers.forEach((server, idx) => {
                const template = document.getElementById('server-card-template');
                const card = template.content.firstElementChild.cloneNode(true);

                const icon = card.querySelector('.server-icon');
                icon.classList.add(server.key);
                icon.innerHTML = serverIconSvg();

                card.querySelector('.server-name').textContent = server.label;

                const isOnline = server.status === 'up';
                const statusPill = card.querySelector('.status-pill');
                const statusMeta = card.querySelector('.status-meta');

                statusPill.classList.add(isOnline ? 'up' : 'down');
                statusPill.textContent = isOnline ? 'Online' : 'Down';
                statusMeta.textContent = isOnline ? 'Ketuk untuk terhubung' : 'Server tidak tersedia';

                const selectable = isOnline && !!server.url && server.selectable !== false;
                if (!selectable) {
                    card.setAttribute('disabled', 'disabled');
                    card.disabled = true;
                } else {
                    card.addEventListener('click', () => {
                        window.location.href = server.url;
                    });
                }

                elements.serverList.appendChild(card);
            });

            // Update timestamp
            const now = new Date(data.checked_at);
            elements.checkedAt.textContent = 'Dicek: ' + now.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            state.lastUpdate = now;
        }

        // ============================================
        // API & Loading
        // ============================================
        function load() {
            setLoading(true);
            showSkeletonLoading();

            const url = config.apiBase + (config.apiBase.indexOf('?') !== -1 ? '&' : '?') + '_t=' + Date.now();

            fetch(url, {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Exambro-Key': config.apiKey
                }
            })
                .then(response => {
                    if (!response.ok) throw new Error('Akses ditolak atau API tidak dapat diakses.');
                    return response.json();
                })
                .then(payload => {
                    if (payload.status === 'error') throw new Error(payload.message || 'Gagal memuat data.');
                    render(payload);
                    elements.footerNote.textContent = '✓ Tersambung dengan baik • Sistem aktif';
                })
                .catch(error => {
                    elements.serverList.innerHTML = '';
                    elements.serverList.appendChild(createAlert('error', '❌ ' + (error.message || 'Gagal memuat data.')));
                    elements.footerNote.textContent = '✗ Gagal terhubung • Coba muat ulang';
                })
                .finally(() => setLoading(false));
        }

        function rescheduleRefresh() {
            if (state.refreshInterval) clearInterval(state.refreshInterval);
            if (config.autoRefresh) {
                state.refreshInterval = setInterval(load, config.refreshInterval);
            }
        }

        // ============================================
        // Initialize
        // ============================================
        load();
        rescheduleRefresh();
    </script>
</body>
</html>
