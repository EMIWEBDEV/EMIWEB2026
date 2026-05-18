<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Keluar — LIMS | PT. Evo Manufacturing Indonesia</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

        :root {
            --p:       #405189;
            --p-dk:    #2c3b74;
            --p-rgb:   64,81,137;
            --teal:    #0ab39c;
            --t-rgb:   10,179,156;
            --white:   #ffffff;
            --muted:   rgba(255,255,255,.55);
            --border:  rgba(255,255,255,.08);
            --card-bg: rgba(16,24,58,.80);
        }

        html,body {
            min-height:100%;
            font-family:'Nunito',sans-serif;
            color:var(--white);
            background:#080e22;
            overflow-y:auto;
        }

        /* ── BACKGROUND ── */
        .bg-scene {
            position:fixed;inset:0;z-index:0;
            background:
                radial-gradient(ellipse 80% 70% at 10% 20%, rgba(var(--p-rgb),.22) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 80%, rgba(var(--p-rgb),.14) 0%, transparent 55%),
                linear-gradient(145deg, #060b1c 0%, #0b1228 45%, #101740 100%);
        }
        .bg-grid {
            position:fixed;inset:0;z-index:0;
            background-image:
                linear-gradient(rgba(255,255,255,.028) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.028) 1px, transparent 1px);
            background-size:44px 44px;
        }
        .bg-orb {
            position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;z-index:0;
        }
        .orb-1{width:520px;height:520px;top:-130px;left:-90px;background:rgba(var(--p-rgb),.16);}
        .orb-2{width:400px;height:400px;bottom:-70px;right:-70px;background:rgba(var(--p-rgb),.11);}
        .orb-3{width:280px;height:280px;top:42%;left:56%;background:rgba(var(--p-rgb),.07);}

        /* ── PARTICLES ── */
        #particles{position:fixed;inset:0;z-index:1;pointer-events:none;}

        /* ── TOPBAR ── */
        .topbar {
            position:fixed;top:0;left:0;right:0;z-index:30;
            display:flex;align-items:center;justify-content:center;
            padding:13px 24px;
            background:rgba(6,11,28,.72);
            backdrop-filter:blur(18px);
            -webkit-backdrop-filter:blur(18px);
            border-bottom:1px solid rgba(var(--p-rgb),.22);
            animation:slideDown .5s .1s both;
        }
        @keyframes slideDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}
        .tb-inner{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:center;}
        .tb-chip{
            background:linear-gradient(135deg,var(--p),var(--p-dk));
            color:#fff;font-size:11px;font-weight:800;
            padding:5px 14px;border-radius:50px;letter-spacing:.8px;
            text-transform:uppercase;
        }
        .tb-sep{color:rgba(255,255,255,.2);font-size:14px;}
        .tb-full{font-family:'Inter',sans-serif;font-size:12px;color:rgba(255,255,255,.4);}

        /* ── LAYOUT ── */
        .page {
            position:relative;z-index:10;
            display:flex;align-items:center;justify-content:center;
            min-height:100vh;padding:96px 20px 48px;
        }

        /* ── CARD ── */
        .card {
            width:100%;max-width:520px;
            background:var(--card-bg);
            border:1px solid rgba(var(--p-rgb),.22);
            border-radius:22px;
            padding:48px 44px 40px;
            backdrop-filter:blur(28px);
            -webkit-backdrop-filter:blur(28px);
            box-shadow:0 40px 80px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.04) inset;
            animation:cardIn .65s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes cardIn{from{opacity:0;transform:translateY(32px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}

        /* ── BRAND HEADER ── */
        .brand-header {
            display:flex;align-items:center;gap:14px;
            margin-bottom:32px;padding-bottom:24px;
            border-bottom:1px solid var(--border);
            animation:fadeUp .5s .1s both;
        }
        .brand-logo {
            width:50px;height:50px;border-radius:13px;
            background:linear-gradient(135deg,var(--p),var(--p-dk));
            display:flex;align-items:center;justify-content:center;
            font-weight:900;font-size:15px;letter-spacing:-1px;color:#fff;
            box-shadow:0 8px 24px rgba(var(--p-rgb),.45);
            flex-shrink:0;
        }
        .brand-text{flex:1;min-width:0;}
        .brand-name{
            font-size:11.5px;font-weight:700;letter-spacing:.4px;
            color:rgba(255,255,255,.9);line-height:1.5;
        }
        .brand-name em{font-style:normal;color:rgba(var(--t-rgb),1);}
        .brand-company{
            font-size:10.5px;font-weight:500;color:var(--muted);
            margin-top:3px;letter-spacing:.3px;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
        }
        .brand-badge{
            display:inline-flex;align-items:center;gap:5px;
            background:rgba(var(--t-rgb),.1);
            border:1px solid rgba(var(--t-rgb),.28);
            color:var(--teal);
            font-size:10px;font-weight:800;
            padding:4px 10px;border-radius:20px;letter-spacing:.5px;
            flex-shrink:0;
        }
        .badge-dot{width:6px;height:6px;border-radius:50%;background:var(--teal);animation:pulse 1.4s ease-in-out infinite;}
        @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}

        /* ── ICON RING ── */
        .shield-wrap{text-align:center;margin-bottom:28px;animation:fadeUp .5s .2s both;}
        .shield-ring {
            display:inline-flex;align-items:center;justify-content:center;
            width:90px;height:90px;border-radius:50%;
            background:rgba(var(--p-rgb),.1);
            border:1.5px solid rgba(var(--p-rgb),.28);
            position:relative;
        }
        .shield-ring::before {
            content:'';position:absolute;inset:-5px;border-radius:50%;
            border:1.5px solid transparent;
            border-top-color:rgba(var(--p-rgb),.95);
            border-right-color:rgba(var(--t-rgb),.8);
            animation:spinRing 2.4s linear infinite;
        }
        .shield-ring::after {
            content:'';position:absolute;inset:-12px;border-radius:50%;
            border:1px solid transparent;
            border-bottom-color:rgba(var(--p-rgb),.35);
            animation:spinRing 3.6s linear reverse infinite;
        }
        @keyframes spinRing{to{transform:rotate(360deg)}}
        .shield-icon{font-size:36px;}

        /* ── HEADING ── */
        .heading-wrap{text-align:center;margin-bottom:28px;animation:fadeUp .5s .25s both;}
        .heading{
            font-size:24px;font-weight:800;letter-spacing:-.3px;margin-bottom:10px;
            background:linear-gradient(135deg,#fff 30%,rgba(var(--p-rgb),.85) 100%);
            -webkit-background-clip:text;background-clip:text;color:transparent;
        }
        .sub{font-family:'Inter',sans-serif;font-size:13.5px;line-height:1.65;color:var(--muted);}

        /* ── SECURITY STEPS ── */
        .security-steps{display:flex;flex-direction:column;gap:10px;margin-bottom:28px;animation:fadeUp .5s .3s both;}
        .step{
            display:flex;align-items:center;gap:12px;
            padding:11px 14px;
            background:rgba(255,255,255,.04);
            border:1px solid rgba(255,255,255,.07);
            border-radius:10px;font-size:13px;color:var(--muted);
            transition:border-color .3s,background .3s;
        }
        .step.done{border-color:rgba(var(--t-rgb),.3);background:rgba(var(--t-rgb),.06);color:rgba(255,255,255,.8);}
        .step.done .step-icon{color:var(--teal);}
        .step.active{border-color:rgba(var(--p-rgb),.4);background:rgba(var(--p-rgb),.09);color:var(--white);}
        .step.active .step-icon{color:rgba(var(--p-rgb),1);}
        .step-icon{font-size:17px;width:20px;text-align:center;flex-shrink:0;}
        .step-text{flex:1;font-weight:500;}
        .step-status{font-size:11px;font-weight:700;letter-spacing:.5px;}
        .step.done .step-status{color:var(--teal);}
        .step.active .step-status{color:rgba(var(--p-rgb),.95);}

        /* ── PROGRESS BAR ── */
        .progress-wrap{margin-bottom:24px;animation:fadeUp .5s .35s both;}
        .progress-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
        .progress-label{font-size:12px;font-weight:600;color:var(--muted);letter-spacing:.5px;}
        .progress-pct{font-size:12px;font-weight:700;color:rgba(var(--p-rgb),.9);}
        .progress-track{height:8px;border-radius:4px;background:rgba(255,255,255,.08);overflow:hidden;}
        .progress-fill{
            height:100%;width:0;border-radius:4px;
            background:linear-gradient(90deg,var(--p),var(--teal));
            box-shadow:0 0 12px rgba(var(--p-rgb),.5);
            transition:width 4.8s cubic-bezier(.4,0,.2,1);
        }

        /* ── TIMER ── */
        .timer-chip{
            display:flex;align-items:center;justify-content:center;gap:8px;
            padding:10px 18px;
            background:rgba(var(--p-rgb),.08);
            border:1px solid rgba(var(--p-rgb),.2);
            border-radius:40px;font-size:12.5px;font-weight:600;
            color:rgba(255,255,255,.7);
            animation:fadeUp .5s .4s both;
        }

        /* ── FOOTER ── */
        .card-footer{
            margin-top:28px;padding-top:20px;
            border-top:1px solid var(--border);
            display:flex;align-items:center;justify-content:space-between;
            animation:fadeUp .5s .45s both;
        }
        .footer-copy{font-size:11px;color:rgba(255,255,255,.3);}
        .footer-version{
            font-size:11px;font-weight:700;
            background:rgba(var(--p-rgb),.12);
            border:1px solid rgba(var(--p-rgb),.22);
            color:rgba(var(--p-rgb),.9);
            padding:3px 10px;border-radius:20px;
        }

        @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

        /* ── RESPONSIVE ── */
        @media(max-width:540px){
            .card{padding:32px 22px 28px;}
            .heading{font-size:20px;}
            .tb-full{display:none;}
            .brand-company{font-size:9.5px;}
        }
    </style>
</head>
<body>

<div class="bg-scene"></div>
<div class="bg-grid"></div>
<div class="bg-orb orb-1"></div>
<div class="bg-orb orb-2"></div>
<div class="bg-orb orb-3"></div>
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

        <div class="brand-header">
            <div class="brand-logo">L</div>
            <div class="brand-text">
                <div class="brand-name">
                    <em>L</em>aboratory <em>I</em>nformation <em>M</em>anagement <em>S</em>ystem
                </div>
                <div class="brand-company">PT. Evo Manufacturing Indonesia</div>
            </div>
            <div class="brand-badge">
                <div class="badge-dot"></div>
                SECURE
            </div>
        </div>

        <div class="shield-wrap">
            <div class="shield-ring">
                <div class="shield-icon" id="mainIcon">🔐</div>
            </div>
        </div>

        <div class="heading-wrap">
            <h1 class="heading">Sesi Berakhir dengan Aman</h1>
            <p class="sub">
                Sistem sedang membersihkan data sesi dan mengamankan<br>
                seluruh informasi laboratorium Anda.
            </p>
        </div>

        <div class="security-steps">
            <div class="step active" id="s1">
                <span class="step-icon">🔑</span>
                <span class="step-text">Menghapus token autentikasi</span>
                <span class="step-status">PROSES...</span>
            </div>
            <div class="step" id="s2">
                <span class="step-icon">🗄️</span>
                <span class="step-text">Membersihkan cache sesi lab</span>
                <span class="step-status">MENUNGGU</span>
            </div>
            <div class="step" id="s3">
                <span class="step-icon">🔒</span>
                <span class="step-text">Mengenkripsi log aktivitas</span>
                <span class="step-status">MENUNGGU</span>
            </div>
            <div class="step" id="s4">
                <span class="step-icon">🛡️</span>
                <span class="step-text">Memverifikasi keamanan data</span>
                <span class="step-status">MENUNGGU</span>
            </div>
        </div>

        <div class="progress-wrap">
            <div class="progress-header">
                <span class="progress-label">SECURING DATA</span>
                <span class="progress-pct" id="pct">0%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="fill"></div>
            </div>
        </div>

        <div class="timer-chip">
            <span>⏱</span>
            <span>Mengalihkan ke halaman login dalam <strong id="countdown">5</strong> detik</span>
        </div>

        <div class="card-footer">
            <span class="footer-copy">© <script>document.write(new Date().getFullYear())</script> PT. Evo Manufacturing Indonesia</span>
            <span class="footer-version">LIMS v3.0</span>
        </div>

    </div>
</div>

<script>
(function(){
    var c=document.getElementById('particles');
    for(var i=0;i<36;i++){
        var d=document.createElement('div');
        var s=Math.random()*4+2;
        d.style.cssText=[
            'position:fixed','border-radius:50%','pointer-events:none','z-index:1',
            'width:'+s+'px','height:'+s+'px',
            'left:'+(Math.random()*100)+'%',
            'top:'+(Math.random()*100)+'%',
            'background:rgba(64,81,137,'+(Math.random()*.28+.06)+')',
            'animation:floatDot '+(Math.random()*8+8).toFixed(1)+'s ease-in-out '+(Math.random()*6).toFixed(1)+'s infinite'
        ].join(';');
        c.appendChild(d);
    }
    var st=document.createElement('style');
    st.textContent='@keyframes floatDot{0%,100%{transform:translateY(0) scale(1);opacity:.3}50%{transform:translateY(-22px) scale(1.2);opacity:.6}}';
    document.head.appendChild(st);
}());

const steps=[{id:'s1',label:'SELESAI ✓'},{id:'s2',label:'SELESAI ✓'},{id:'s3',label:'SELESAI ✓'},{id:'s4',label:'SELESAI ✓'}];
const delays=[0,1100,2100,3000];
const pctMilestones=[0,25,55,80,100];

function setDone(el,label){el.classList.remove('active');el.classList.add('done');el.querySelector('.step-status').textContent=label;}
function setActive(el){el.classList.add('active');el.querySelector('.step-status').textContent='PROSES...';}

delays.forEach(function(d,i){
    setTimeout(function(){
        if(i>0) setDone(document.getElementById(steps[i-1].id),steps[i-1].label);
        if(i<steps.length-1) setActive(document.getElementById(steps[i+1].id));
        document.getElementById('fill').style.width=pctMilestones[i+1]+'%';
        animatePct(pctMilestones[i+1]);
    },d);
});

setTimeout(function(){
    setDone(document.getElementById(steps[steps.length-1].id),steps[steps.length-1].label);
    document.getElementById('fill').style.width='100%';
    animatePct(100);
    document.getElementById('mainIcon').textContent='✅';
},3800);

var currentPct=0;
function animatePct(target){
    var el=document.getElementById('pct');
    var step=target>currentPct?1:-1;
    var iv=setInterval(function(){
        currentPct+=step;
        el.textContent=currentPct+'%';
        if(currentPct===target) clearInterval(iv);
    },18);
}

var secs=5;
var cdEl=document.getElementById('countdown');
var cdIv=setInterval(function(){
    secs--;
    cdEl.textContent=secs;
    if(secs<=0){
        clearInterval(cdIv);
        localStorage.removeItem('SSID_EmI_Lab_EVO_RS');
        window.location.href='/';
    }
},1000);
</script>
</body>
</html>
