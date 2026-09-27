<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ImageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicController;
use App\Models\Kategori;
use Illuminate\Support\Facades\Route;

// --- PUBLIC ROUTES ---
Route::get('/', [PublicController::class, 'index'])->name('home');
Route::get('/category', [PublicController::class, 'categories'])->name('categories.index');
Route::get('/category/{id}', [PublicController::class, 'showCategory'])->name('categories.show');
Route::get('/category/{id}/download', [PublicController::class, 'downloadCategory'])->name('categories.download');
Route::post('/category/{id}/download/start', [PublicController::class, 'startDownload'])
    ->name('categories.download.start');
Route::get('/download/progress/{token}', [PublicController::class, 'downloadProgress'])
    ->name('categories.download.progress');
Route::get('/download/file/{token}', [PublicController::class, 'downloadFile'])
    ->name('categories.download.file');
Route::get('/category/{id}/download/page', [PublicController::class, 'downloadPage'])
    ->name('categories.download.page');
Route::get('/search', [PublicController::class, 'search'])->name('search');

// --- AUTH ROUTES ---
Route::get('/login', [AuthController::class, 'login'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- ADMIN ROUTES (Terproteksi Middleware Auth) ---
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    Route::resource('images', ImageController::class);
    Route::resource('categories', CategoryController::class);
});

Route::get('/keep-alive', function () {
    try {
        // Lakukan query sederhana, misal ambil 1 data dari kategori
        Kategori::first();

        return response()->json(['status' => 'alive']);
    } catch (Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
});
