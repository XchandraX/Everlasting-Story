<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Everlasting Story</title>
    <link rel="stylesheet" href="{{ asset('assets/css/templatemo-nexus-style.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            }
        }
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" />
</head>

<body id="top" class="bg-[#020617] text-white overflow-x-hidden">

    <div class="cyber-bg">
        <div class="cyber-gradient"></div>
        <div class="matrix-rain" id="matrixRain"></div>
    </div>
    <div class="particles" id="particlesContainer"></div>
    <div class="data-streams" id="dataStreams"></div>
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    <div class="orb orb3"></div>
    <div class="grid-overlay">
        <div class="grid-lines"></div>
    </div>
    <div class="scanlines"></div>
    <div class="noise-overlay"></div>

    <nav class="z-[10000]">
        <div class="nav-container">
            <a href="/" class="logo">EVERLASTING</a>

            <ul class="nav-links">
                <li>
                    <a href="/" class="hover:text-cyan-400 transition">Home</a>
                </li>
                <li>
                    <a href="{{ route('categories.index') }}" class="hover:text-cyan-400 transition">Categories</a>
                </li>
            </ul>

            <div class="nav-bottom hidden md:block">
                <a href="/login" class="cyber-button">Access Terminal</a>
            </div>

            <button class="mobile-menu-button" id="mobileMenuBtn">
                <div class="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </button>
        </div>
    </nav>

    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>

    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <a href="/" class="mobile-menu-logo">EVERLASTING</a>
            <button class="mobile-menu-close" id="mobileMenuClose">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="mobile-menu-nav">
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="{{ route('categories.index') }}">Categories</a></li>
            </ul>
        </div>
        <div class="mobile-menu-cta">
            <a href="/login" class="cyber-button block w-full text-center">Access Terminal</a>
        </div>
    </div>

    <main class="relative z-10 pt-28 md:pt-20">
        @yield('content')
    </main>

    <footer class="relative z-10 py-8 border-t border-cyan-500/10 mt-16 bg-black/20">
        <div class="text-center text-gray-500 text-xs md:text-sm italic px-4">
            &copy; 2026 Everlasting Story. Generated in Cyber Reality.
        </div>
    </footer>
<script src="{{ asset('assets/js/templatemo-nexus-scripts.js') }}"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">
<script src="https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js"></script>
<script>
    const lightbox = GLightbox({
        selector: '.glightbox',
        touchNavigation: true,
        loop: false,
        autoplayVideos: true,
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
    const mobileMenuClose = document.getElementById('mobileMenuClose');

    function openMenu() {
        mobileMenu.classList.add('active');
        mobileMenuOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeMenu() {
        mobileMenu.classList.remove('active');
        mobileMenuOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    mobileMenuBtn.addEventListener('click', openMenu);
    mobileMenuClose.addEventListener('click', closeMenu);
    mobileMenuOverlay.addEventListener('click', closeMenu);
});
</script>
</body>

</html>
