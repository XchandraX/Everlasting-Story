<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', [ImageController::class, 'index'])->name('index');
Route::get('/upload', [ImageController::class, 'create'])->name('create');
Route::get('/upload', [ImageController::class, 'store'])->name('store');