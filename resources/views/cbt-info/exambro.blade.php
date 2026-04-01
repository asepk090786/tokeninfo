<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exambro Client</title>
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
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background: linear-gradient(165deg, #d5dae2 0%, #e7ebf2 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 0;
        }

        .app-shell {
            width: min(760px, 100%);
            margin: 0 auto;
            min-height: 100vh;
            background: var(--bg);
            border-left: 1px solid #bcc6d4;
            border-right: 1px solid #bcc6d4;
        }

        .hero {
            background: linear-gradient(100deg, var(--accent-start), var(--accent-end));
            color: #fff;
            padding: 18px 18px 16px;
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.2);
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .brand-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: grid;
            place-items: center;
        }

        .brand-logo svg {
            width: 24px;
            height: 24px;
            fill: #f8fbff;
        }

        .brand-title {
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: 0.2px;
            line-height: 1;
        }

        .brand-subtitle {
            margin-top: 4px;
            opacity: 0.95;
            font-size: clamp(0.98rem, 1.8vw, 1.18rem);
            font-weight: 700;
            line-height: 1.25;
        }

        .token-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 0.76rem;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.25);
            text-transform: uppercase;
        }

        .token-pill .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #facc15;
        }

        .token-pill.active .dot {
            background: #22c55e;
            box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.22);
        }

        .token-pill.inactive .dot {
            background: #fb7185;
            box-shadow: 0 0 0 5px rgba(251, 113, 133, 0.2);
        }

        .token-block {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }

        .token-code-note {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-align: right;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            padding: 7px 12px;
            box-shadow: 0 3px 10px rgba(29, 78, 216, 0.18);
        }

        .token-code-note .label {
            opacity: 0.9;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .token-code-note .value {
            font-size: 1.18rem;
            font-weight: 800;
            line-height: 1;
            color: #ffffff;
            text-shadow: 0 1px 8px rgba(15, 23, 42, 0.35);
        }

        .hero-note {
            margin-top: 16px;
        }

        .hero-note h2 {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .hero-note p {
            font-size: 0.95rem;
            margin-top: 5px;
            opacity: 0.82;
        }

        .warning-status {
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 7px 12px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.88rem;
            font-weight: 700;
        }

        .warning-status .value {
            font-size: 0.95rem;
            font-weight: 800;
        }

        .warning-status.on .value {
            color: #bbf7d0;
        }

        .warning-status.off .value {
            color: #fee2e2;
        }

        .token-subnote {
            margin-top: 8px;
            font-size: 0.83rem;
            opacity: 0.85;
        }

        .pin-toggle-box {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.28);
        }

        .pin-toggle-box p {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 600;
            opacity: 0.95;
        }

        .pin-toggle-actions {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pin-toggle-state {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            border: 1px solid rgba(255, 255, 255, 0.35);
            background: rgba(255, 255, 255, 0.22);
            color: #fff;
        }

        .pin-toggle-btn {
            border: 1px solid rgba(255, 255, 255, 0.38);
            background: rgba(2, 6, 23, 0.25);
            color: #fff;
            border-radius: 8px;
            padding: 7px 11px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
        }

        .token-code-note.hidden-by-admin {
            opacity: 0.75;
            border-style: dashed;
        }

        .content {
            padding: 14px;
            display: grid;
            gap: 12px;
        }

        .alert {
            border-radius: 14px;
            border: 1px solid transparent;
            padding: 12px 13px;
            font-size: 0.85rem;
            font-weight: 600;
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

        .server-card {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #eef2f7;
            box-shadow: 0 2px 0 rgba(15, 23, 42, 0.02);
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            text-align: left;
            cursor: pointer;
            transition: transform 140ms ease, border-color 140ms ease, box-shadow 140ms ease;
        }

        .server-card:hover:not([disabled]) {
            transform: translateY(-1px);
            border-color: #9cb4da;
            box-shadow: 0 5px 16px rgba(42, 58, 88, 0.08);
        }

        .server-card[disabled] {
            cursor: not-allowed;
            opacity: 0.65;
            pointer-events: none;
        }

        .server-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .server-icon {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            display: grid;
            place-items: center;
        }

        .server-icon svg {
            width: 22px;
            height: 22px;
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

        .server-name {
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .server-caption {
            margin-top: 3px;
            font-size: 0.9rem;
            color: #55657f;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 340px;
        }

        .server-side {
            text-align: right;
            flex-shrink: 0;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 78px;
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 0.92rem;
            font-weight: 700;
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
            margin-top: 6px;
            color: #6d7b92;
            font-size: 0.89rem;
            font-weight: 600;
        }

        .footer {
            text-align: center;
            color: #7d8aa0;
            font-size: 0.83rem;
            padding: 0 14px 16px;
        }

        .footer a {
            color: #55657f;
            text-decoration: none;
            border-bottom: 1px dashed #8ea1c0;
        }

        @media (max-width: 640px) {
            .brand-title { font-size: 1.45rem; }
            .hero-note h2 { font-size: 1.35rem; }
            .server-caption { max-width: 200px; }
            .status-pill { min-width: 72px; font-size: 0.84rem; }
            .status-meta { font-size: 0.8rem; }
            .token-code-note { font-size: 0.9rem; padding: 6px 10px; }
            .token-code-note .label { font-size: 0.8rem; }
            .token-code-note .value { font-size: 1rem; }
        }

        @media (max-width: 420px) {
            .server-card { padding: 12px; }
            .server-name { font-size: 1rem; }
            .server-caption { font-size: 0.82rem; }
            .server-icon { width: 40px; height: 40px; }
        }
    </style>
</head>
<body>
    <main class="app-shell">
        <header class="hero">
            <div class="brand">
                <div class="brand-left">
                    <div class="brand-logo" aria-hidden="true">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4v2h2a1 1 0 1 1 0 2H8a1 1 0 0 1 0-2h2v-2H6a2 2 0 0 1-2-2V5zm2 0v10h12V5H6zm2 2h8a1 1 0 0 1 0 2H8a1 1 0 1 1 0-2zm0 3h5a1 1 0 0 1 0 2H8a1 1 0 1 1 0-2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="brand-title">{{ $appName ?? 'Exambro Client' }}</p>
                        <p class="brand-subtitle" id="school-name">{{ $schoolName ?? 'Memuat nama sekolah...' }}</p>
                    </div>
                </div>
                <div class="token-block">
                    <span id="token-pill" class="token-pill">
                        <span class="dot"></span>
                        <span id="token-pill-text">TOKEN ...</span>
                    </span>
                    <p class="token-code-note" id="token-code-note">
                        <span class="label">PIN EXIT EXAMBRO</span>
                        <span>:</span>
                        <span class="value" id="token-code-value">......</span>
                    </p>
                </div>
            </div>

            <div class="hero-note">
                <h2>Pilih Server Ujian</h2>
                <p>Ketuk server yang tersedia untuk memulai koneksi</p>
                <p class="warning-status" id="warning-status">
                    <span>STATUS PERINGATAN:</span>
                    <span class="value" id="warning-status-value">-</span>
                </p>
                <p class="token-subnote" id="cbt-token-note">Token Soal CBT: -</p>

                @if (!empty($canTogglePinVisibility) && $canTogglePinVisibility === true)
                    <div class="pin-toggle-box">
                        <p>Admin Control: Tampilkan Kode/PIN Exambro di halaman ini</p>
                        <div class="pin-toggle-actions">
                            <span class="pin-toggle-state">{{ !empty($exambroTokenVisibleOnPage) && $exambroTokenVisibleOnPage ? 'ON' : 'OFF' }}</span>
                            <form action="{{ route('cbt.exambro.token.visibility.toggle') }}" method="post" style="margin: 0;">
                                @csrf
                                <button type="submit" class="pin-toggle-btn">{{ !empty($exambroTokenVisibleOnPage) && $exambroTokenVisibleOnPage ? 'Ubah ke OFF' : 'Ubah ke ON' }}</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

        </header>

        <section class="content" id="server-list"></section>

        <div class="footer">
            <p id="checked-at">Terakhir dicek: -</p>
        </div>
    </main>

    <template id="server-card-template">
        <button type="button" class="server-card">
            <div class="server-main">
                <div class="server-icon"></div>
                <div>
                    <p class="server-name"></p>
                </div>
            </div>
            <div class="server-side">
                <span class="status-pill"></span>
                <p class="status-meta"></p>
            </div>
        </button>
    </template>

    <script>
        // Key diinjeksi langsung dari server agar tidak bergantung URLSearchParams
        var apiKey = '{{ addslashes(request()->query("key", "")) }}';
        var API = '{{ url("api/exambro-info") }}' + (apiKey ? ('?key=' + encodeURIComponent(apiKey)) : '');

        function serverIconSvg() {
            return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="currentColor"><path d="M4 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm2 0v4h12V4H6zm-2 12a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4zm2 0v4h12v-4H6zm2-11h3a1 1 0 0 1 0 2H8a1 1 0 0 1 0-2zm0 12h3a1 1 0 0 1 0 2H8a1 1 0 1 1 0-2z"/></svg>';
        }

        function makeAlert(type, message) {
            var box = document.createElement('div');
            box.className = 'alert ' + type;
            box.textContent = message;
            return box;
        }

        function render(data) {
            var rawActive = data.exambro_active;
            var tokenActive = rawActive === true
                || rawActive === 1
                || rawActive === '1'
                || rawActive === 'true'
                || rawActive === 'active'
                || data.token_status === 'active';
            var warningValue = Number(data.warning != null ? data.warning : (data.peringatan != null ? data.peringatan : 1));
            var showWarning = warningValue === 1;
            var serverList = document.getElementById('server-list');
            var checkedAt = document.getElementById('checked-at');
            var schoolName = document.getElementById('school-name');
            var tokenPill = document.getElementById('token-pill');
            var tokenPillText = document.getElementById('token-pill-text');
            var tokenCodeValue = document.getElementById('token-code-value');
            var warningStatus = document.getElementById('warning-status');
            var warningStatusValue = document.getElementById('warning-status-value');
            var cbtTokenNote = document.getElementById('cbt-token-note');
            var tokenCodeNote = document.getElementById('token-code-note');

            serverList.innerHTML = '';
            schoolName.textContent = data.school || 'Sekolah Ujian';

            tokenPill.classList.remove('active', 'inactive');
            tokenPill.classList.add(tokenActive ? 'active' : 'inactive');
            tokenPillText.textContent = tokenActive ? 'Token Aktif' : 'Token Non-Aktif';
            var showPinOnPage = data.show_exambro_token_on_page === true
                || data.show_exambro_token_on_page === 1
                || data.show_exambro_token_on_page === '1'
                || data.show_exambro_token_on_page === 'true';

            if (showPinOnPage) {
                tokenCodeValue.textContent = data.token || '-';
                tokenCodeNote.classList.remove('hidden-by-admin');
            } else {
                tokenCodeValue.textContent = 'DISEMBUNYIKAN';
                tokenCodeNote.classList.add('hidden-by-admin');
            }
            cbtTokenNote.textContent = 'Token Soal CBT: ' + (data.cbt_token || data.token_soal || '-');

            warningStatus.classList.remove('on', 'off');
            warningStatus.classList.add(showWarning ? 'on' : 'off');
            warningStatusValue.textContent = showWarning ? 'ON' : 'OFF';

            if (showWarning) {
                if (!tokenActive) {
                    serverList.appendChild(
                        makeAlert('error', 'Token Exambro non-aktif. Namun pilihan server yang Online tetap dapat dipilih.')
                    );
                } else {
                    serverList.appendChild(
                        makeAlert('info', 'Server yang berstatus Online dapat dipilih. Server Down akan otomatis nonaktif.')
                    );
                }
            }

            var servers = Array.isArray(data.servers) && data.servers.length > 0
                ? data.servers.map(function (server) {
                    return {
                        key: server.key,
                        label: server.name,
                        url: server.url,
                        status: server.status,
                        selectable: server.selectable === true
                    };
                })
                : [
                    { key: 'primary', label: 'Server Utama', url: data.server_utama, status: data.server_utama_status, selectable: data.server_utama_status === 'up' && !!data.server_utama },
                    { key: 'backup1', label: 'Server 2', url: data.server_backup1, status: data.server_backup1_status, selectable: data.server_backup1_status === 'up' && !!data.server_backup1 },
                    { key: 'backup2', label: 'Server 3', url: data.server_backup2, status: data.server_backup2_status, selectable: data.server_backup2_status === 'up' && !!data.server_backup2 }
                ];

            servers.forEach(function (server) {
                var tpl = document.getElementById('server-card-template');
                var card = tpl.content.firstElementChild.cloneNode(true);

                var icon = card.querySelector('.server-icon');
                icon.classList.add(server.key);
                icon.innerHTML = serverIconSvg();

                card.querySelector('.server-name').textContent = server.label;

                var statusPill = card.querySelector('.status-pill');
                var statusMeta = card.querySelector('.status-meta');
                var isOnline = server.status === 'up';
                statusPill.classList.add(isOnline ? 'up' : 'down');
                statusPill.textContent = isOnline ? 'Online' : 'Down';

                if (isOnline) {
                    statusMeta.textContent = 'Ketuk untuk terhubung';
                } else {
                    statusMeta.textContent = 'Server tidak tersedia';
                }

                var selectable = isOnline && !!server.url && server.selectable !== false;
                if (!selectable) {
                    card.setAttribute('disabled', 'disabled');
                    card.setAttribute('aria-disabled', 'true');
                    card.setAttribute('tabindex', '-1');
                    card.disabled = true;
                } else {
                    card.addEventListener('click', function () {
                        window.location.href = server.url;
                    });
                }

                serverList.appendChild(card);
            });

            checkedAt.textContent = 'Terakhir dicek: ' + new Date(data.checked_at).toLocaleString('id-ID');
        }

        function load() {
            var requestUrl = API
                + (API.indexOf('?') !== -1 ? '&' : '?')
                + '_t=' + Date.now();

            fetch(requestUrl, {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Exambro-Key': apiKey
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Akses ditolak atau API tidak dapat diakses.');
                    }

                    return response.json();
                })
                .then(function (payload) {
                    if (payload.status === 'error') {
                        throw new Error(payload.message || 'Gagal memuat data Exambro.');
                    }

                    render(payload);
                })
                .catch(function (error) {
                    var serverList = document.getElementById('server-list');
                    serverList.innerHTML = '';
                    serverList.appendChild(makeAlert('error', error && error.message ? error.message : 'Gagal memuat data.'));
                });
        }

        load();
        setInterval(load, 30000);
    </script>
</body>
</html>
