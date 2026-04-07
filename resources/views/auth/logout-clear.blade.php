<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Sampai Jumpa Kembali</title>
        <link
            href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
        />
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
        />
        <style>
            :root {
                --primary: #6c5ce7;
                --secondary: #a29bfe;
                --accent: #fd79a8;
                --dark: #2d3436;
                --light: #f5f6fa;
                --success: #00b894;
            }

            body {
                margin: 0;
                font-family: "Poppins", sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: var(--light);
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                flex-direction: column;
                text-align: center;
                overflow: hidden;
            }

            .particles {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 0;
            }

            .particle {
                position: absolute;
                background: rgba(255, 255, 255, 0.5);
                border-radius: 50%;
                pointer-events: none;
            }

            .logout-container {
                position: relative;
                z-index: 1;
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 24px;
                padding: 50px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                max-width: 450px;
                width: 90%;
                border: 1px solid rgba(255, 255, 255, 0.2);
                transform-style: preserve-3d;
                perspective: 1000px;
                overflow: hidden;
            }

            .logout-container::before {
                content: "";
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: linear-gradient(
                    to bottom right,
                    rgba(255, 255, 255, 0.1) 0%,
                    rgba(255, 255, 255, 0) 60%
                );
                transform: rotate(30deg);
                z-index: -1;
            }

            h1 {
                font-size: 2.2rem;
                margin-bottom: 15px;
                font-weight: 600;
                background: linear-gradient(to right, #fff, #a29bfe);
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            p {
                font-size: 1rem;
                opacity: 0.9;
                margin-bottom: 30px;
                line-height: 1.6;
            }

            .spinner-container {
                position: relative;
                width: 80px;
                height: 80px;
                margin: 30px auto;
            }

            .spinner {
                position: absolute;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                border: 3px solid transparent;
                border-top-color: var(--accent);
                animation: spin 1.5s cubic-bezier(0.68, -0.55, 0.27, 1.55)
                    infinite;
            }

            .spinner:nth-child(2) {
                border-top-color: var(--secondary);
                animation-delay: 0.3s;
            }

            .spinner:nth-child(3) {
                border-top-color: var(--primary);
                animation-delay: 0.6s;
            }

            .progress-bar {
                height: 6px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 3px;
                margin-top: 40px;
                overflow: hidden;
            }

            .progress {
                height: 100%;
                width: 0;
                background: linear-gradient(
                    to right,
                    var(--primary),
                    var(--accent)
                );
                border-radius: 3px;
                transition: width 5s ease-out;
            }

            .goodbye-message {
                margin-top: 30px;
                font-size: 0.9rem;
                opacity: 0.8;
                font-style: italic;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }

            @keyframes float {
                0%,
                100% {
                    transform: translateY(0);
                }
                50% {
                    transform: translateY(-15px);
                }
            }

            .animate-float {
                animation: float 3s ease-in-out infinite;
            }

            /* Responsive adjustments */
            @media (max-width: 480px) {
                .logout-container {
                    padding: 30px;
                }

                h1 {
                    font-size: 1.8rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="particles" id="particles"></div>

        <div
            class="logout-container animate__animated animate__fadeIn animate__slower"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="64"
                height="64"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="feather feather-log-out"
                style="margin-bottom: 20px; color: var(--accent)"
            >
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>

            <h1 class="animate__animated animate__fadeInDown">Sampai Jumpa!</h1>
            <p class="animate__animated animate__fadeIn animate__delay-1s">
                Anda telah berhasil keluar. Membersihkan data sesi dan
                mengamankan informasi Anda...
            </p>

            <div
                class="spinner-container animate__animated animate__zoomIn animate__delay-1s"
            >
                <div class="spinner"></div>
                <div class="spinner"></div>
                <div class="spinner"></div>
            </div>

            <div class="progress-bar">
                <div class="progress" id="progress"></div>
            </div>

            <div
                class="goodbye-message animate__animated animate__fadeIn animate__delay-2s"
            >
                "Terima kasih telah menggunakan layanan kami"
            </div>
        </div>

        <script>
            // Create floating particles
            function createParticles() {
                const particlesContainer = document.getElementById("particles");
                const particleCount = 30;

                for (let i = 0; i < particleCount; i++) {
                    const particle = document.createElement("div");
                    particle.classList.add("particle");

                    // Random properties
                    const size = Math.random() * 5 + 2;
                    const posX = Math.random() * 100;
                    const posY = Math.random() * 100;
                    const delay = Math.random() * 5;
                    const duration = Math.random() * 10 + 10;
                    const opacity = Math.random() * 0.5 + 0.1;

                    particle.style.width = `${size}px`;
                    particle.style.height = `${size}px`;
                    particle.style.left = `${posX}%`;
                    particle.style.top = `${posY}%`;
                    particle.style.opacity = opacity;
                    particle.style.animation = `float ${duration}s ease-in-out ${delay}s infinite`;

                    particlesContainer.appendChild(particle);
                }
            }

            // Animate progress bar
            function animateProgress() {
                const progressBar = document.getElementById("progress");
                progressBar.style.width = "100%";
            }

            // Redirect after delay
            function redirect() {
                localStorage.removeItem("SSID_EmI_Lab_EVO_RS");
                window.location.href = "/";
            }

            // Initialize
            document.addEventListener("DOMContentLoaded", () => {
                createParticles();
                animateProgress();
                setTimeout(redirect, 5000);
            });
        </script>
    </body>
</html>
