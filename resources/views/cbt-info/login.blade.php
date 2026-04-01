<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin CBT</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #475569;
            --line: #cbd5e1;
            --brand: #1d4ed8;
            --error-bg: #fff1f2;
            --error-text: #be123c;
            --ok-bg: #ecfdf3;
            --ok-text: #047857;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 16px;
            background:
                radial-gradient(circle at 15% 20%, #dbeafe 0%, transparent 30%),
                radial-gradient(circle at 85% 0%, #dcfce7 0%, transparent 28%),
                #f8fafc;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--ink);
        }

        .box {
            width: min(430px, 100%);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.1);
        }

        h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        p {
            margin: 8px 0 0;
            color: var(--muted);
        }

        .school-name {
            margin-top: 10px;
            font-size: clamp(1.05rem, 2.2vw, 1.3rem);
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

        .alert-ok {
            background: var(--ok-bg);
            color: var(--ok-text);
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        form {
            margin-top: 14px;
            display: grid;
            gap: 12px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.95rem;
            outline: none;
        }

        input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        button,
        a {
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            padding: 10px 14px;
            display: inline-block;
        }

        button {
            border: 0;
            cursor: pointer;
            background: var(--brand);
            color: #fff;
        }

        a {
            border: 1px solid var(--line);
            color: var(--ink);
            background: #fff;
        }
    </style>
</head>
<body>
    <main class="box">
        <h1>Login Admin</h1>
        <p class="school-name">{{ $schoolName ?? 'GARUDA CBT' }}</p>
        <p><strong>{{ $appName ?? 'GARUDA CBT' }}</strong></p>
        <p>Gunakan username dan password admin dari database GARUDA CBT.</p>

        @if (session('status'))
            <div class="alert alert-ok">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('cbt.admin.login.submit') }}" method="post">
            @csrf

            <div>
                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" required>
            </div>

            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>

            <div class="actions">
                <button type="submit">Masuk Admin</button>
                <a href="{{ route('cbt.index') }}">Kembali</a>
            </div>
        </form>
    </main>
</body>
</html>
