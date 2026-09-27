<?php

namespace App\Http\Controllers;

use App\Jobs\BuildCategoryZip;
use App\Models\Image;
use App\Models\Kategori;
use Illuminate\Http\Request;
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
        $filter   = $request->get('filter', 'image');

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
        $filter   = $request->get('filter', 'image');
        $page     = (int) $request->get('page', 1);

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
    // CORE: buildZip() — dipakai oleh downloadCategory & downloadPage
    // ============================================================

    /**
     * Bangun ZIP dari collection Image lalu kirim ke user.
     *
     * @param  \App\Models\Kategori  $category
     * @param  \Illuminate\Support\Collection  $media
     * @param  string  $filter   'image' | 'video'
     * @param  string|null  $suffix  tambahan nama file (misal "page-2")
     */
    private function buildZip($category, $media, string $filter, ?string $suffix = null)
    {
        if ($media->isEmpty()) {
            return back()->with('error', 'Tidak ada file untuk diunduh.');
        }

        set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipName = Str::slug($category->nama_kategori)
                 . '-' . $filter
                 . ($suffix ? '-' . $suffix : '')
                 . '-' . now()->format('Ymd_His')
                 . '.zip';
        $zipPath = $tempDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat file ZIP.');
        }

        $tempFiles = [];
        $counter   = 1;

        foreach ($media as $item) {
            $tmpFile = null;
            try {
                $url = $this->resolveMediaUrl($item->file_path);
                $tmpFile = tempnam(sys_get_temp_dir(), 'media_');

                // ✅ sink() = streaming ke file, hemat memory untuk video besar
                $response = Http::timeout(300)->sink($tmpFile)->get($url);

                if (!$response->successful()) {
                    @unlink($tmpFile);
                    continue;
                }

                $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (!$ext) {
                    $ext = $filter === 'video' ? 'mp4' : 'jpg';
                }

                $safeTitle = Str::slug($item->title ?: 'file') ?: 'file';
                $fileName  = sprintf('%03d_%s.%s', $counter, $safeTitle, $ext);

                $zip->addFile($tmpFile, $fileName);
                $tempFiles[] = $tmpFile;
                $counter++;
            } catch (\Throwable $e) {
                if ($tmpFile && file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
                continue;
            }
        }

        $zip->close();

        foreach ($tempFiles as $tmp) {
            @unlink($tmp);
        }

        return response()
            ->download($zipPath, $zipName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Helper: Cloudinary URL atau path storage lokal → full URL
     */
    private function resolveMediaUrl(string $path): string
    {
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        return asset('storage/' . ltrim($path, '/'));
    }

    // ============================================================
    // DOWNLOAD — ASYNC (Queue Job + Progress Polling)
    // ============================================================

    public function startDownload(Request $request, $id)
    {
        $filter = $request->get('filter', 'image');
        $token  = Str::random(32);

        Cache::put("zip:{$token}", [
            'status'   => 'queued',
            'progress' => 0,
            'current'  => 0,
            'total'    => 0,
        ], now()->addHours(2));

        BuildCategoryZip::dispatch($id, $filter, $token);

        return response()->json([
            'token' => $token,
            'poll'  => route('categories.download.progress', $token),
        ]);
    }

    public function downloadProgress($token)
    {
        $data = Cache::get("zip:{$token}");
        if (!$data) {
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
        if (!$data || $data['status'] !== 'done') {
            abort(404);
        }
        if (!file_exists($data['path'])) {
            abort(404, 'File sudah dihapus.');
        }
        return response()
            ->download($data['path'], $data['filename'])
            ->deleteFileAfterSend(true);
    }
}
