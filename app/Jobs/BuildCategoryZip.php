<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\Kategori;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ZipArchive;

class BuildCategoryZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 jam
    public $tries = 1;

    public function __construct(
        public int $categoryId,
        public string $filter,
        public string $token,
    ) {}

    public function handle(): void
    {
        $category = Kategori::findOrFail($this->categoryId);
        $media = Image::where('kategori_id', $this->categoryId)
            ->where('media_type', $this->filter)
            ->get();

        $total = $media->count();
        $this->updateProgress(0, $total, 'Menyiapkan...');

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

        $zipName = Str::slug($category->nama_kategori) . '-' . $this->filter . '-' . $this->token . '.zip';
        $zipPath = $tempDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $tempFiles = [];
        $i = 0;

        foreach ($media as $item) {
            $tmpFile = null;
            try {
                $url = preg_match('/^https?:\/\//i', $item->file_path)
                    ? $item->file_path
                    : asset('storage/' . ltrim($item->file_path, '/'));

                $tmpFile = tempnam(sys_get_temp_dir(), 'media_');
                $res = Http::timeout(300)->sink($tmpFile)->get($url);

                if ($res->successful()) {
                    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: ($this->filter === 'video' ? 'mp4' : 'jpg');
                    $safe = Str::slug($item->title ?: 'file') ?: 'file';
                    $zip->addFile($tmpFile, sprintf('%03d_%s.%s', $i + 1, $safe, $ext));
                    $tempFiles[] = $tmpFile;
                }
            } catch (\Throwable $e) {
                if ($tmpFile && file_exists($tmpFile)) @unlink($tmpFile);
            }

            $i++;
            $this->updateProgress($i, $total, $item->title);
        }

        $zip->close();
        foreach ($tempFiles as $t) @unlink($t);

        Cache::put("zip:{$this->token}", [
            'status'   => 'done',
            'path'     => $zipPath,
            'filename' => $zipName,
            'progress' => 100,
        ], now()->addHours(2));
    }

    private function updateProgress(int $current, int $total, string $currentFile): void
    {
        Cache::put("zip:{$this->token}", [
            'status'       => 'processing',
            'current'      => $current,
            'total'        => $total,
            'progress'     => $total > 0 ? round($current / $total * 100, 1) : 0,
            'current_file' => $currentFile,
        ], now()->addHours(2));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("zip:{$this->token}", [
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ], now()->addHours(2));
    }
}
