<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Dalam Pemeliharaan | LIMS</title>
    <style>
        :root {
            --accent: #8b5cf6;
            --accent-dark: #6d28d9;
            --accent-rgb: 139, 92, 246;
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
                radial-gradient(ellipse 70% 50% at 15% 55%, rgba(var(--accent-rgb), 0.14) 0%, transparent 65%),
                radial-gradient(ellipse 50% 40% at 85% 25%, rgba(var(--accent-rgb), 0.09) 0%, transparent 60%),
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
        .orb-1 { width: 500px; height: 500px; top: -20%; left: -15%; background: rgba(var(--accent-rgb), 0.18); animation-duration: 18s; }
        .orb-2 { width: 350px; height: 350px; bottom: -15%; right: -10%; background: rgba(var(--accent-rgb), 0.12); animation-duration: 14s; animation-delay: -5s; }
        .orb-3 { width: 200px; height: 200px; top: 50%; left: 70%; background: rgba(var(--accent-rgb), 0.08); animation-duration: 10s; animation-delay: -8s; }
        @keyframes orbDrift {
            0% { transform: translate(0, 0); }
            100% { transform: translate(40px, -50px); }
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

        .maint-card {
            position: relative;
            z-index: 10;
            text-align: center;
            background: rgba(255, 255, 255, 0.035);
            backdrop-filter: blur(28px) saturate(180%);
            -webkit-backdrop-filter: blur(28px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            padding: 52px 48px 48px;
            max-width: 520px;
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

        /* Animated gears */
        .gears-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease 0.3s both;
        }

        .gear-svg {
            fill: rgba(var(--accent-rgb), 0.85);
            filter: drop-shadow(0 0 10px rgba(var(--accent-rgb), 0.4));
        }
        .gear-lg {
            width: 60px; height: 60px;
            animation: rotateGear 8s linear infinite;
        }
        .gear-sm {
            width: 38px; height: 38px;
            animation: rotateGear 5s linear infinite reverse;
            margin-top: 18px;
        }
        .gear-xs {
            width: 26px; height: 26px;
            animation: rotateGear 3.5s linear infinite;
            margin-top: -8px;
        }
        @keyframes rotateGear {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(var(--accent-rgb), 0.12);
            border: 1px solid rgba(var(--accent-rgb), 0.3);
            color: rgba(var(--accent-rgb), 1);
            font-size: 0.73rem;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 50px;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            margin-bottom: 20px;
            animation: fadeUp 0.5s ease 0.4s both;
        }
        .status-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: rgba(var(--accent-rgb), 1);
            animation: dotPulse 1.5s ease-in-out infinite;
        }
        @keyframes dotPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.7); }
        }

        .maint-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #f0f4f8;
            margin-bottom: 12px;
            letter-spacing: -0.03em;
            animation: fadeUp 0.5s ease 0.5s both;
        }

        .maint-msg {
            font-size: 0.9rem;
            color: #6b7280;
            line-height: 1.75;
            margin-bottom: 32px;
            animation: fadeUp 0.5s ease 0.6s both;
        }

        /* Progress bar */
        .progress-wrap {
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50px;
            height: 5px;
            overflow: hidden;
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease 0.65s both;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg,
                rgba(var(--accent-rgb), 0.3),
                rgba(var(--accent-rgb), 1),
                rgba(var(--accent-rgb), 0.3));
            background-size: 200% 100%;
            border-radius: 50px;
            animation: progressSlide 2.2s ease-in-out infinite;
        }
        @keyframes progressSlide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(200%); }
        }

        /* Info rows */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease 0.7s both;
        }
        .info-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 12px 14px;
            text-align: left;
        }
        .info-label {
            font-size: 0.7rem;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 4px;
        }
        .info-val {
            font-size: 0.85rem;
            color: #9ca3af;
            font-weight: 500;
        }

        .divider-line {
            height: 1px;
            background: rgba(255,255,255,0.06);
            margin: 0 0 20px;
            animation: fadeUp 0.5s ease 0.75s both;
        }

        .contact-note {
            font-size: 0.8rem;
            color: #374151;
            animation: fadeUp 0.5s ease 0.8s both;
        }
        .contact-note a {
            color: rgba(var(--accent-rgb), 0.8);
            text-decoration: none;
        }
        .contact-note a:hover { color: rgba(var(--accent-rgb), 1); }

        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
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
            .maint-card { padding: 36px 24px 32px; }
            .maint-title { font-size: 1.3rem; }
            .logo-name { display: none; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="bg-mesh"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="logo-bar">
        <span class="logo-chip">EMI</span>
        <span class="logo-name">Laboratory Information Management System</span>
    </div>

    <div class="maint-card">
        <!-- Animated Gears -->
        <div class="gears-wrap">
            <svg class="gear-svg gear-lg" viewBox="0 0 24 24">
                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
            </svg>
            <svg class="gear-svg gear-sm" viewBox="0 0 24 24">
                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
            </svg>
            <svg class="gear-svg gear-xs" viewBox="0 0 24 24">
                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
            </svg>
        </div>

        <div class="status-chip">
            <div class="status-dot"></div>
            Pemeliharaan Aktif
        </div>

        <h1 class="maint-title">Sistem Sedang Dalam Pemeliharaan</h1>
        <p class="maint-msg">
            Kami sedang melakukan peningkatan sistem untuk memberikan<br>
            pengalaman yang lebih baik. Mohon bersabar.
        </p>

        <div class="progress-wrap">
            <div class="progress-fill"></div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Sistem</div>
                <div class="info-val">LIMS</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-val">Maintenance</div>
            </div>
            <div class="info-item">
                <div class="info-label">Tim</div>
                <div class="info-val">IT Development</div>
            </div>
            <div class="info-item">
                <div class="info-label">Perusahaan</div>
                <div class="info-val">PT Evo Manufacturing Indonesia</div>
            </div>
        </div>

        <div class="divider-line"></div>

        <p class="contact-note">
            Butuh bantuan segera? Hubungi
            <a href="mailto:developer@evonusabersaudara.co.id">Tim IT</a>
        </p>
    </div>

    <div class="page-footer">
        PT Evo Manufacturing Indonesia &nbsp;·&nbsp; EMI Laboratory Information Management System
    </div>

    <script>
        (function () {
            var rgb = '139, 92, 246';
            for (var i = 0; i < 30; i++) {
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
