<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ZipArchive;


class PublicController extends Controller
{
    // Menampilkan Halaman Utama (Misal: Menampilkan Kategori)
    public function index()
    {
        // Menampilkan 6 kategori terbaru beserta jumlah fotonya
        $categories = Kategori::withCount('images')->latest()->take(6)->get();
        // Menampilkan beberapa foto terbaru untuk highlight
        $latestImages = Image::latest()->take(8)->get();

        return view('public.home', compact('categories', 'latestImages'));
    }

    // Menampilkan semua kategori
    public function categories()
    {
        $categories = Kategori::withCount('images')->latest()->paginate(12);

        return view('public.categories', compact('categories'));
    }

    // Menampilkan foto-foto di dalam satu kategori
    public function showCategory(Request $request, $id)
    {
        $category = Kategori::findOrFail($id);
        $filter = $request->get('filter', 'image'); // default image

        $images = Image::where('kategori_id', $id);
        if ($filter === 'image') {
            $images->where('media_type', 'image');
        } elseif ($filter === 'video') {
            $images->where('media_type', 'video');
        }
        $images = $images->latest()->paginate(12)->withQueryString();

        return view('public.category_show', compact('category', 'images', 'filter'));
    }

    // Fitur Pencarian Foto
    public function search(Request $request)
    {
        $keyword = $request->input('q');

        $images = Image::where('title', 'like', "%{$keyword}%")
            ->orWhere('description', 'like', "%{$keyword}%")
            ->latest()
            ->paginate(15);

        return view('public.search_results', compact('images', 'keyword'));
    }

        /**
     * Download semua media di kategori tertentu sebagai ZIP
     */
    public function downloadCategory(Request $request, $id)
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $category = Kategori::findOrFail($id);
        $filter   = $request->get('filter', 'image'); // image | video

        $media = Image::where('kategori_id', $id)
            ->where('media_type', $filter)
            ->latest()
            ->get();

        if ($media->isEmpty()) {
            return back()->with('error', 'Tidak ada file untuk diunduh.');
        }

        // Folder temp
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipName = Str::slug($category->nama_kategori)
                 . '-' . $filter
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
                // Resolve URL: dukung Cloudinary full URL ATAU path storage lokal
                $url = $this->resolveMediaUrl($item->file_path);

                $tmpFile = tempnam(sys_get_temp_dir(), 'media_');

                $response = Http::timeout(300)->get($url);
                if (!$response->successful()) {
                    @unlink($tmpFile);
                    continue;
                }
                file_put_contents($tmpFile, $response->body());

                // Ekstensi file
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
     * Helper: bisa handle Cloudinary URL atau path storage lokal
     */
    private function resolveMediaUrl(string $path): string
    {
        // Kalau sudah full URL (http/https), pakai langsung
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        // Kalau path lokal, gunakan asset storage
        return asset('storage/' . ltrim($path, '/'));
    }
}
