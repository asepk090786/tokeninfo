<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informasi Token dan URL CBT</title>
    <style>
        :root {
            --bg: #f3f8ff;
            --card: #ffffff;
            --ink: #0f172a;
            --muted: #475569;
            --accent: #0f766e;
            --accent-2: #0e7490;
            --line: #dbe4ef;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 20% 20%, #e0f2fe 0%, transparent 35%),
                radial-gradient(circle at 80% 10%, #dcfce7 0%, transparent 30%),
                linear-gradient(160deg, #f8fbff 0%, var(--bg) 70%);
            min-height: 100vh;
            padding: 24px;
        }

        .wrapper {
            width: min(900px, 100%);
            margin: 0 auto;
        }

        .hero {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(15, 118, 110, 0.2);
            animation: fadeInUp 500ms ease-out;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(1.5rem, 2.4vw, 2.1rem);
            letter-spacing: 0.4px;
        }

        .hero p {
            margin: 10px 0 0;
            opacity: 0.95;
        }

        .school-name {
            margin-top: 10px;
            font-size: clamp(1.15rem, 2.4vw, 1.55rem);
            font-weight: 800;
            letter-spacing: 0.4px;
            line-height: 1.25;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
            margin-top: 20px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            animation: fadeInUp 650ms ease-out;
        }

        .label {
            margin: 0;
            font-size: 0.85rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .value {
            margin: 10px 0 0;
            font-size: clamp(1.1rem, 1.8vw, 1.4rem);
            font-weight: 700;
            line-height: 1.4;
            word-break: break-word;
        }

        .token-value {
            font-size: clamp(2rem, 5vw, 3.2rem);
            letter-spacing: 1px;
            line-height: 1.1;
        }

        .token-card {
            text-align: center;
        }

        .token-meta {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .desc {
            margin-top: 18px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 20px;
            color: var(--muted);
            line-height: 1.7;
            animation: fadeInUp 800ms ease-out;
        }

        .server-wrap {
            margin-top: 18px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 20px;
            animation: fadeInUp 900ms ease-out;
        }

        .server-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .server-card {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            background: #fff;
            text-align: center;
        }

        .server-card.up {
            background: #ecfdf3;
            border-color: #a7f3d0;
        }

        .server-card.down {
            background: #fff1f2;
            border-color: #fecdd3;
        }

        .server-name {
            margin: 0;
            font-size: 0.8rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }

        .server-status {
            margin: 8px 0 0;
            font-weight: 700;
            font-size: 1rem;
        }

        .server-status.up {
            color: #047857;
        }

        .server-status.down {
            color: #be123c;
        }

        .qr-image {
            width: min(260px, 100%);
            aspect-ratio: 1 / 1;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 10px;
        }

        .qr-help {
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .refresh-bar {
            margin-top: 18px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .refresh-bar .countdown {
            font-weight: 600;
            color: var(--accent);
        }

        .refresh-bar .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            margin-right: 6px;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .fade-update {
            animation: fadeFlash 600ms ease;
        }

        @keyframes fadeFlash {
            0% { opacity: 0.3; transform: scale(0.97); }
            100% { opacity: 1; transform: scale(1); }
        }

        .actions {
            margin-top: 18px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            display: inline-block;
            padding: 11px 16px;
            border-radius: 12px;
            font-weight: 600;
            border: 1px solid var(--line);
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        .btn-primary {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
        }

        .btn-secondary {
            color: var(--ink);
            background: #fff;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 760px) {
            body {
                padding: 14px;
            }

            .hero,
            .card,
            .desc {
                border-radius: 14px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .server-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <section class="hero">
            <h1>Informasi Token dan URL CBT</h1>
            <p class="school-name">{{ $info->school }}</p>
            <p><strong>{{ $info->app_name }}</strong></p>
            <p>Pastikan token dan alamat CBT sesuai dengan pengumuman terbaru sekolah.</p>
        </section>

        <section class="grid">
            <article class="card" id="exambro-card" style="text-align: center; padding: 16px 20px; background: {{ $exambroActive ? '#ecfdf3' : '#fff1f2' }}; border-color: {{ $exambroActive ? '#a7f3d0' : '#fecdd3' }};">
                <p class="label" id="exambro-label" style="color: {{ $exambroActive ? '#047857' : '#be123c' }};">Status Token Exambro</p>
                <p class="value" id="exambro-value" style="font-size: clamp(1.4rem, 3vw, 2rem); color: {{ $exambroActive ? '#047857' : '#be123c' }};">
                    <span id="exambro-dot" style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background: {{ $exambroActive ? '#10b981' : '#f43f5e' }}; vertical-align: middle; margin-right: 8px; box-shadow: 0 0 8px {{ $exambroActive ? 'rgba(16,185,129,0.5)' : 'rgba(244,63,94,0.4)' }};"></span>
                    <span id="exambro-text">{{ $exambroActive ? 'AKTIF' : 'NON-AKTIF' }}</span>
                </p>
                <p id="exambro-hint" style="margin: 8px 0 0; color: {{ $exambroActive ? '#047857' : '#be123c' }}; font-size: 0.92rem; opacity: 0.85;">
                    {{ $exambroActive ? 'Token dan PIN Exambro dapat digunakan.' : 'Token dan PIN Exambro sedang tidak aktif.' }}
                </p>
            </article>

            <article class="card token-card">
                <p class="label">Token CBT</p>
                <p class="value token-value" id="token-value">{{ $info->cbt_token }}</p>
                <p class="token-meta" id="token-meta">
                @if (!empty($info->token_valid_until))
                    Berlaku sampai: {{ $info->token_valid_until }}
                @elseif (!empty($info->token_updated_at))
                    Terakhir diperbarui: {{ $info->token_updated_at }}
                @else
                    Waktu berlaku token belum tersedia.
                @endif
                </p>
            </article>

            <article class="card token-card" id="exambro-token-card" style="background: #f8fafc; border-color: #cbd5e1;">
                <p class="label">PIN Exambro (Exit PIN)</p>
                <p class="value token-value" id="exambro-token-value">{{ $info->exambro_token ?: '-' }}</p>
                <p class="token-meta">PIN khusus Exambro, terpisah dari token akses soal CBT.</p>
            </article>
        </section>

        <section class="desc" id="desc-section">
            {{ $info->description ?: 'Tidak ada keterangan tambahan.' }}
        </section>

        <section class="server-wrap">
            <p class="label">QR URL CBT Utama dan Backup</p>
            <section class="server-grid" id="server-grid">
                @foreach ($servers as $server)
                    <article class="server-card {{ $server['status_class'] }}" data-server-key="{{ $server['key'] }}">
                        <p class="server-name">{{ $server['name'] }}</p>
                        <p class="server-status {{ $server['status_class'] }}">{{ $server['status_label'] }}</p>
                        <img
                            class="qr-image"
                            src="{{ $server['qr_url'] }}"
                            alt="QR code {{ strtolower($server['name']) }}"
                        >
                    </article>
                @endforeach
            </section>
            <p class="qr-help">Scan QR code sesuai server yang tersedia.</p>
        </section>

        <section class="actions">
            <a class="btn btn-secondary" href="{{ route('cbt.admin.login') }}">Login Admin</a>
        </section>

        <section class="refresh-bar">
            <span><span class="status-dot"></span> Auto-refresh aktif</span>
            <span>Update berikutnya: <span class="countdown" id="countdown">5:00</span></span>
        </section>
    </div>

    <script>
        (function () {
            var INTERVAL = 300; // detik (5 menit)
            var remaining = INTERVAL;
            var countdownEl = document.getElementById('countdown');

            function pad(n) { return n < 10 ? '0' + n : n; }

            function updateCountdown() {
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                countdownEl.textContent = m + ':' + pad(s);
            }

            function applyFade(el) {
                el.classList.remove('fade-update');
                void el.offsetWidth;
                el.classList.add('fade-update');
            }

            function refreshToken() {
                window.location.reload();
            }

            setInterval(function () {
                remaining--;
                if (remaining <= 0) {
                    refreshToken();
                }
                updateCountdown();
            }, 1000);

            updateCountdown();
        })();
    </script>
</body>
</html>
