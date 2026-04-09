<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unauthorized Access</title>
    <style>
        :root {
            --bg-top: #08114a;
            --bg-bottom: #04081f;
            --glow: rgba(45, 104, 255, 0.55);
            --accent: #ff2f43;
            --accent-soft: rgba(255, 47, 67, 0.18);
            --text-main: #f4f7ff;
            --text-muted: rgba(236, 240, 255, 0.72);
            --grid: rgba(103, 141, 255, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            overflow: hidden;
            background:
                radial-gradient(circle at 50% 22%, rgba(52, 102, 255, 0.4), transparent 30%),
                radial-gradient(circle at 50% 50%, rgba(12, 31, 116, 0.78), transparent 58%),
                linear-gradient(180deg, var(--bg-top) 0%, var(--bg-bottom) 100%);
            color: var(--text-main);
            font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
        }

        body::before {
            background-image:
                linear-gradient(var(--grid) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid) 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.35;
            transform: perspective(1200px) rotateX(75deg) scale(1.8) translateY(24%);
            transform-origin: center bottom;
        }

        body::after {
            background: repeating-linear-gradient(
                180deg,
                rgba(255, 255, 255, 0.03) 0,
                rgba(255, 255, 255, 0.03) 2px,
                transparent 2px,
                transparent 5px
            );
            mix-blend-mode: screen;
            opacity: 0.18;
        }

        .frame {
            position: relative;
            width: min(100%, 920px);
            padding: 48px 28px;
            text-align: center;
        }

        .aura {
            position: absolute;
            inset: 12% 22%;
            background: radial-gradient(circle, var(--glow) 0%, transparent 68%);
            filter: blur(44px);
            opacity: 0.85;
            z-index: 0;
        }

        .panel {
            position: relative;
            z-index: 1;
            padding: 44px 24px 28px;
            border: 1px solid rgba(120, 162, 255, 0.15);
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(8, 16, 58, 0.72), rgba(4, 10, 34, 0.9));
            box-shadow:
                0 28px 80px rgba(0, 0, 0, 0.45),
                inset 0 1px 0 rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
        }

        .warning-mark {
            width: 132px;
            height: 118px;
            margin: 0 auto 28px;
            filter: drop-shadow(0 0 20px rgba(255, 47, 67, 0.45));
        }

        .warning-mark svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .headline {
            margin: 0;
            font-size: clamp(2.6rem, 7vw, 5.3rem);
            letter-spacing: 0.08em;
            line-height: 0.95;
            text-transform: uppercase;
            text-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
        }

        .subhead {
            margin: 10px 0 0;
            font-size: clamp(1.6rem, 4vw, 3rem);
            letter-spacing: 0.14em;
            color: rgba(255, 255, 255, 0.96);
            text-transform: uppercase;
        }

        .copy {
            width: min(100%, 640px);
            margin: 26px auto 0;
            color: var(--text-muted);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1rem;
            line-height: 1.7;
            letter-spacing: 0.02em;
        }

        .copy strong {
            color: var(--text-main);
            font-weight: 700;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 28px;
            padding: 12px 18px;
            border-radius: 999px;
            border: 1px solid rgba(255, 47, 67, 0.34);
            background: linear-gradient(180deg, rgba(255, 47, 67, 0.22), rgba(255, 47, 67, 0.1));
            color: #ffd3d8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .badge-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #ff4a5d;
            box-shadow: 0 0 12px rgba(255, 74, 93, 0.9);
        }

        @media (max-width: 640px) {
            .frame {
                padding: 24px 16px;
            }

            .panel {
                padding: 32px 18px 24px;
                border-radius: 22px;
            }

            .warning-mark {
                width: 104px;
                height: 92px;
                margin-bottom: 22px;
            }

            .copy {
                font-size: 0.94rem;
            }
        }
    </style>
</head>
<body>
    <main class="frame">
        <div class="aura"></div>
        <section class="panel" aria-labelledby="unauthorized-title">
            <div class="warning-mark" aria-hidden="true">
                <svg viewBox="0 0 120 108" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M60 8L111 97H9L60 8Z" fill="rgba(255,47,67,0.12)" stroke="#ff364a" stroke-width="6"/>
                    <path d="M60 32V62" stroke="#ffffff" stroke-width="10" stroke-linecap="round"/>
                    <circle cx="60" cy="79" r="6" fill="#ffffff"/>
                </svg>
            </div>

            <h1 class="headline" id="unauthorized-title">Unauthorized</h1>
            <p class="subhead">Access</p>

            <p class="copy">
                <strong>{{ $appName ?? 'GARUDA CBT' }}</strong> hanya dapat dibuka melalui aplikasi Exambro yang valid.
                {{ $message ?? 'Akses dari browser biasa diblokir untuk menjaga integritas ujian.' }}
            </p>

            <div class="badge">
                <span class="badge-dot"></span>
                Exambro Only
            </div>
        </section>
    </main>
</body>
</html>