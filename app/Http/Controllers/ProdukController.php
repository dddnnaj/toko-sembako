<?php
namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        // 1. Relasi dengan Modul Pertama (Eager Loading Kategori)
        $query = Produk::with('kategori');

        // 2. Pencarian (Search) berdasarkan nama produk atau deskripsi
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_produk', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan kategori jika ada
        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->kategori_id);
        }

        // Filter rentang harga
        if ($request->filled('min_harga')) {
            $query->where('harga', '>=', $request->min_harga);
        }
        if ($request->filled('max_harga')) {
            $query->where('harga', '<=', $request->max_harga);
        }

        // Filter ketersediaan stok
        if ($request->boolean('in_stock')) {
            $query->where('stok', '>', 0);
        }

        // Pengurutan (Sorting)
        switch ($request->query('sort')) {
            case 'harga_asc':
                $query->orderBy('harga', 'asc');
                break;
            case 'harga_desc':
                $query->orderBy('harga', 'desc');
                break;
            case 'nama_asc':
                $query->orderBy('nama_produk', 'asc');
                break;
            case 'nama_desc':
                $query->orderBy('nama_produk', 'desc');
                break;
            case 'terlama':
                $query->oldest();
                break;
            default:
                $query->latest();
                break;
        }

        // 3. Opsi Ambil Semua (tanpa paginasi jika diminta)
        if ($request->boolean('all') || $request->query('per_page') === 'all') {
            $items = $query->get();
            return response()->json([
                'success' => true,
                'data'    => $items,
                'total'   => $items->count(),
            ]);
        }

        // 4. Pagination (Standar Tugas Lab/Bootcamp)
        $perPage   = (int) $request->input('per_page', 10);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success'      => true,
            'data'         => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
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
            'foto'        => 'nullable',
            'gambar'      => 'nullable',
            'image'       => 'nullable',
        ]);

        // Mendukung file upload dengan nama 'foto', 'gambar', atau 'image'
        $file = $request->file('foto') ?? $request->file('gambar') ?? $request->file('image');
        if ($file) {
            $fields['foto'] = $file->store('produks', 'public');
        } elseif ($request->filled('foto')) {
            $fields['foto'] = $request->input('foto');
        } elseif ($request->filled('gambar')) {
            $fields['foto'] = $request->input('gambar');
        } elseif ($request->filled('image')) {
            $fields['foto'] = $request->input('image');
        }

        unset($fields['gambar'], $fields['image']);

        $product = Produk::create($fields);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data'    => $product->load('kategori'),
        ], 201);
    }

    public function show(Produk $product)
    {
        return response()->json([
            'success' => true,
            'data'    => $product->load('kategori'),
        ]);
    }

    public function update(Request $request, Produk $product)
    {
        $fields = $request->validate([
            'kategori_id' => 'sometimes|required|exists:kategoris,id',
            'nama_produk' => 'sometimes|required|string|max:255',
            'harga'       => 'sometimes|required|integer|min:0',
            'stok'        => 'sometimes|required|integer|min:0',
            'deskripsi'   => 'sometimes|nullable|string',
            'foto'        => 'sometimes|nullable',
            'gambar'      => 'sometimes|nullable',
            'image'       => 'sometimes|nullable',
        ]);

        $file = $request->file('foto') ?? $request->file('gambar') ?? $request->file('image');
        if ($file) {
            $rawFoto = $product->getRawOriginal('foto');
            // Hapus file foto lama jika ada di storage lokal
            if ($rawFoto && ! str_starts_with($rawFoto, 'http') && Storage::disk('public')->exists($rawFoto)) {
                Storage::disk('public')->delete($rawFoto);
            }
            $fields['foto'] = $file->store('produks', 'public');
        } elseif ($request->filled('foto')) {
            $fields['foto'] = $request->input('foto');
        } elseif ($request->filled('gambar')) {
            $fields['foto'] = $request->input('gambar');
        } elseif ($request->filled('image')) {
            $fields['foto'] = $request->input('image');
        }

        unset($fields['gambar'], $fields['image']);

        $product->update($fields);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui',
            'data'    => $product->load('kategori'),
        ]);
    }

    public function destroy(Produk $product)
    {
        $rawFoto = $product->getRawOriginal('foto');

        // Hapus file foto jika ada
        if ($rawFoto && Storage::disk('public')->exists($rawFoto)) {
            Storage::disk('public')->delete($rawFoto);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus',
        ]);
    }
}
