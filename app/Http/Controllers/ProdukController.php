<?php
namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\Request;

class ProdukController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => Produk::with('kategori')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'kategori_id' => 'required|exists:kategoris,id',
            'nama_produk' => 'required|string|max:255',
            'harga'       => 'required|integer|min:0',
            'stok'        => 'required|integer|min:0',
            'deskripsi'   => 'nullable|string',
        ]);

        $product = Produk::create($fields);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data'    => $product->load('kategori'),
        ], 201);
    }

    public function show(Produk $product)
    {
        return response()->json(['success' => true, 'data' => $product->load('kategori')]);
    }

    public function update(Request $request, Produk $product)
    {
        $fields = $request->validate([
            'kategori_id' => 'sometimes|required|exists:kategoris,id',
            'nama_produk' => 'sometimes|required|string|max:255',
            'harga'       => 'sometimes|required|integer|min:0',
            'stok'        => 'sometimes|required|integer|min:0',
            'deskripsi'   => 'sometimes|nullable|string',
        ]);

        $product->update($fields);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui',
            'data'    => $product->load('kategori'),
        ]);
    }

    public function destroy(Produk $product)
    {
        $product->delete();

        return response()->json(['success' => true, 'message' => 'Produk berhasil dihapus']);
    }
}
