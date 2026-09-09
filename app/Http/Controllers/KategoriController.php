<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use Illuminate\Http\Request;

class KategoriController extends Controller
{
    /**
     * Menampilkan daftar semua kategori (Publik).
     * Menyertakan jumlah produk dan mendukung pencarian ?search=...
     */
    public function index(Request $request)
    {
        $query = Kategori::withCount('produk');

        if ($request->filled('search')) {
            $query->where('nama_kategori', 'like', '%' . $request->search . '%');
        }

        $kategoris = $query->orderBy('nama_kategori', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $kategoris,
        ]);
    }

    /**
     * Menambahkan kategori baru (Perlu Login Sanctum).
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'nama_kategori' => 'required|string|max:255|unique:kategoris,nama_kategori',
        ]);

        $kategori = Kategori::create($fields);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan',
            'data'    => $kategori,
        ], 201);
    }

    /**
     * Menampilkan detail kategori dan produk yang termasuk di dalamnya (Publik).
     */
    public function show(Kategori $kategori)
    {
        return response()->json([
            'success' => true,
            'data'    => $kategori->load(['produk' => function ($query) {
                $query->latest();
            }]),
        ]);
    }

    /**
     * Memperbarui kategori (Perlu Login Sanctum).
     */
    public function update(Request $request, Kategori $kategori)
    {
        $fields = $request->validate([
            'nama_kategori' => 'required|string|max:255|unique:kategoris,nama_kategori,' . $kategori->id,
        ]);

        $kategori->update($fields);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui',
            'data'    => $kategori,
        ]);
    }

    /**
     * Menghapus kategori (Perlu Login Sanctum).
     */
    public function destroy(Kategori $kategori)
    {
        $kategori->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus',
        ]);
    }
}
