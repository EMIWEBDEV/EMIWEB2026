<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>401 – Sesi Berakhir | EMI Lab</title>
    <style>
        :root {
            --accent: #f59e0b;
            --accent-dark: #b45309;
            --accent-rgb: 245, 158, 11;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #0d1117;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #e2e8f0;
            overflow: hidden;
            position: relative;
            padding: 80px 20px 64px;
        }

        .bg-mesh {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 15% 55%, rgba(var(--accent-rgb), 0.13) 0%, transparent 65%),
                radial-gradient(ellipse 50% 40% at 85% 25%, rgba(var(--accent-rgb), 0.08) 0%, transparent 60%),
                linear-gradient(180deg, #0d1117 0%, #111827 50%, #0d1117 100%);
            animation: meshBreath 14s ease-in-out infinite alternate;
            pointer-events: none;
            z-index: 0;
        }
        @keyframes meshBreath {
            0% { transform: scale(1); }
            100% { transform: scale(1.06); }
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(70px);
            pointer-events: none;
            z-index: 0;
            animation: orbDrift ease-in-out infinite alternate;
        }
        .orb-1 { width: 420px; height: 420px; top: -15%; left: -12%; background: rgba(var(--accent-rgb), 0.18); animation-duration: 16s; }
        .orb-2 { width: 300px; height: 300px; bottom: -12%; right: -8%; background: rgba(var(--accent-rgb), 0.12); animation-duration: 12s; animation-delay: -4s; }
        .orb-3 { width: 160px; height: 160px; top: 40%; left: 65%; background: rgba(var(--accent-rgb), 0.08); animation-duration: 9s; animation-delay: -7s; }
        @keyframes orbDrift {
            0% { transform: translate(0, 0); }
            100% { transform: translate(35px, -45px); }
        }

        .logo-bar {
            position: fixed;
            top: 22px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 20;
            white-space: nowrap;
            animation: fadeDown 0.6s ease 0.9s both;
        }
        .logo-chip {
            background: linear-gradient(135deg, #405189, rgba(var(--accent-rgb), 0.9));
            color: white;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 50px;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }
        .logo-name { font-size: 0.78rem; color: #374151; }
        @keyframes fadeDown {
            0% { opacity: 0; transform: translateX(-50%) translateY(-12px); }
            100% { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        .error-card {
            position: relative;
            z-index: 10;
            text-align: center;
            background: rgba(255, 255, 255, 0.035);
            backdrop-filter: blur(28px) saturate(180%);
            -webkit-backdrop-filter: blur(28px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            padding: 48px 44px 44px;
            max-width: 490px;
            width: 100%;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.04) inset,
                0 1px 0 rgba(255,255,255,0.08) inset,
                0 40px 80px rgba(0,0,0,0.5);
            animation: cardReveal 1s cubic-bezier(0.34, 1.36, 0.64, 1) both;
        }
        @keyframes cardReveal {
            0% { opacity: 0; transform: translateY(60px) scale(0.9); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .icon-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 76px; height: 76px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(var(--accent-rgb), 0.9), rgba(var(--accent-rgb), 0.6));
            margin-bottom: 20px;
            animation: iconFloat 4s ease-in-out infinite;
            box-shadow:
                0 16px 40px rgba(var(--accent-rgb), 0.35),
                0 0 0 10px rgba(var(--accent-rgb), 0.07),
                0 0 0 22px rgba(var(--accent-rgb), 0.03);
        }
        .icon-circle svg { width: 34px; height: 34px; fill: white; }
        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-13px); }
        }

        .error-num {
            font-size: 6.5rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.04em;
            margin-bottom: 4px;
            background: linear-gradient(135deg, rgba(var(--accent-rgb), 1) 0%, rgba(var(--accent-rgb), 0.6) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: numReveal 0.8s cubic-bezier(0.34, 1.5, 0.64, 1) 0.2s both;
            display: block;
        }
        @keyframes numReveal {
            0% { opacity: 0; transform: scale(0.5); }
            100% { opacity: 1; transform: scale(1); }
        }

        .divider {
            width: 40px; height: 3px;
            background: linear-gradient(90deg, rgba(var(--accent-rgb), 0.8), rgba(var(--accent-rgb), 0.2));
            border-radius: 2px;
            margin: 14px auto 18px;
            animation: divExpand 0.5s ease 0.6s both;
        }
        @keyframes divExpand {
            0% { width: 0; opacity: 0; }
            100% { width: 40px; opacity: 1; }
        }

        .error-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #f0f4f8;
            margin-bottom: 10px;
            letter-spacing: -0.02em;
            animation: fadeUp 0.5s ease 0.5s both;
        }

        .error-msg {
            font-size: 0.9rem;
            color: #6b7280;
            line-height: 1.75;
            margin-bottom: 32px;
            animation: fadeUp 0.5s ease 0.6s both;
        }
        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .btn-wrap {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeUp 0.5s ease 0.7s both;
        }

        .btn-main {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(var(--accent-rgb), 0.85);
            color: white;
            text-decoration: none;
            padding: 12px 26px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.88rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 8px 20px rgba(var(--accent-rgb), 0.35);
        }
        .btn-main:hover {
            background: rgba(var(--accent-rgb), 1);
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 16px 36px rgba(var(--accent-rgb), 0.5);
            color: white;
            text-decoration: none;
        }

        .page-footer {
            position: fixed;
            bottom: 20px;
            left: 0; right: 0;
            text-align: center;
            font-size: 0.73rem;
            color: #1e293b;
            letter-spacing: 0.04em;
            z-index: 20;
            animation: fadeUp 0.5s ease 1.2s both;
        }

        @keyframes sparkle {
            0%, 100% { opacity: 0; transform: translateY(0) scale(0.5); }
            50% { opacity: 1; transform: translateY(-28px) scale(1); }
        }

        @media (max-width: 480px) {
            .error-card { padding: 36px 24px 32px; }
            .error-num { font-size: 5rem; }
            .logo-name { display: none; }
        }
    </style>
</head>
<body>
    <div class="bg-mesh"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="logo-bar">
        <span class="logo-chip">EMI Lab</span>
        <span class="logo-name">Laboratory Information Management System</span>
    </div>

    <div class="error-card">
        <div class="icon-circle">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
            </svg>
        </div>
        <span class="error-num">401</span>
        <div class="divider"></div>
        <h1 class="error-title">Sesi Berakhir</h1>
        <p class="error-msg">
            Anda belum login atau sesi Anda telah berakhir.<br>
            Silakan masuk kembali untuk melanjutkan.
        </p>
        <div class="btn-wrap">
            <a href="/login" class="btn-main">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/></svg>
                Masuk Kembali
            </a>
        </div>
    </div>

    <div class="page-footer">
        PT Evo Manufacturing Indonesia &nbsp;·&nbsp; EMI Laboratory Information Management System
    </div>

    <script>
        (function () {
            var rgb = '245, 158, 11';
            for (var i = 0; i < 28; i++) {
                var p = document.createElement('div');
                var sz = Math.random() * 3.5 + 1;
                p.style.cssText = [
                    'position:fixed', 'pointer-events:none', 'z-index:1', 'border-radius:50%',
                    'width:' + sz + 'px', 'height:' + sz + 'px',
                    'background:rgba(' + rgb + ',' + (Math.random() * 0.45 + 0.05) + ')',
                    'top:' + (Math.random() * 100) + 'vh',
                    'left:' + (Math.random() * 100) + 'vw',
                    'animation:sparkle ' + (Math.random() * 5 + 4) + 's ease-in-out infinite',
                    'animation-delay:-' + (Math.random() * 9) + 's'
                ].join(';');
                document.body.appendChild(p);
            }
        }());
    </script>
</body>
</html>
