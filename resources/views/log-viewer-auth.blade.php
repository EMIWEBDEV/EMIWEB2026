<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Viewer — Autentikasi</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 40px 36px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
        }
        .card-icon {
            width: 52px;
            height: 52px;
            background: #1d4ed8;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .card-icon svg { width: 26px; height: 26px; color: #fff; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        h1 { font-size: 18px; font-weight: 700; color: #f1f5f9; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #94a3b8; margin-bottom: 28px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .input-wrap { position: relative; }
        input[type="password"], input[type="text"] {
            width: 100%;
            padding: 11px 42px 11px 14px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f1f5f9;
            font-size: 14px;
            outline: none;
            transition: border-color .2s;
        }
        input:focus { border-color: #3b82f6; }
        input.is-error { border-color: #ef4444; }
        .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            padding: 2px;
            display: flex;
            align-items: center;
        }
        .toggle-btn:hover { color: #94a3b8; }
        .toggle-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .error-msg { font-size: 12px; color: #f87171; margin-top: 6px; }
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #1d4ed8;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 24px;
            transition: background .2s;
        }
        .btn:hover { background: #1e40af; }
        .footer-note { font-size: 11px; color: #475569; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>
        <h1>Log Viewer</h1>
        <p class="subtitle">Masukkan access key untuk melanjutkan.</p>

        <form method="POST" action="{{ route('log-viewer.auth.post') }}">
            @csrf
            <label for="key">Access Key</label>
            <div class="input-wrap">
                <input
                    type="password"
                    id="key"
                    name="key"
                    placeholder="••••••••"
                    autocomplete="off"
                    autofocus
                    class="{{ $errors->has('key') ? 'is-error' : '' }}"
                    value="{{ old('key') }}"
                />
                <button type="button" class="toggle-btn" onclick="toggleVisibility(this)">
                    <svg id="eye-icon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </button>
            </div>
            @if($errors->has('key'))
                <div class="error-msg">{{ $errors->first('key') }}</div>
            @endif
            <button type="submit" class="btn">Masuk ke Log Viewer</button>
        </form>

        <p class="footer-note">EMI Lab &mdash; Internal Tool</p>
    </div>

    <script>
        function toggleVisibility(btn) {
            const input = document.getElementById('key');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.querySelector('svg').innerHTML = isPassword
                ? '<line x1="1" y1="1" x2="23" y2="23"></line><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>'
                : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        }
    </script>
</body>
</html>
