<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>503 – Layanan Tidak Tersedia | LIMS · PT. Evo Manufacturing Indonesia</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--p:#405189;--p-dk:#2c3b74;--p-rgb:64,81,137;--teal:#0ab39c;--t-rgb:10,179,156;}
        html,body{min-height:100%;font-family:'Nunito',sans-serif;color:#e2e8f0;background:#080e22;overflow-y:auto;}
        .bg-scene{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 80% 60% at 10% 20%,rgba(var(--p-rgb),.2) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 90% 80%,rgba(var(--p-rgb),.13) 0%,transparent 55%),linear-gradient(145deg,#060b1c 0%,#0b1228 45%,#101740 100%);}
        .bg-grid{position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:44px 44px;}
        .orb{position:fixed;border-radius:50%;filter:blur(70px);pointer-events:none;z-index:0;}
        .orb-1{width:500px;height:500px;top:-15%;left:-12%;background:rgba(var(--p-rgb),.18);}
        .orb-2{width:360px;height:360px;bottom:-12%;right:-8%;background:rgba(var(--p-rgb),.12);}
        .orb-3{width:200px;height:200px;top:45%;left:65%;background:rgba(var(--p-rgb),.07);}
        #particles{position:fixed;inset:0;z-index:1;pointer-events:none;}
        .topbar{position:fixed;top:0;left:0;right:0;z-index:30;display:flex;align-items:center;justify-content:center;padding:13px 24px;background:rgba(6,11,28,.72);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border-bottom:1px solid rgba(var(--p-rgb),.22);animation:slideDown .5s .1s both;}
        @keyframes slideDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}
        .tb-inner{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:center;}
        .tb-chip{background:linear-gradient(135deg,var(--p),var(--p-dk));color:#fff;font-size:11px;font-weight:800;padding:5px 14px;border-radius:50px;letter-spacing:.8px;text-transform:uppercase;}
        .tb-sep{color:rgba(255,255,255,.2);font-size:14px;}
        .tb-full{font-family:'Inter',sans-serif;font-size:12px;color:rgba(255,255,255,.4);}
        .page{position:relative;z-index:10;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:96px 20px 64px;}
        .card{width:100%;max-width:490px;text-align:center;background:rgba(16,24,58,.80);border:1px solid rgba(var(--p-rgb),.22);border-radius:22px;padding:48px 44px 44px;backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);box-shadow:0 40px 80px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.04) inset;animation:cardIn .65s cubic-bezier(.22,1,.36,1) both;}
        @keyframes cardIn{from{opacity:0;transform:translateY(36px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
        .icon-ring{display:inline-flex;align-items:center;justify-content:center;width:84px;height:84px;border-radius:50%;background:rgba(var(--p-rgb),.1);border:1.5px solid rgba(var(--p-rgb),.28);margin-bottom:22px;position:relative;}
        .icon-ring::before{content:'';position:absolute;inset:-5px;border-radius:50%;border:1.5px solid transparent;border-top-color:rgba(var(--p-rgb),.95);border-right-color:rgba(var(--t-rgb),.8);animation:spin 2.4s linear infinite;}
        .icon-ring::after{content:'';position:absolute;inset:-12px;border-radius:50%;border:1px solid transparent;border-bottom-color:rgba(var(--p-rgb),.3);animation:spin 3.6s linear reverse infinite;}
        @keyframes spin{to{transform:rotate(360deg)}}
        .icon-inner{font-size:36px;}
        .err-num{display:block;font-size:80px;font-weight:900;line-height:1;letter-spacing:-4px;background:linear-gradient(135deg,rgba(var(--p-rgb),1) 0%,rgba(var(--p-rgb),.5) 100%);-webkit-background-clip:text;background-clip:text;color:transparent;margin-bottom:4px;animation:numPop .7s cubic-bezier(.34,1.5,.64,1) .1s both;}
        @keyframes numPop{from{opacity:0;transform:scale(.5)}to{opacity:1;transform:scale(1)}}
        .divider{width:40px;height:3px;border-radius:2px;margin:14px auto 18px;background:linear-gradient(90deg,rgba(var(--p-rgb),.8),rgba(var(--p-rgb),.15));animation:expandW .5s .3s both;}
        @keyframes expandW{from{width:0;opacity:0}to{width:40px;opacity:1}}
        .title{font-size:22px;font-weight:800;color:#f0f4f8;letter-spacing:-.3px;margin-bottom:10px;animation:fadeUp .5s .2s both;}
        .desc{font-family:'Inter',sans-serif;font-size:13.5px;line-height:1.75;color:rgba(255,255,255,.5);margin-bottom:28px;animation:fadeUp .5s .25s both;}
        .strip{display:flex;align-items:center;gap:10px;padding:11px 14px;background:rgba(var(--p-rgb),.06);border:1px solid rgba(var(--p-rgb),.16);border-radius:10px;margin-bottom:10px;font-size:12.5px;color:rgba(255,255,255,.6);animation:fadeUp .5s .3s both;}
        .strip-icon{font-size:15px;flex-shrink:0;}
        .strip-val{font-weight:700;color:rgba(var(--p-rgb),.9);margin-left:auto;font-size:11px;letter-spacing:.4px;}
        .btn-wrap{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:28px;animation:fadeUp .5s .35s both;}
        .btn-main{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--p),var(--p-dk));color:#fff;text-decoration:none;padding:13px 28px;border-radius:50px;font-family:'Nunito',sans-serif;font-weight:700;font-size:14px;border:none;cursor:pointer;transition:all .3s cubic-bezier(.34,1.56,.64,1);box-shadow:0 8px 24px rgba(var(--p-rgb),.35);}
        .btn-main:hover{transform:translateY(-4px) scale(1.03);box-shadow:0 16px 40px rgba(var(--p-rgb),.5);color:#fff;text-decoration:none;}
        .btn-main svg{width:16px;height:16px;fill:white;flex-shrink:0;}
        .card-foot{margin-top:28px;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;animation:fadeUp .5s .4s both;}
        .cf-copy{font-size:11px;color:rgba(255,255,255,.28);}
        .cf-badge{font-size:11px;font-weight:700;background:rgba(var(--p-rgb),.1);border:1px solid rgba(var(--p-rgb),.22);color:rgba(var(--p-rgb),.9);padding:3px 10px;border-radius:20px;}
        .page-foot{text-align:center;padding:20px;font-family:'Inter',sans-serif;font-size:11px;color:rgba(255,255,255,.15);letter-spacing:.4px;position:relative;z-index:10;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
        @keyframes sparkle{0%,100%{opacity:0;transform:translateY(0) scale(.5)}50%{opacity:1;transform:translateY(-28px) scale(1)}}
        @media(max-width:480px){.card{padding:36px 22px 32px;}.err-num{font-size:64px;}.tb-full{display:none;}.btn-wrap{flex-direction:column;align-items:center;}}
    </style>
</head>
<body>
<div class="bg-scene"></div><div class="bg-grid"></div>
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
        <div class="icon-ring"><span class="icon-inner">🔧</span></div>
        <span class="err-num">503</span>
        <div class="divider"></div>
        <h1 class="title">Layanan Tidak Tersedia</h1>
        <p class="desc">Layanan sedang mengalami gangguan atau overload sementara.<br>Silakan coba kembali beberapa saat kemudian.</p>
        <div class="strip"><span class="strip-icon">⚡</span><span>Ketersediaan Layanan</span><span class="strip-val">TIDAK TERSEDIA</span></div>
        <div class="strip"><span class="strip-icon">⏰</span><span>Estimasi Pemulihan</span><span class="strip-val">SEGERA</span></div>
        <div class="btn-wrap">
            <a href="javascript:location.reload()" class="btn-main">
                <svg viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                Coba Lagi
            </a>
        </div>
        <div class="card-foot">
            <span class="cf-copy">© <script>document.write(new Date().getFullYear())</script> PT. Evo Manufacturing Indonesia</span>
            <span class="cf-badge">LIMS v3.0</span>
        </div>
    </div>
</div>

<div class="page-foot">Laboratory Information Management System &nbsp;·&nbsp; PT. Evo Manufacturing Indonesia</div>

<script>
(function(){var c=document.getElementById('particles');for(var i=0;i<32;i++){var p=document.createElement('div');var sz=Math.random()*3.5+1.5;p.style.cssText=['position:fixed','pointer-events:none','z-index:1','border-radius:50%','width:'+sz+'px','height:'+sz+'px','background:rgba(64,81,137,'+(Math.random()*.3+.05)+')','top:'+(Math.random()*100)+'vh','left:'+(Math.random()*100)+'vw','animation:sparkle '+(Math.random()*5+4)+'s ease-in-out infinite','animation-delay:-'+(Math.random()*9)+'s'].join(';');c.appendChild(p);}})();
</script>
</body>
</html>
