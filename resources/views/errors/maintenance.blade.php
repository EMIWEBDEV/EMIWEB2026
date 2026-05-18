<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sistem Dalam Pemeliharaan | LIMS · PT. Evo Manufacturing Indonesia</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

        :root{
            --p:#405189;--p-dk:#2c3b74;--p-rgb:64,81,137;
            --teal:#0ab39c;--t-rgb:10,179,156;
        }

        html,body{
            min-height:100%;
            font-family:'Nunito',sans-serif;
            color:#e2e8f0;
            background:#080e22;
            overflow-y:auto;
        }

        .bg-scene{
            position:fixed;inset:0;z-index:0;
            background:
                radial-gradient(ellipse 80% 60% at 10% 20%,rgba(var(--p-rgb),.22) 0%,transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 80%,rgba(var(--p-rgb),.14) 0%,transparent 55%),
                linear-gradient(145deg,#060b1c 0%,#0b1228 45%,#101740 100%);
            animation:meshBreath 14s ease-in-out infinite alternate;
        }
        @keyframes meshBreath{0%{transform:scale(1)}100%{transform:scale(1.04)}}
        .bg-grid{
            position:fixed;inset:0;z-index:0;
            background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);
            background-size:44px 44px;
        }
        .orb{position:fixed;border-radius:50%;filter:blur(70px);pointer-events:none;z-index:0;animation:orbDrift ease-in-out infinite alternate;}
        .orb-1{width:520px;height:520px;top:-18%;left:-14%;background:rgba(var(--p-rgb),.2);animation-duration:18s;}
        .orb-2{width:360px;height:360px;bottom:-14%;right:-10%;background:rgba(var(--p-rgb),.14);animation-duration:14s;animation-delay:-5s;}
        .orb-3{width:220px;height:220px;top:48%;left:68%;background:rgba(var(--p-rgb),.09);animation-duration:10s;animation-delay:-8s;}
        @keyframes orbDrift{0%{transform:translate(0,0)}100%{transform:translate(40px,-50px)}}
        #particles{position:fixed;inset:0;z-index:1;pointer-events:none;}

        /* TOPBAR */
        .topbar{
            position:fixed;top:0;left:0;right:0;z-index:30;
            display:flex;align-items:center;justify-content:center;
            padding:13px 24px;
            background:rgba(6,11,28,.72);
            backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
            border-bottom:1px solid rgba(var(--p-rgb),.22);
            animation:slideDown .6s .8s both;
        }
        @keyframes slideDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}
        .tb-inner{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:center;}
        .tb-chip{background:linear-gradient(135deg,var(--p),var(--p-dk));color:#fff;font-size:11px;font-weight:800;padding:5px 14px;border-radius:50px;letter-spacing:.8px;text-transform:uppercase;}
        .tb-sep{color:rgba(255,255,255,.2);font-size:14px;}
        .tb-full{font-family:'Inter',sans-serif;font-size:12px;color:rgba(255,255,255,.4);}

        /* PAGE */
        .page{
            position:relative;z-index:10;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            min-height:100vh;padding:96px 20px 64px;
        }

        /* CARD */
        .card{
            text-align:center;
            background:rgba(16,24,58,.82);
            backdrop-filter:blur(28px) saturate(180%);-webkit-backdrop-filter:blur(28px) saturate(180%);
            border:1px solid rgba(var(--p-rgb),.22);
            border-radius:26px;
            padding:52px 48px 48px;
            max-width:520px;width:100%;
            box-shadow:0 0 0 1px rgba(255,255,255,.04) inset,0 1px 0 rgba(255,255,255,.07) inset,0 40px 80px rgba(0,0,0,.5);
            animation:cardReveal 1s cubic-bezier(.34,1.36,.64,1) both;
        }
        @keyframes cardReveal{0%{opacity:0;transform:translateY(60px) scale(.9)}100%{opacity:1;transform:translateY(0) scale(1)}}

        /* GEARS */
        .gears-wrap{display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:28px;animation:fadeUp .5s .3s both;}
        .gear-svg{fill:rgba(var(--p-rgb),.9);filter:drop-shadow(0 0 10px rgba(var(--p-rgb),.5));}
        .gear-lg{width:60px;height:60px;animation:rotateGear 8s linear infinite;}
        .gear-sm{width:38px;height:38px;animation:rotateGear 5s linear infinite reverse;margin-top:18px;}
        .gear-xs{width:26px;height:26px;animation:rotateGear 3.5s linear infinite;margin-top:-8px;}
        @keyframes rotateGear{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}

        /* STATUS CHIP */
        .status-chip{
            display:inline-flex;align-items:center;gap:7px;
            background:rgba(var(--p-rgb),.12);border:1px solid rgba(var(--p-rgb),.3);
            color:rgba(var(--p-rgb),.95);
            font-size:.73rem;font-weight:700;
            padding:5px 14px;border-radius:50px;letter-spacing:.07em;text-transform:uppercase;
            margin-bottom:20px;animation:fadeUp .5s .4s both;
        }
        .status-dot{width:6px;height:6px;border-radius:50%;background:rgba(var(--p-rgb),1);animation:dotPulse 1.5s ease-in-out infinite;}
        @keyframes dotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}

        .maint-title{font-size:1.6rem;font-weight:800;color:#f0f4f8;margin-bottom:12px;letter-spacing:-.03em;animation:fadeUp .5s .5s both;}
        .maint-msg{font-family:'Inter',sans-serif;font-size:.88rem;color:rgba(255,255,255,.45);line-height:1.75;margin-bottom:32px;animation:fadeUp .5s .6s both;}

        /* PROGRESS (indeterminate shimmer) */
        .progress-wrap{background:rgba(255,255,255,.06);border-radius:50px;height:5px;overflow:hidden;margin-bottom:28px;animation:fadeUp .5s .65s both;}
        .progress-fill{
            height:100%;
            background:linear-gradient(90deg,rgba(var(--p-rgb),.3),rgba(var(--p-rgb),1),rgba(var(--p-rgb),.3));
            background-size:200% 100%;border-radius:50px;
            animation:progressSlide 2.2s ease-in-out infinite;
        }
        @keyframes progressSlide{0%{transform:translateX(-100%)}100%{transform:translateX(200%)}}

        /* INFO GRID */
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:28px;animation:fadeUp .5s .7s both;}
        .info-item{
            background:rgba(var(--p-rgb),.06);border:1px solid rgba(var(--p-rgb),.15);
            border-radius:12px;padding:12px 14px;text-align:left;
        }
        .info-label{font-size:.7rem;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px;}
        .info-val{font-size:.85rem;color:rgba(255,255,255,.8);font-weight:600;}

        .divider-line{height:1px;background:rgba(255,255,255,.07);margin:0 0 20px;animation:fadeUp .5s .75s both;}

        .contact-note{font-family:'Inter',sans-serif;font-size:.8rem;color:rgba(255,255,255,.4);animation:fadeUp .5s .8s both;}
        .contact-note a{color:rgba(var(--p-rgb),.8);text-decoration:none;}
        .contact-note a:hover{color:rgba(var(--p-rgb),1);}

        .page-foot{text-align:center;padding:20px;font-family:'Inter',sans-serif;font-size:11px;color:rgba(255,255,255,.15);letter-spacing:.4px;position:relative;z-index:10;}

        @keyframes fadeUp{0%{opacity:0;transform:translateY(10px)}100%{opacity:1;transform:translateY(0)}}
        @keyframes sparkle{0%,100%{opacity:0;transform:translateY(0) scale(.5)}50%{opacity:1;transform:translateY(-28px) scale(1)}}

        @media(max-width:480px){
            .card{padding:36px 24px 32px;}
            .maint-title{font-size:1.3rem;}
            .tb-full{display:none;}
            .info-grid{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
<div class="bg-scene"></div>
<div class="bg-grid"></div>
<div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div>
<div id="particles"></div>

<div class="topbar">
    <div class="tb-inner">
        <span class="tb-chip">LIMS</span>
        <span class="tb-sep">|</span>
        <span class="tb-full">Laboratory Information Management System &nbsp;·&nbsp; PT. Evo Manufacturing Indonesia</span>
    </div>
</div>

<div class="page">
    <div class="card">
        <div class="gears-wrap">
            <svg class="gear-svg gear-lg" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
            <svg class="gear-svg gear-sm" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
            <svg class="gear-svg gear-xs" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
        </div>

        <div class="status-chip">
            <div class="status-dot"></div>
            Pemeliharaan Aktif
        </div>

        <h1 class="maint-title">Sistem Sedang Dalam Pemeliharaan</h1>
        <p class="maint-msg">
            Kami sedang melakukan peningkatan sistem untuk memberikan<br>
            pengalaman yang lebih baik. Mohon bersabar sebentar.
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
                <div class="info-val">PT Evo Manufacturing</div>
            </div>
        </div>

        <div class="divider-line"></div>

        <p class="contact-note">
            Butuh bantuan segera? Hubungi
            <a href="mailto:developer@evonusabersaudara.co.id">Tim IT Development</a>
        </p>
    </div>
</div>

<div class="page-foot">PT Evo Manufacturing Indonesia &nbsp;·&nbsp; Laboratory Information Management System</div>

<script>
(function(){
    var c=document.getElementById('particles');
    for(var i=0;i<30;i++){
        var p=document.createElement('div');var sz=Math.random()*3.5+1;
        p.style.cssText=['position:fixed','pointer-events:none','z-index:1','border-radius:50%',
            'width:'+sz+'px','height:'+sz+'px',
            'background:rgba(64,81,137,'+(Math.random()*.35+.05)+')',
            'top:'+(Math.random()*100)+'vh','left:'+(Math.random()*100)+'vw',
            'animation:sparkle '+(Math.random()*5+4)+'s ease-in-out infinite',
            'animation-delay:-'+(Math.random()*9)+'s'].join(';');
        c.appendChild(p);
    }
}());
</script>
</body>
</html>
