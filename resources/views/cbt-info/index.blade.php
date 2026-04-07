<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informasi CBT</title>
    <style>
        :root {
            --bg-top: #f4f8ff;
            --bg-bottom: #ecfdf5;
            --ink: #10233b;
            --muted: #4f647d;
            --panel: #ffffff;
            --line: #d6e3f2;
            --brand: #0f766e;
            --brand-deep: #115e59;
            --ok: #059669;
            --down: #be123c;
            --warn: #ca8a04;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: "Trebuchet MS", "Segoe UI", Tahoma, sans-serif;
            background:
                radial-gradient(circle at 8% 10%, #dbeafe 0%, transparent 34%),
                radial-gradient(circle at 92% 12%, #dcfce7 0%, transparent 32%),
                linear-gradient(165deg, var(--bg-top), var(--bg-bottom));
            padding: 22px;
        }

        .shell {
            width: min(1100px, 100%);
            margin: 0 auto;
            display: grid;
            gap: 16px;
        }

        .hero {
            background: linear-gradient(130deg, #0f766e, #0e7490);
            color: #ffffff;
            border-radius: 24px;
            padding: 26px;
            box-shadow: 0 22px 44px rgba(15, 118, 110, 0.24);
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(1.6rem, 2.4vw, 2.2rem);
            letter-spacing: 0.4px;
        }

        .hero .school {
            margin: 10px 0 0;
            font-size: clamp(1rem, 1.8vw, 1.3rem);
            font-weight: 700;
            opacity: 0.98;
        }

        .hero .app {
            margin: 4px 0 0;
            font-size: 0.95rem;
            opacity: 0.92;
        }

        .status-strip {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .strip-card {
            border-radius: 16px;
            border: 1px solid var(--line);
            background: var(--panel);
            padding: 14px 16px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.07);
        }

        .strip-label {
            margin: 0;
            color: var(--muted);
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
        }

        .strip-value {
            margin: 8px 0 0;
            font-size: clamp(1.4rem, 3.6vw, 2.4rem);
            font-weight: 800;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .strip-meta {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.86rem;
        }

        .exambro-active {
            color: var(--ok);
        }

        .exambro-inactive {
            color: var(--down);
        }

        .desc {
            border-radius: 16px;
            border: 1px solid var(--line);
            background: #f8fbff;
            padding: 16px;
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .server-section {
            border-radius: 18px;
            border: 1px solid var(--line);
            background: var(--panel);
            padding: 18px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }

        .server-title {
            margin: 0;
            font-size: 1.05rem;
        }

        .server-note {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .server-layout {
            margin-top: 14px;
            display: grid;
            grid-template-columns: 230px 1fr;
            gap: 14px;
            align-items: start;
        }

        .single-qr {
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 12px;
            display: grid;
            gap: 10px;
            place-items: center;
        }

        .server-list {
            display: grid;
            gap: 10px;
        }

        .server-item {
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 12px;
            display: grid;
            gap: 8px;
        }

        .server-item.up {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .server-item.down {
            background: #fff1f2;
            border-color: #fecdd3;
        }

        .server-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .server-name {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 800;
        }

        .badge {
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge.up {
            background: #dcfce7;
            color: #166534;
        }

        .badge.down {
            background: #ffe4e6;
            color: #9f1239;
        }

        .server-stats {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            color: var(--muted);
            font-size: 0.82rem;
            line-height: 1.35;
            min-height: 38px;
        }

        .server-stats span:last-child {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .server-qrcode {
            width: 100%;
            max-width: 200px;
            aspect-ratio: 1 / 1;
            margin: 0 auto;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 7px;
            background: #fff;
            display: grid;
            place-items: center;
        }

        .server-qrcode svg {
            width: 100%;
            height: 100%;
        }

        .server-qr-note {
            margin: 0;
            text-align: center;
            font-size: 0.8rem;
            color: var(--muted);
            background: #f8fafc;
            border: 1px dashed var(--line);
            border-radius: 10px;
            padding: 12px;
            display: grid;
            place-items: center;
            min-height: 200px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            text-decoration: none;
            border-radius: 10px;
            border: 1px solid var(--line);
            padding: 10px 14px;
            font-size: 0.88rem;
            font-weight: 700;
            transition: transform 140ms ease, box-shadow 140ms ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.12);
        }

        .btn-primary {
            background: var(--brand-deep);
            border-color: var(--brand-deep);
            color: #fff;
        }

        .btn-soft {
            background: #fff;
            color: var(--ink);
        }

        .refresh-bar {
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 10px 12px;
            font-size: 0.84rem;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .refresh-count {
            color: var(--brand);
            font-weight: 700;
        }

        @media (max-width: 860px) {
            body { padding: 14px; }
            .status-strip { grid-template-columns: 1fr; }
            .server-layout { grid-template-columns: 1fr; }
            .hero { border-radius: 16px; padding: 18px; }
            .server-stats { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <h1>Informasi Token dan Server CBT</h1>
            <p class="school">{{ $info->school }}</p>
            <p class="app">{{ $info->app_name }}</p>
        </section>

        <section class="status-strip">
            <article class="strip-card">
                <p class="strip-label">Token CBT</p>
                <p class="strip-value" id="token-cbt-value">{{ $info->cbt_token }}</p>
                <p class="strip-meta" id="token-cbt-meta">
                    @if (!empty($info->token_valid_until))
                        Berlaku sampai {{ $info->token_valid_until }}
                    @elseif (!empty($info->token_updated_at))
                        Diperbarui {{ $info->token_updated_at }}
                    @else
                        Waktu pembaruan token belum tersedia
                    @endif
                </p>
            </article>
            <article class="strip-card">
                <p class="strip-label">Status Exambro</p>
                <p class="strip-value {{ $exambroActive ? 'exambro-active' : 'exambro-inactive' }}">
                    {{ $exambroActive ? 'AKTIF' : 'NON-AKTIF' }}
                </p>
                <p class="strip-meta">PIN Exambro: <strong>{{ $info->exambro_token ?: '-' }}</strong></p>
            </article>
        </section>

        <section class="desc">
            {{ $info->description ?: 'Belum ada keterangan tambahan dari admin.' }}
        </section>

        <section class="server-section">
            <h2 class="server-title">Daftar Server CBT</h2>
            <p class="server-note">Semua QR mengarah ke halaman Exambro untuk akses cepat.</p>
            @php
                $singleQr = collect($servers)->pluck('qr_svg')->filter()->first();
            @endphp
            <div class="server-layout">
                <div class="single-qr">
                    @if (!empty($singleQr))
                        <div class="server-qrcode" aria-label="QR Exambro">
                            {!! $singleQr !!}
                        </div>
                    @else
                        <p class="server-qr-note">QR belum tersedia.</p>
                    @endif
                </div>

                <div class="server-list">
                    @foreach ($servers as $server)
                        <article class="server-item {{ $server['status_class'] }}">
                        <div class="server-head">
                            <h3 class="server-name">{{ $server['name'] }}</h3>
                            <span class="badge {{ $server['status_class'] }}">{{ $server['status_label'] }}</span>
                        </div>
                        <div class="server-stats">
                            <span>Core {{ $server['core'] }} • RAM {{ $server['ram'] }}</span>
                            <span>{{ $server['active_user_count'] ?? $server['login_count'] }} / {{ $server['capacity'] }} peserta aktif (2m)</span>
                        </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="actions">
            <a class="btn btn-soft" href="{{ route('cbt.admin.login') }}">Login Admin</a>
        </section>

        <section class="refresh-bar">
            <span>Auto-refresh aktif (30 detik)</span>
            <span>Update berikutnya: <span id="countdown" class="refresh-count">00:30</span></span>
        </section>
    </div>

    <script>
        (function () {
            var total = 30;
            var remaining = total;
            var countdown = document.getElementById('countdown');
            var tokenEl  = document.getElementById('token-cbt-value');
            var metaEl   = document.getElementById('token-cbt-meta');
            var lastToken = tokenEl ? tokenEl.textContent.trim() : '';

            function updateTokenFromApi() {
                fetch('{{ route('cbt.token.info') }}?_=' + Date.now(), {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    var newToken = (data.cbt_token || data.token || '').toString().trim();
                    if (newToken && newToken !== lastToken) {
                        if (tokenEl) {
                            tokenEl.textContent = newToken;
                            tokenEl.style.transition = 'color 0.4s';
                            tokenEl.style.color = '#059669';
                            setTimeout(function () { tokenEl.style.color = ''; }, 2000);
                        }
                        if (metaEl && data.token_updated_at) {
                            metaEl.textContent = 'Diperbarui ' + data.token_updated_at;
                        }
                        lastToken = newToken;
                    }
                    remaining = total;
                })
                .catch(function () {
                    /* Gagal fetch — coba lagi di interval berikutnya */
                });
            }

            function tick() {
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                countdown.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                remaining -= 1;
                if (remaining < 0) {
                    updateTokenFromApi();
                }
            }

            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
