<?php

namespace App\Http\Controllers;

use App\Jobs\BuildCategoryZip;
use App\Models\Image;
use App\Models\Kategori;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ZipArchive;

class PublicController extends Controller
{
    // ============================================================
    // PUBLIC PAGES
    // ============================================================

    public function index()
    {
        $categories = Kategori::withCount('images')->latest()->take(6)->get();
        $latestImages = Image::latest()->take(8)->get();

        return view('public.home', compact('categories', 'latestImages'));
    }

    public function categories()
    {
        $categories = Kategori::withCount('images')->latest()->paginate(12);

        return view('public.categories', compact('categories'));
    }

    public function showCategory(Request $request, $id)
    {
        $category = Kategori::findOrFail($id);
        $filter = $request->get('filter', 'image');

        $images = Image::where('kategori_id', $id);
        if ($filter === 'image') {
            $images->where('media_type', 'image');
        } elseif ($filter === 'video') {
            $images->where('media_type', 'video');
        }
        $images = $images->latest()->paginate(12)->withQueryString();

        $totalSize = Image::where('kategori_id', $id)
            ->where('media_type', $filter)
            ->sum('file_size');

        $totalCount = Image::where('kategori_id', $id)
            ->where('media_type', $filter)
            ->count();

        return view('public.category_show', compact('category', 'images', 'filter', 'totalSize', 'totalCount'));
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');
        $images = Image::where('title', 'like', "%{$keyword}%")
            ->orWhere('description', 'like', "%{$keyword}%")
            ->latest()
            ->paginate(15);

        return view('public.search_results', compact('images', 'keyword'));
    }

    // ============================================================
    // DOWNLOAD — SYNC (ZIP langsung)
    // ============================================================

    /**
     * Download SEMUA file kategori sebagai ZIP (SYNC)
     * ⚠️ Cocok kalau total < 100 file / < 500 MB
     */
    public function downloadCategory(Request $request, $id)
    {
        $category = Kategori::findOrFail($id);
        $filter = $request->get('filter', 'image');

        $media = Image::where('kategori_id', $id)
            ->where('media_type', $filter)
            ->latest()
            ->get();

        return $this->buildZip($category, $media, $filter);
    }

    /**
     * Download HANYA file di halaman tertentu sebagai ZIP (SYNC)
     * ✅ Rekomendasi untuk kategori besar (314 file)
     */
    public function downloadPage(Request $request, $id)
    {
        $category = Kategori::findOrFail($id);
        $filter = $request->get('filter', 'image');
        $page = (int) $request->get('page', 1);

        // Harus SAMA dengan perPage di showCategory() → 12
        $perPage = 12;

        $media = Image::where('kategori_id', $id)
            ->where('media_type', $filter)
            ->latest()
            ->forPage($page, $perPage)
            ->get();

        return $this->buildZip($category, $media, $filter, "page-{$page}");
    }

    // ============================================================
    // CORE: buildZip() — Pool Guzzle (paralel)
    // ============================================================

    /**
     * Bangun ZIP dari collection Image menggunakan download paralel.
     * Concurrency: 15 file sekaligus → ~10-15x lebih cepat.
     */
    private function buildZip($category, $media, string $filter, ?string $suffix = null)
    {
        if ($media->isEmpty()) {
            return back()->with('error', 'Tidak ada file untuk diunduh.');
        }

        set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $tempDir = sys_get_temp_dir().'/everlasting_zip';
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipName = Str::slug($category->nama_kategori)
                 .'-'.$filter
                 .($suffix ? '-'.$suffix : '')
                 .'-'.now()->format('Ymd_His')
                 .'.zip';
        $zipPath = $tempDir.DIRECTORY_SEPARATOR.$zipName;

        // ============================================================
        // STEP 1: Siapkan metadata setiap file
        // ============================================================
        $meta = [];
        foreach ($media as $idx => $item) {
            $url = $this->resolveMediaUrl($item->file_path);
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)
                  ?: ($filter === 'video' ? 'mp4' : 'jpg');
            $safe = Str::slug($item->title ?: 'file') ?: 'file';

            $meta[$idx] = [
                'id' => $item->id,
                'title' => $item->title,
                'url' => $url,
                'ext' => $ext,
                'safe_title' => $safe,
                'expected_size' => (int) ($item->file_size ?? 0),
                'tmp_file' => tempnam(sys_get_temp_dir(), 'media_'),
                'success' => false,
                'attempts' => 0,
            ];
        }

