<?php

namespace App\Console\Commands;

use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncImageSizes extends Command
{
    protected $signature = 'images:sync-sizes
                            {--force : Timpa size yang sudah ada}
                            {--limit=0 : Batasi jumlah file yang diproses (0 = semua)}';

    protected $description = 'Sync file_size dari Cloudinary untuk image yang belum punya size';

    public function handle()
    {
        $query = Image::query();

        if (!$this->option('force')) {
            $query->whereNull('file_size');
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $images = $query->get();

        if ($images->isEmpty()) {
            $this->info('✅ Semua image sudah punya file_size. Tidak ada yang perlu di-sync.');
            return self::SUCCESS;
        }

        $this->info("📦 Ditemukan {$images->count()} image untuk di-sync.");
        $this->newLine();

        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        $success = 0;
        $failed  = 0;

        foreach ($images as $img) {
            try {
                // HEAD request → ambil Content-Length tanpa download body
                $response = Http::timeout(30)
                    ->withOptions(['stream' => false])
                    ->head($img->file_path);

                $size = $response->header('Content-Length');

                if ($size && (int) $size > 0) {
                    $img->update(['file_size' => (int) $size]);
                    $success++;
                } else {
                    // Fallback: GET dengan stream untuk cek ukuran
                    $size = $this->getSizeViaGet($img->file_path);
                    if ($size > 0) {
                        $img->update(['file_size' => $size]);
                        $success++;
                    } else {
                        $failed++;
                        \Log::warning("SyncImageSizes: tidak bisa ambil size untuk image ID {$img->id} ({$img->title})");
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                \Log::error("SyncImageSizes error ID {$img->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Status', 'Jumlah'],
            [
                ['✅ Success', $success],
                ['❌ Failed',  $failed],
                ['📊 Total',   $images->count()],
            ]
        );

        if ($failed > 0) {
            $this->warn("⚠️  {$failed} file gagal. Cek storage/logs/laravel.log untuk detail.");
        }

        return self::SUCCESS;
    }

    /**
     * Fallback: kalau HEAD tidak kasih Content-Length, pakai GET stream
     */
    private function getSizeViaGet(string $url): int
    {
        try {
            $tmp = tempnam(sys_get_temp_dir(), 'size_');
            $response = Http::timeout(60)->sink($tmp)->get($url);
            $size = file_exists($tmp) ? filesize($tmp) : 0;
            @unlink($tmp);

            return $response->successful() ? $size : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
