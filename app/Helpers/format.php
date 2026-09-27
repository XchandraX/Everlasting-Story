<?php

if (!function_exists('format_bytes')) {
    /**
     * Format bytes ke string yang mudah dibaca.
     * Contoh: 1536 → "1.5 KB", 5242880 → "5 MB"
     */
    function format_bytes(?int $bytes, int $precision = 1): string
    {
        if (!$bytes || $bytes <= 0) return '-';

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), $precision) . ' ' . $units[$i];
    }
}
