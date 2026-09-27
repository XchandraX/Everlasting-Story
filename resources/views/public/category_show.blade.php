@extends('layouts.main')
@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
@endpush
@section('content')
    <div class="container mx-auto px-4 sm:px-6 py-8 md:py-12">
        <div class="mb-8 text-center">
            <h1 class="text-2xl md:text-4xl font-bold text-white tracking-tight">{{ $category->nama_kategori }}</h1>
            <p class="text-gray-400 mt-2 mb-8 text-sm md:text-base italic">// Capture the moment, secure the data.</p>

            {{-- Tombol Filter --}}
            <div id="filter-buttons" class="filter-switch">
                <button id="filter-images" class="filter-btn active" data-filter="image">
                    <i class="bi bi-camera-fill"></i> Images
                </button>
                <button id="filter-videos" class="filter-btn" data-filter="video">
                    <i class="bi bi-film"></i> Videos
                </button>
            </div>

            {{-- ✅ TOMBOL DOWNLOAD (CLIENT-SIDE ZIP) --}}
            <div class="flex flex-wrap gap-3 justify-center mt-6 mb-4">
                {{-- Download halaman ini saja (client-side) --}}
                <button type="button" class="download-btn"
                    onclick="downloadPageClient('{{ $filter }}', {{ $images->currentPage() }})">
                    <i class="bi bi-download"></i>
                    Halaman Ini ({{ $images->count() }} file)
                </button>

                {{-- Download semua (client-side) --}}
                <button type="button" id="download-all-btn" class="download-btn"
                    onclick="downloadAllClient('{{ $filter }}')">
                    <i class="bi bi-cloud-arrow-down"></i>
                    <span id="download-label">
                        Semua {{ $filter === 'video' ? 'Videos' : 'Images' }}
                    </span>
                    <span class="text-[10px] opacity-80 font-mono">
                        ({{ $totalCount }} file · {{ format_bytes($totalSize) }})
                    </span>
                </button>
            </div>

            {{-- Progress bar --}}
            <div id="download-progress" class="hidden mt-3 w-full max-w-md mx-auto">
                <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden border border-cyan-500/30">
                    <div id="download-progress-bar"
                        class="h-full bg-gradient-to-r from-green-400 to-cyan-500 transition-all duration-150"
                        style="width: 0%"></div>
                </div>
                <p id="download-progress-text" class="text-[10px] text-cyan-400 font-mono mt-2 text-center">0%</p>
            </div>


        </div>

        {{-- GRID MODE --}}
        <div id="view-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4">
            @foreach ($images as $image)
                <div class="group flex flex-col bg-slate-900/50 rounded-xl overflow-hidden border border-white/10 hover:border-cyan-500/50 transition-all duration-500 shadow-lg"
                    data-media-type="{{ $image->media_type }}">
                    @if ($image->media_type == 'video')
                        @php
                            // Generate poster URL dari Cloudinary (frame di detik 0.5)
                            $posterUrl = preg_replace('/\/upload\/(v\d+\/)?/', '/upload/so_0.5/', $image->file_path);
                            $posterUrl = preg_replace('/\.(mp4|webm|mov|avi|mkv)$/', '.jpg', $posterUrl);
                        @endphp
                        <a href="{{ $image->file_path }}" class="glightbox block overflow-hidden aspect-[4/6] relative"
                            data-glightbox="type: video; title: {{ $image->title }}; description: {{ $image->description ?? 'Video Asset' }}">
                            <video
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                                muted preload="metadata" poster="{{ $posterUrl }}">
                                <source src="{{ $image->file_path }}" type="video/mp4">
                            </video>
                            <div
                                class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <div
                                    class="w-12 h-12 rounded-full bg-cyan-500/80 flex items-center justify-center backdrop-blur-sm shadow-lg">
                                    <i class="bi bi-play-fill text-black text-2xl ml-0.5"></i>
                                </div>
                            </div>
                        </a>
                    @else
                        <a href="{{ $image->file_path }}" class="glightbox block overflow-hidden aspect-[4/6]"
                            data-glightbox="title: {{ $image->title }}; description: {{ $image->description ?? 'Image Asset' }}">
                            <img src="{{ $image->file_path }}" alt="{{ $image->title }}" loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                        </a>
                    @endif
                    <div class="p-3 bg-black/40 border-t border-white/5">
                        <p
                            class="text-[10px] md:text-xs font-bold text-gray-300 group-hover:text-cyan-400 transition-colors uppercase tracking-widest truncate">
                            {{ $image->title }}
                        </p>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[8px] text-gray-600 uppercase font-mono tracking-tighter">
                                {{ $image->media_type == 'video' ? 'Asset_Video' : 'Asset_Rec' }}
                            </p>
                            <span class="text-[8px] text-cyan-500 font-mono font-bold">
                                {{ format_bytes($image->file_size) }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- PAGINATION DENGAN INPUT ANGKA (JUMP TO PAGE) --}}
        <div class="mt-16 mb-12 flex flex-col items-center">
            <div class="pagination-wrapper flex flex-wrap items-center justify-center gap-2">
                {{-- Tombol Previous --}}
                @if (!$images->onFirstPage())
                    <a href="{{ $images->previousPageUrl() . '&filter=' . request('filter', 'image') }}"
                        class="btn-nav hover:bg-cyan-500/10 transition-all px-3 py-2 rounded-lg">
                        <i class="bi bi-chevron-left text-cyan-500"></i>
                    </a>
                @endif

                {{-- Nomor halaman (desktop) --}}
                <div class="hidden md:flex gap-2">
                    @foreach ($images->getUrlRange(max(1, $images->currentPage() - 2), min($images->lastPage(), $images->currentPage() + 2)) as $page => $url)
                        @if ($page == $images->currentPage())
                            <span
                                class="active-page scale-110 px-3 py-1 rounded-lg bg-cyan-500/20 text-cyan-400 border border-cyan-500/50">{{ $page }}</span>
                        @else
                            <a href="{{ $url . '&filter=' . request('filter', 'image') }}"
                                class="hover:text-cyan-400 transition-colors px-3 py-1 rounded-lg">{{ $page }}</a>
                        @endif
                    @endforeach
                </div>

                {{-- Form Lompat Halaman (Input Angka) --}}
                <div class="jump-control">
                    <span class="jump-label">Go to</span>
                    <input type="number" id="page-input" min="1" max="{{ $images->lastPage() }}"
                        value="{{ $images->currentPage() }}" class="jump-input">
                    <button id="go-to-page" class="jump-btn">JUMP</button>
                </div>

                {{-- Tombol Next --}}
                @if ($images->hasMorePages())
                    <a href="{{ $images->nextPageUrl() . '&filter=' . request('filter', 'image') }}"
                        class="btn-nav hover:bg-cyan-500/10 transition-all px-3 py-2 rounded-lg">
                        <i class="bi bi-chevron-right text-cyan-500"></i>
                    </a>
                @endif
            </div>

            {{-- Info halaman untuk mobile --}}
            <div class="md:hidden mt-4 text-center">
                <span
                    class="text-xs font-mono font-bold text-cyan-500 border border-cyan-500/30 px-3 py-1 rounded-full bg-cyan-500/5">
                    INDEX_{{ $images->currentPage() }}/{{ $images->lastPage() }}
                </span>
            </div>

            <p class="mt-8 text-[12px] text-gray-600 uppercase tracking-[0.4em] text-center font-mono animate-pulse">
                Captured Data Stream: {{ $images->firstItem() }}-{{ $images->lastItem() }} // Total:
                {{ $images->total() }}
            </p>
        </div>
    </div>

    <style>
        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #0f172a;
            background: linear-gradient(135deg, #22c55e, #06b6d4);
            border-radius: 40px;
            box-shadow: 0 0 20px rgba(34, 197, 94, 0.35);
            transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
            text-decoration: none;
            border: none;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.6);
            color: #000;
        }

        .download-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .download-btn.loading i:first-child {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .filter-switch {
            display: inline-flex;
            gap: 0.75rem;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            padding: 0.5rem;
            border-radius: 60px;
            border: 1px solid rgba(14, 165, 233, 0.3);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.1);
            margin-bottom: 2rem;
        }

        .filter-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.6rem;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: transparent;
            border: none;
            border-radius: 40px;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
            font-family: 'JetBrains Mono', monospace;
        }

        .filter-btn i {
            font-size: 1.1rem;
            transition: transform 0.2s;
        }

        .filter-btn:hover {
            color: #7dd3fc;
            background: rgba(14, 165, 233, 0.1);
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #0ea5e9, #a855f7);
            color: #0f172a;
            box-shadow: 0 0 15px rgba(14, 165, 233, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .filter-btn.active i {
            transform: scale(1.1);
            filter: drop-shadow(0 0 4px white);
        }

        .glightbox-clean .gslide-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: #0ea5e9;
            text-shadow: 0 0 10px #0ea5e9;
        }

        .glightbox-clean .gslide-desc {
            font-family: monospace;
            font-size: 0.85rem;
            color: #cbd5e1;
        }

        .glightbox-clean .gclose,
        .glightbox-clean .gnext,
        .glightbox-clean .gprev {
            filter: drop-shadow(0 0 5px #0ea5e9);
        }

        /* Hide number input spinner */
        #page-input::-webkit-inner-spin-button,
        #page-input::-webkit-outer-spin-button {
            opacity: 0.5;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi GLightbox
            // Tandai bahwa halaman ini sudah handle sendiri
            window.__glightboxInitialized = true;

            const lightbox = GLightbox({
                selector: '.glightbox',
                touchNavigation: true,
                loop: false,
                autoplayVideos: true,
                zoomable: true,
                draggable: true,
                closeOnOutsideClick: true,
                moreLength: 0,
                slideEffect: 'fade',
                plyr: {
                    controls: ['play', 'pause', 'progress', 'current-time', 'duration', 'mute', 'volume',
                        'fullscreen'
                    ],
                    settings: ['captions', 'quality', 'speed'],
                },
                cssEfects: {
                    fade: {
                        in: 'fadeIn',
                        out: 'fadeOut'
                    }
                }
            });

            const viewGrid = document.getElementById('view-grid');
            const btnImages = document.getElementById('filter-images');
            const btnVideos = document.getElementById('filter-videos');
            const urlParams = new URLSearchParams(window.location.search);
            let currentFilter = urlParams.get('filter') || 'image';

            function updateFilterUI() {
                if (currentFilter === 'image') {
                    btnImages.classList.add('active');
                    btnVideos.classList.remove('active');
                } else {
                    btnVideos.classList.add('active');
                    btnImages.classList.remove('active');
                }
            }

            function applyFilter(filter) {
                if (filter === currentFilter) return;
                let url = new URL(window.location.href);
                url.searchParams.set('filter', filter);
                url.searchParams.delete('page'); // reset ke halaman 1
                window.location.href = url.toString();
            }

            btnImages.addEventListener('click', () => {
                applyFilter('image');
                syncDownloadButton();
            });
            btnVideos.addEventListener('click', () => {
                applyFilter('video');
                syncDownloadButton();
            });
            // Set active class sesuai URL awal
            updateFilterUI();

            // ========== FUNGSI JUMP TO PAGE ==========
            const pageInput = document.getElementById('page-input');
            const goToPageBtn = document.getElementById('go-to-page');

            function goToPage() {
                let targetPage = parseInt(pageInput.value);
                const maxPage = {{ $images->lastPage() }};
                if (isNaN(targetPage)) targetPage = 1;
                if (targetPage < 1) targetPage = 1;
                if (targetPage > maxPage) targetPage = maxPage;

                const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'image';
                let url = new URL(window.location.href);
                url.searchParams.set('page', targetPage);
                url.searchParams.set('filter', currentFilter);
                window.location.href = url.toString();
            }

            goToPageBtn.addEventListener('click', goToPage);
            pageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') goToPage();
            });

            // Pastikan semua link pagination membawa parameter filter
            document.querySelectorAll('.pagination-wrapper a').forEach(link => {
                let href = link.getAttribute('href');
                if (href && !href.includes('filter=')) {
                    let separator = href.includes('?') ? '&' : '?';
                    link.href = href + separator + 'filter=' + currentFilter;
                }
            });

            // ========== DOWNLOAD ALL ==========
            function syncDownloadButton() {
                const filter = new URLSearchParams(window.location.search).get('filter') || 'image';
                const btn = document.getElementById('download-all-btn');
                const label = document.getElementById('download-label');
                if (!btn || !label) return;

                btn.href = `{{ route('categories.download', $category->id) }}?filter=${filter}`;
                label.textContent = `Download All ${filter === 'video' ? 'Videos' : 'Images'}`;
            }
            window.confirmDownload = async function(el) {
                const filter = new URLSearchParams(window.location.search).get('filter') || 'image';
                const ok = confirm(
                    `Download semua ${filter === 'video' ? 'video' : 'foto'} di browser?\n\n` +
                    `File akan di-ZIP di browser, bukan di server.\n` +
                    `Jangan tutup tab ini sampai selesai.`
                );
                if (!ok) return false;

                el.classList.add('loading');
                const wrap = document.getElementById('download-progress');
                const bar = document.getElementById('download-progress-bar');
                const txt = document.getElementById('download-progress-text');
                wrap.classList.remove('hidden');
                txt.textContent = 'Mengambil daftar file...';

                try {
                    // 1. Ambil daftar URL dari server (JSON kecil)
                    const listUrl =
                        `{{ route('categories.download.list', $category->id) }}?filter=${filter}`;
                    const listRes = await fetch(listUrl);
                    if (!listRes.ok) throw new Error('Gagal ambil daftar file');

                    const data = await listRes.json();
                    const files = data.files;
                    const total = files.length;

                    if (total === 0) {
                        txt.textContent = '❌ Tidak ada file';
                        el.classList.remove('loading');
                        return false;
                    }

                    const zip = new JSZip();
                    let done = 0;
                    let failed = 0;

                    // 2. Download paralel (6 file bersamaan)
                    const concurrency = 6;
                    const queue = [...files];

                    async function worker() {
                        while (queue.length > 0) {
                            const file = queue.shift();
                            if (!file) break;

                            try {
                                const res = await fetch(file.url, {
                                    mode: 'cors'
                                });
                                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                                const blob = await res.blob();
                                zip.file(file.name, blob);
                            } catch (e) {
                                failed++;
                                console.warn('Gagal:', file.name, e.message);
                            }

                            done++;
                            const percent = (done / total) * 100;
                            bar.style.width = percent + '%';
                            txt.textContent = `${done}/${total} file (${percent.toFixed(0)}%)`;
                        }
                    }

                    await Promise.all(Array.from({
                        length: concurrency
                    }, () => worker()));

                    // 3. Generate ZIP di browser
                    txt.textContent = 'Membuat ZIP...';
                    bar.style.width = '95%';

                    const zipBlob = await zip.generateAsync({
                            type: 'blob',
                            compression: 'STORE'
                        }, // STORE = tidak compress (media sudah compressed)
                        (meta) => {
                            const p = 95 + (meta.percent * 0.05);
                            bar.style.width = p + '%';
                            txt.textContent = `Membuat ZIP: ${meta.percent.toFixed(0)}%`;
                        }
                    );

                    // 4. Trigger download
                    const blobUrl = URL.createObjectURL(zipBlob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = `{{ Str::slug($category->nama_kategori) }}-${filter}-${Date.now()}.zip`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(blobUrl);

                    bar.style.width = '100%';
                    txt.textContent = failed > 0 ?
                        `✅ Selesai! (${failed} file gagal)` :
                        `✅ Selesai! (${total} file)`;

                    setTimeout(() => {
                        wrap.classList.add('hidden');
                        bar.style.width = '0%';
                        el.classList.remove('loading');
                    }, 5000);

                } catch (err) {
                    console.error(err);
                    txt.textContent = '❌ ' + err.message;
                    el.classList.remove('loading');
                }

                return false;
            };

            // Helper
            function formatBytes(bytes) {
                if (!bytes) return '0 B';
                const u = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(1024));
                return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
            }

            syncDownloadButton();
        });
    </script>
@endsection