        // ============================================================
        // STEP 2: Download paralel pakai Pool Guzzle
        // ============================================================
        $this->parallelDownload($meta, $filter);

        // ============================================================
        // STEP 3: Retry file yang gagal (sequential, lebih lambat tapi akurat)
        // ============================================================
        $failedIdx = array_keys(array_filter($meta, fn ($m) => ! $m['success']));
        if (! empty($failedIdx)) {
            \Log::info('Pool: '.count($failedIdx).' file gagal, retry sequential...');

            foreach ($failedIdx as $idx) {
                $this->retrySingle($meta[$idx], $filter);
            }
        }

        // ============================================================
        // STEP 4: Masukkan ke ZIP
        // ============================================================
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            foreach ($meta as $m) {
                @unlink($m['tmp_file']);
            }
            abort(500, 'Gagal membuat file ZIP.');
        }

        $counter = 1;
        $failed = [];

        foreach ($meta as $m) {
            if (! $m['success']) {
                $failed[] = $m['title'];
                @unlink($m['tmp_file']);

                continue;
            }

            $fileName = sprintf('%03d_%s.%s', $counter, $m['safe_title'], $m['ext']);
            $zip->addFile($m['tmp_file'], $fileName);
            $counter++;
        }

        $zip->close();

        // Cleanup
        foreach ($meta as $m) {
            @unlink($m['tmp_file']);
        }

        if ($counter === 1) {
            @unlink($zipPath);

            return back()->with('error', 'Semua file gagal diunduh.');
        }

        return response()
            ->download($zipPath, $zipName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    // ============================================================
    // DOWNLOAD LIST — Kirim daftar URL ke client (untuk JSZip)
    // ============================================================

    public function downloadList(Request $request, $id)
    {
        $category = Kategori::findOrFail($id);
        $filter = $request->get('filter', 'image');

        $media = Image::where('kategori_id', $id)
            ->where('media_type', $filter)
            ->latest()
            ->get();

        if ($media->isEmpty()) {
            return response()->json(['error' => 'Tidak ada file'], 404);
        }

        $files = $media->map(function ($item, $idx) use ($filter) {
            $url = $this->resolveMediaUrl($item->file_path);
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)
                 ?: ($filter === 'video' ? 'mp4' : 'jpg');
            $safe = Str::slug($item->title ?: 'file') ?: 'file';

            return [
                'url' => $url,
                'name' => sprintf('%03d_%s.%s', $idx + 1, $safe, $ext),
                'size' => (int) ($item->file_size ?? 0),
            ];
        });

        return response()->json([
            'category' => $category->nama_kategori,
            'filter' => $filter,
            'total' => $files->count(),
            'total_size' => $files->sum('size'),
            'files' => $files,
        ]);
    }
    // ============================================================
    // PARALLEL DOWNLOAD — Pool Guzzle
    // ============================================================

    // ============================================================
    // HELPER: resolveMediaUrl
    // ============================================================

    /**
     * Konversi path file → full URL.
     * Support Cloudinary (https://...) atau storage lokal.
     */
    private function resolveMediaUrl(string $path): string
    {
        // Kalau sudah full URL (http/https), pakai langsung
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        // Kalau path lokal, gunakan asset storage
        return asset('storage/'.ltrim($path, '/'));
    }

    /**
     * Download semua file secara paralel dengan Pool Guzzle.
     * Concurrency = 15 file sekaligus.
     */
    private function parallelDownload(array &$meta, string $filter): void
    {
        $client = new Client([
            'timeout' => 180,
            'connect_timeout' => 30,
            'verify' => false,
            'http_errors' => false,
        ]);

        $requests = [];
        foreach ($meta as $idx => $m) {
            $requests[$idx] = new \GuzzleHttp\Psr7\Request('GET', $m['url']);
        }

        $pool = new Pool($client, $requests, [
            'concurrency' => 4,           // ✅ turunkan

            'fulfilled' => function ($response, $idx) use (&$meta, $filter) {
                $m = &$meta[$idx];
                $m['attempts']++;

                if ($response->getStatusCode() !== 200) {
                    \Log::warning("Pool: HTTP {$response->getStatusCode()} untuk {$m['title']}");

                    return;
                }

                $body = $response->getBody();
                file_put_contents($m['tmp_file'], $body);

                $m['success'] = $this->verifyFile($m['tmp_file'], $m['expected_size'], $filter);
                if (! $m['success']) {
                    \Log::warning("Pool: verifikasi gagal untuk {$m['title']}");
                    @unlink($m['tmp_file']);
                    $m['tmp_file'] = tempnam(sys_get_temp_dir(), 'media_');
                }
            },

            'rejected' => function ($reason, $idx) use (&$meta) {
                $m = &$meta[$idx];
                $m['attempts']++;
                \Log::warning("Pool: rejected {$m['title']} - {$reason}");
            },
        ]);

        $pool->promise()->wait();
    }

    // ============================================================
    // RETRY SINGLE — Fallback kalau pool gagal
    // ============================================================

    /**
     * Retry download satu file secara sequential.
     */
    private function retrySingle(array &$m, string $filter): void
    {
        $delays = [2, 5, 10]; // detik jeda antar retry

        foreach ($delays as $i => $delay) {
            try {
                $res = Http::timeout(180)
                    ->connectTimeout(30)                          // ✅ tambah
                    ->withOptions(['verify' => false])
                    ->sink($m['tmp_file'])
                    ->get($m['url']);

                if ($res->successful() && $this->verifyFile($m['tmp_file'], $m['expected_size'], $filter)) {
                    $m['success'] = true;
                    \Log::info("Retry single BERHASIL {$m['title']} (attempt ".($i + 1).')');

                    return;
                }
            } catch (\Throwable $e) {
                \Log::warning("Retry single gagal {$m['title']} attempt ".($i + 1).': '.$e->getMessage());
            }

            if ($i < count($delays) - 1) {
                sleep($delay);
            }
        }

        $m['success'] = false;
    }
    // ============================================================
    // VERIFIKASI FILE
    // ============================================================

    /**
     * Verifikasi file setelah download: size + magic bytes.
     */
    private function verifyFile(string $tmpFile, int $expectedSize, string $filter): bool
    {
        if (! file_exists($tmpFile)) {
            return false;
        }

        $actualSize = filesize($tmpFile);
        if ($actualSize === 0) {
            return false;
        }

        // Verifikasi ukuran (toleransi 2%)
        if ($expectedSize > 0) {
            $tolerance = max(2048, $expectedSize * 0.02);
            if (abs($actualSize - $expectedSize) > $tolerance) {
                return false;
            }
        }

        // Verifikasi magic bytes untuk image
        if ($filter === 'image') {
            $handle = fopen($tmpFile, 'rb');
            $header = fread($handle, 12);
            fclose($handle);

            return
                str_starts_with($header, "\xFF\xD8\xFF") ||           // JPEG
                str_starts_with($header, "\x89PNG\r\n\x1a\n") ||      // PNG
                str_starts_with($header, 'GIF87a') ||                 // GIF87
                str_starts_with($header, 'GIF89a') ||                 // GIF89
                (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP'); // WEBP
        }

        return true;
    }

    // ============================================================
    // DOWNLOAD — ASYNC (Queue Job + Progress Polling)
    // ============================================================

    public function startDownload(Request $request, $id)
    {
        $filter = $request->get('filter', 'image');
        $token = Str::random(32);

        Cache::put("zip:{$token}", [
            'status' => 'queued',
            'progress' => 0,
            'current' => 0,
            'total' => 0,
        ], now()->addHours(2));

        BuildCategoryZip::dispatch($id, $filter, $token);

        return response()->json([
            'token' => $token,
            'poll' => route('categories.download.progress', $token),
        ]);
    }

    public function downloadProgress($token)
    {
        $data = Cache::get("zip:{$token}");
        if (! $data) {
            return response()->json(['status' => 'not_found'], 404);
        }
        if ($data['status'] === 'done') {
            $data['download_url'] = route('categories.download.file', $token);
        }

        return response()->json($data);
    }

    public function downloadFile($token)
    {
        $data = Cache::get("zip:{$token}");
        if (! $data || $data['status'] !== 'done') {
            abort(404);
        }
        if (! file_exists($data['path'])) {
            abort(404, 'File sudah dihapus.');
        }

        return response()
            ->download($data['path'], $data['filename'])
            ->deleteFileAfterSend(true);
    }
}
