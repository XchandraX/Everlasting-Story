<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    //
    protected $fillable = [
        'title',
        'kategori_id',
        'deskription',   // sesuaikan dengan nama kolom di migration
        'description',   // kalau ada dua-duanya
        'file_path',
        'media_type',
        'file_size',     // ✅ TAMBAH INI
    ];

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }
}
