<?php

namespace App\Http\Controllers;

use App\Models\Image;

class ImageController extends Controller
{
    public function index()
    {
        //
        return view('image.index', [
            'title' => 'Image',
            'images' => Image::latest()->get(),
        ]);
    }

    public function create() {
        //
        return view('image.create', [
            'title' => 'Tambah Foto',
        ]);
    }

    // public function edit() {

    // }

    public function store() {
        $path = $request->file('image')->store('images', 'public');

        Image::create([
            'title' => $request->title,
            'file_path' => $path
        ]);

        return redirect('/');
    }

    // public function destroy() {
        
    // }
    
}
