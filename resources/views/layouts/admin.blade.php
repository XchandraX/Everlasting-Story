<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal Admin | Everlasting</title>
    <link href="{{ asset('assets/css/templatemo-nexus-style.css') }}" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false, // Mematikan preflight agar tidak bentrok dengan CSS manual
            }
        }
    </script>
    <style>
        /* Custom scrollbar untuk sidebar agar tetap estetik */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        @keyframes scanline {
            0% {
                bottom: 100%;
            }

            100% {
                bottom: -100%;
            }
        }

        .scanline-effect {
            position: absolute;
            width: 100%;
            height: 10px;
            background: rgba(6, 182, 212, 0.1);
            animation: scanline 8s linear infinite;
            pointer-events: none;
        }

        /* TERMINAL PAGINATION CUSTOM */
        .btn-nav-terminal {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.03);
            border: 1px border rgba(6, 182, 212, 0.2);
            border-radius: 8px;
            color: #06b6d4;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }

        .btn-nav-terminal:hover:not(.cursor-not-allowed) {
            background: rgba(6, 182, 212, 0.1);
            border-color: #06b6d4;
            box-shadow: 0 0 15px rgba(6, 182, 212, 0.2);
            transform: translateY(-2px);
        }

        .active-page-number {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 900;
            color: #db2777;
            /* Pink */
            text-shadow: 0 0 10px rgba(219, 39, 119, 0.5);
            position: relative;
        }

        .active-page-number::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #db2777;
            box-shadow: 0 0 8px #db2777;
        }

        .page-number-link {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: bold;
            color: #4b5563;
            /* gray-600 */
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .page-number-link:hover {
            color: #06b6d4;
            /* Cyan */
            transform: scale(1.2);
        }
    </style>
</head>

<body class="bg-[#020617] text-white font-mono overflow-x-hidden">

    <!-- Efek Background Latar Tetap -->
    <div class="cyber-bg">
        <div class="cyber-gradient"></div>
        <div class="matrix-rain" id="matrixRain"></div>
    </div>
    <div class="particles" id="particlesContainer"></div>
    <div class="data-streams" id="dataStreams"></div>
    <div class="scanlines"></div>

    <!-- HEADER MOBILE (Hanya muncul di HP) -->
    <div
        class="md:hidden flex items-center justify-between bg-black/90 p-4 border-b border-cyan-500/20 sticky top-0 z-[120]">
        <h1 class="text-lg font-black tracking-widest uppercase m-0 p-0 leading-tight">
            <span class="text-cyan-400">Admin</span><span class="text-pink-600">Terminal</span>
        </h1>
        <button id="mobileMenuBtn" class="text-cyan-500 focus:outline-none">
            <i class="bi bi-list text-3xl"></i>
        </button>
    </div>

    <!-- OVERLAY (Latar gelap saat menu HP terbuka) -->
    <div id="sidebarOverlay"
        class="fixed inset-0 bg-black/80 z-[80] hidden transition-opacity duration-300 opacity-0 md:hidden"></div>

    <div class="flex min-h-screen relative z-10">

        <!-- SIDEBAR (Disesuaikan untuk Mobile & Desktop) -->
        <!-- Tambahan class: fixed, transform, -translate-x-full untuk sembunyi di HP, dan md:relative md:translate-x-0 untuk tampil di Desktop -->
        <aside id="sidebar"
            class="w-72 bg-black/90 md:bg-black/80 backdrop-blur-2xl border-r border-cyan-500/20 flex flex-col h-screen fixed md:sticky top-0 z-[100] shadow-[10px_0_30px_rgba(0,0,0,0.5)] overflow-hidden transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">

            <div class="scanline-effect absolute inset-0 pointer-events-none opacity-10"></div>

            <!-- Tombol Close Menu di Sidebar (Hanya untuk HP) -->
            <div class="p-8 border-b border-cyan-500/10 flex-none relative z-[110] flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-black tracking-widest uppercase m-0 p-0 leading-tight">
                        <span class="text-cyan-400">Admin</span><span class="text-pink-600">Terminal</span>
                    </h1>
                    <p class="text-[10px] text-gray-500 mt-2 uppercase tracking-tighter font-mono m-0 p-0">//
                        System_Authenticated</p>
                </div>
                <button id="closeMenuBtn" class="md:hidden text-pink-500">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>

            <!-- Navigasi Sidebar -->
            <nav
                class="flex-grow p-6 space-y-2 overflow-y-auto no-scrollbar relative z-[110] bg-transparent border-none">
                <a href="{{ route('admin.dashboard') }}"
                    class="flex items-center p-3 rounded-xl transition {{ request()->routeIs('admin.dashboard') ? 'bg-cyan-500/20 border border-cyan-500/50 text-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.2)]' : 'hover:bg-white/5 text-gray-400' }}">
                    <i class="bi bi-cpu mr-3"></i> Core Dashboard
                </a>

                <a href="{{ route('admin.categories.index') }}"
                    class="flex items-center p-3 rounded-xl transition {{ request()->routeIs('admin.categories.*') ? 'bg-cyan-500/20 border border-cyan-500/50 text-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.2)]' : 'hover:bg-white/5 text-gray-400' }}">
                    <i class="bi bi-grid-3x3-gap mr-3"></i> Data Clusters
                </a>

                <a href="{{ route('admin.images.index') }}"
                    class="flex items-center p-3 rounded-xl transition {{ request()->routeIs('admin.images.*') ? 'bg-cyan-500/20 border border-cyan-500/50 text-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.2)]' : 'hover:bg-white/5 text-gray-400' }}">
                    <i class="bi bi-images mr-3"></i> Photo Assets
                </a>

                <div class="pt-6 border-t border-cyan-500/5 mt-6">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center p-3 text-pink-500 hover:bg-pink-500/10 rounded-xl transition border border-transparent hover:border-pink-500/30 cursor-pointer bg-transparent uppercase font-bold text-[10px] tracking-widest p-0 m-0">
                            <i class="bi bi-power mr-3"></i> Terminate Session
                        </button>
                    </form>
                </div>
            </nav>

            <div class="p-6 border-t border-cyan-500/10 bg-cyan-950/20 flex-none relative z-[110]">
                <p class="text-[9px] text-cyan-500 font-mono mb-3 tracking-[0.2em] uppercase opacity-70">
                    Visual_Link_Active</p>

                <!-- Animasi dan Status -->
                <div class="flex items-end gap-1 h-8 mb-4">
                    <div class="w-1 bg-cyan-500 animate-[pulse_1s_infinite_100ms]" style="height: 40%"></div>
                    <div class="w-1 bg-pink-500 animate-[pulse_1s_infinite_300ms]" style="height: 80%"></div>
                    <div class="w-1 bg-cyan-500 animate-[pulse_1s_infinite_500ms]" style="height: 60%"></div>
                    <div class="w-1 bg-cyan-500 animate-[pulse_1s_infinite_200ms]" style="height: 90%"></div>
                    <div class="w-1 bg-pink-500 animate-[pulse_1s_infinite_400ms]" style="height: 50%"></div>
                </div>

                <div class="space-y-1">
                    <div class="flex items-center justify-between text-[9px] font-mono italic">
                        <span class="text-gray-500 uppercase tracking-tighter">Mem_Load:</span>
                        <span class="text-cyan-400 animate-pulse">42.8%</span>
                    </div>
                    <div class="flex items-center justify-between text-[9px] font-mono italic">
                        <span class="text-gray-500 uppercase tracking-tighter">Gpu_Sync:</span>
                        <span class="text-pink-500">ACTIVE</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- KONTEN UTAMA -->
        <!-- Tambahan p-4 md:p-10 agar jarak di HP tidak terlalu lebar -->
        <main class="flex-1 overflow-y-auto p-4 md:p-10 bg-black/20 w-full overflow-x-hidden">
            @if (session('success'))
                <div id="success-alert"
                    class="mb-6 p-4 bg-cyan-500/10 border border-cyan-500/50 text-cyan-400 rounded-xl flex items-center justify-between group shadow-[0_0_20px_rgba(6,182,212,0.1)]">
                    <div class="flex items-center">
                        <div class="w-2 h-2 bg-cyan-500 rounded-full animate-pulse mr-3 shadow-[0_0_8px_#06b6d4]"></div>
                        <span class="text-xs font-mono uppercase tracking-widest">{{ session('success') }}</span>
                    </div>
                    <button onclick="document.getElementById('success-alert').remove()"
                        class="bg-transparent border-none text-cyan-500 hover:text-white cursor-pointer transition-colors p-1">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>
            @endif

            <div class="relative">
                @yield('admin_content')
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- SCRIPT BARU UNTUK MENGATUR SIDEBAR MOBILE -->
    <script>
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const closeMenuBtn = document.getElementById('closeMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleMenu() {
            const isClosed = sidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        mobileMenuBtn.addEventListener('click', toggleMenu);
        closeMenuBtn.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-btn') || e.target.closest('.delete-btn')) {
                const button = e.target.classList.contains('delete-btn') ? e.target : e.target.closest(
                    '.delete-btn');
                const form = button.closest('.delete-form');

                Swal.fire({
                    title: '<span class="text-pink-500 font-mono tracking-tighter uppercase italic">CRITICAL_WARNING</span>',
                    html: '<p class="text-gray-400 text-xs font-mono lowercase">// asset_erasure_detected: this_action_cannot_be_undone</p>',
                    background: '#020617',
                    showCancelButton: true,
                    confirmButtonColor: '#db2777',
                    cancelButtonColor: 'transparent',
                    confirmButtonText: 'EXECUTE_PURGE',
                    cancelButtonText: 'ABORT_MISSION',
                    reverseButtons: true,
                    customClass: {
                        popup: 'border border-pink-500/30 rounded-3xl backdrop-blur-xl',
                        confirmButton: 'rounded-xl font-black px-6 py-3 text-[10px] uppercase tracking-widest transition-all hover:shadow-[0_0_15px_rgba(219,39,119,0.5)]',
                        cancelButton: 'text-gray-500 font-bold text-[10px] uppercase tracking-widest'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'PURGING_DATA...',
                            html: '<div class="h-1 w-20 bg-pink-500 mx-auto animate-pulse"></div>',
                            background: '#020617',
                            showConfirmButton: false,
                            allowOutsideClick: false
                        });
                        form.submit();
                    }
                });
            }
        });
    </script>
    <script src="{{ asset('assets/js/templatemo-nexus-scripts.js') }}"></script>
</body>

</html>
