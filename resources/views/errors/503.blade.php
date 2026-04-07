<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>503</title>
        <link
            href="https://fonts.googleapis.com/css2?family=Outfit:wght@200;500;800&display=swap"
            rel="stylesheet"
        />
        <style>
            :root {
                --primary: #505f91;
                --darker: #2c3552;
                --light: #ffffff;
                --glass: rgba(255, 255, 255, 0.05);
                --border: rgba(255, 255, 255, 0.1);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: "Outfit", sans-serif;
                height: 100vh;
                width: 100vw;
                overflow: hidden;
                display: flex;
                justify-content: center;
                align-items: center;
                /* Gradient Background Elegan dari warna #505F91 */
                background: radial-gradient(
                    circle at 50% 50%,
                    #505f91 0%,
                    #1e2438 100%
                );
                color: var(--light);
            }

            /* Canvas untuk partikel background */
            #noise-canvas {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1;
                opacity: 0.4;
            }

            /* Container Kartu Glassmorphism */
            .glass-panel {
                position: relative;
                z-index: 10;
                background: var(--glass);
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
                border: 1px solid var(--border);
                border-radius: 24px;
                padding: 4rem 3rem;
                text-align: center;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);

                /* Persiapan untuk efek 3D Tilt */
                transform-style: preserve-3d;
                transform: perspective(1000px);
                transition: transform 0.1s ease-out;
            }

            /* Elemen Dekorasi di dalam kartu */
            .status-badge {
                display: inline-block;
                padding: 8px 16px;
                background: rgba(255, 87, 87, 0.15);
                color: #ff6b6b;
                border-radius: 50px;
                font-size: 0.75rem;
                font-weight: 500;
                letter-spacing: 2px;
                text-transform: uppercase;
                margin-bottom: 2rem;
                border: 1px solid rgba(255, 87, 87, 0.3);
                /* Efek kedalaman 3D */
                transform: translateZ(20px);
            }

            h1 {
                font-size: 8rem;
                line-height: 1;
                font-weight: 800;
                margin-bottom: 0.5rem;
                background: linear-gradient(to bottom right, #ffffff, #8a9bc7);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                /* Efek kedalaman 3D lebih tinggi */
                transform: translateZ(50px);
                text-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }

            h2 {
                font-size: 1.5rem;
                font-weight: 500;
                margin-bottom: 1rem;
                color: #dbe4ff;
                transform: translateZ(30px);
            }

            p {
                font-size: 0.95rem;
                color: #8f9bb3;
                line-height: 1.6;
                font-weight: 200;
                transform: translateZ(20px);
            }

            /* Garis progres animasi */
            .loading-bar {
                width: 100%;
                height: 2px;
                background: rgba(255, 255, 255, 0.1);
                margin-top: 3rem;
                position: relative;
                overflow: hidden;
                border-radius: 2px;
                transform: translateZ(10px);
            }

            .loading-bar::after {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                height: 100%;
                width: 30%;
                background: #505f91;
                box-shadow: 0 0 10px #505f91;
                animation: loading 2s infinite ease-in-out;
            }

            @keyframes loading {
                0% {
                    left: -30%;
                }
                100% {
                    left: 100%;
                }
            }

            /* Responsif */
            @media (max-width: 768px) {
                h1 {
                    font-size: 5rem;
                }
                .glass-panel {
                    padding: 2rem;
                }
            }
        </style>
    </head>
    <body>
        <canvas id="noise-canvas"></canvas>

        <div class="glass-panel" id="tilt-card">
            <div class="status-badge">● Disconnected</div>

            <h1>503</h1>
            <h2>Layanan Tidak Tersedia</h2>
            <div class="loading-bar"></div>
        </div>

        <script>
            // --- BAGIAN 1: BACKGROUND PARTICLES (Floating Data) ---
            const canvas = document.getElementById("noise-canvas");
            const ctx = canvas.getContext("2d");

            let width, height;
            let particles = [];

            function resize() {
                width = canvas.width = window.innerWidth;
                height = canvas.height = window.innerHeight;
            }

            class Particle {
                constructor() {
                    this.x = Math.random() * width;
                    this.y = Math.random() * height;
                    this.vx = (Math.random() - 0.5) * 0.5; // Gerakan lambat
                    this.vy = (Math.random() - 0.5) * 0.5;
                    this.size = Math.random() * 2;
                    this.alpha = Math.random() * 0.5;
                }

                update() {
                    this.x += this.vx;
                    this.y += this.vy;

                    // Memantul pelan di dinding
                    if (this.x < 0 || this.x > width) this.vx *= -1;
                    if (this.y < 0 || this.y > height) this.vy *= -1;

                    // Efek kedip (flicker) seperti sinyal hilang
                    if (Math.random() > 0.98) {
                        this.alpha = Math.random() * 0.5;
                    }
                }

                draw() {
                    ctx.fillStyle = `rgba(255, 255, 255, ${this.alpha})`;
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fill();
                }
            }

            function initParticles() {
                particles = [];
                for (let i = 0; i < 150; i++) {
                    // Jumlah partikel
                    particles.push(new Particle());
                }
            }

            function animate() {
                ctx.clearRect(0, 0, width, height);
                particles.forEach((p) => {
                    p.update();
                    p.draw();
                });
                requestAnimationFrame(animate);
            }

            window.addEventListener("resize", () => {
                resize();
                initParticles();
            });

            // --- BAGIAN 2: 3D TILT EFFECT (Interaksi Mouse Mewah) ---
            const card = document.getElementById("tilt-card");

            document.addEventListener("mousemove", (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 25; // Sensitivitas X
                const yAxis = (window.innerHeight / 2 - e.pageY) / 25; // Sensitivitas Y

                // Rotasi kartu berdasarkan posisi mouse
                card.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg) perspective(1000px)`;
            });

            // Kembalikan ke posisi semula saat mouse keluar (opsional, tapi bagus untuk UX)
            document.addEventListener("mouseleave", () => {
                card.style.transform = `rotateY(0deg) rotateX(0deg) perspective(1000px)`;
                card.style.transition = "transform 0.5s ease";
            });

            // Hapus transisi saat mouse masuk agar gerakan responsif
            document.addEventListener("mouseenter", () => {
                card.style.transition = "none";
            });

            // Inisialisasi
            resize();
            initParticles();
            animate();
        </script>
    </body>
</html>
