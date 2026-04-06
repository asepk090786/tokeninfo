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

        .server-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
        }

        .server-card {
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 14px 14px 12px;
            display: grid;
            grid-template-rows: auto auto 1fr;
            gap: 12px;
            min-height: 348px;
        }

        .server-card.up {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .server-card.down {
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
            max-width: 210px;
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
            min-height: 210px;
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
            .server-grid { grid-template-columns: 1fr; }
            .hero { border-radius: 16px; padding: 18px; }
            .server-card { min-height: 0; }
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
                <p class="strip-value">{{ $info->cbt_token }}</p>
                <p class="strip-meta">
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
            <p class="server-note">Scan QR pada server yang statusnya UP untuk akses cepat.</p>
            <div class="server-grid">
                @foreach ($servers as $server)
                    <article class="server-card {{ $server['status_class'] }}">
                        <div class="server-head">
                            <h3 class="server-name">{{ $server['name'] }}</h3>
                            <span class="badge {{ $server['status_class'] }}">{{ $server['status_label'] }}</span>
                        </div>
                        <div class="server-stats">
                            <span>Core {{ $server['core'] }} • RAM {{ $server['ram'] }}</span>
                            <span>{{ $server['active_user_count'] ?? $server['login_count'] }} / {{ $server['capacity'] }} peserta aktif (2m)</span>
                        </div>
                        @if (!empty($server['qr_svg']))
                            <div class="server-qrcode" aria-label="QR {{ $server['name'] }}">
                                {!! $server['qr_svg'] !!}
                            </div>
                        @else
                            <p class="server-qr-note">QR hanya tersedia saat server Online.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="actions">
            <a class="btn btn-soft" href="{{ route('cbt.admin.login') }}">Login Admin</a>
        </section>

        <section class="refresh-bar">
            <span>Auto-refresh aktif</span>
            <span>Update berikutnya: <span id="countdown" class="refresh-count">05:00</span></span>
        </section>
    </div>

    <script>
        (function () {
            var total = 300;
            var remaining = total;
            var countdown = document.getElementById('countdown');

            function tick() {
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                countdown.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                remaining -= 1;
                if (remaining < 0) {
                    window.location.reload();
                }
            }

            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
