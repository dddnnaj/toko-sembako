<?php

namespace App\Http\Controllers;

use App\Models\Keranjang;
use App\Models\Produk;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeranjangController extends Controller
{
    /**
     * Tampilkan isi keranjang user yang sedang login,
     * lengkap dengan subtotal per item dan total keseluruhan.
     */
    public function index(Request $request)
    {
        $items = Keranjang::with('produk.kategori')
            ->where('user_id', $request->user()->id)
            ->get();

        $total = $items->sum(fn($item) => $item->produk
            ? $item->produk->harga * $item->jumlah
            : 0
        );

        $data = $items->map(function ($item) {
            return [
                'id'       => $item->id,
                'produk'   => $item->produk,
                'jumlah'   => $item->jumlah,
                'subtotal' => $item->produk ? $item->produk->harga * $item->jumlah : 0,
            ];
        });

        return response()->json([
            'success'        => true,
            'data'           => $data,
            'total_item'     => $items->count(),
            'total_harga'    => $total,
        ]);
    }

    /**
     * Tambah produk ke keranjang.
     * Jika produk sudah ada di keranjang, jumlah akan bertambah.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'produk_id' => 'required|exists:produks,id',
            'jumlah'    => 'required|integer|min:1',
        ]);

        $produk = Produk::findOrFail($fields['produk_id']);

        // Cek stok yang tersedia
        $existingJumlah = Keranjang::where('user_id', $request->user()->id)
            ->where('produk_id', $fields['produk_id'])
            ->value('jumlah') ?? 0;

        $totalJumlah = $existingJumlah + $fields['jumlah'];

        if ($produk->stok < $totalJumlah) {
            return response()->json([
                'success' => false,
                'message' => "Stok produk '{$produk->nama_produk}' tidak mencukupi (stok tersedia: {$produk->stok})",
            ], 422);
        }

        // updateOrCreate: jika sudah ada, increment jumlah; jika belum, buat baru
        $keranjang = Keranjang::where('user_id', $request->user()->id)
            ->where('produk_id', $fields['produk_id'])
            ->first();

        if ($keranjang) {
            $keranjang->increment('jumlah', $fields['jumlah']);
            $keranjang->refresh();
        } else {
            $keranjang = Keranjang::create([
                'user_id'   => $request->user()->id,
                'produk_id' => $fields['produk_id'],
                'jumlah'    => $fields['jumlah'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke keranjang',
            'data'    => [
                'id'       => $keranjang->id,
                'produk'   => $keranjang->load('produk')->produk,
                'jumlah'   => $keranjang->jumlah,
                'subtotal' => $keranjang->produk->harga * $keranjang->jumlah,
            ],
        ], 201);
    }

    /**
     * Update jumlah item tertentu di keranjang.
     * Jika jumlah di-set ke 0, item akan dihapus dari keranjang.
     */
    public function update(Request $request, Keranjang $keranjang)
    {
        // Pastikan item ini milik user yang sedang login
        if ($keranjang->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.',
            ], 403);
        }

        $fields = $request->validate([
            'jumlah' => 'required|integer|min:0',
        ]);

        // Jika jumlah 0, hapus item
        if ($fields['jumlah'] === 0) {
            $keranjang->delete();
            return response()->json([
                'success' => true,
                'message' => 'Item dihapus dari keranjang karena jumlah 0',
            ]);
        }

        $produk = $keranjang->produk;
        if ($produk->stok < $fields['jumlah']) {
            return response()->json([
                'success' => false,
                'message' => "Stok produk '{$produk->nama_produk}' tidak mencukupi (stok tersedia: {$produk->stok})",
            ], 422);
        }

        $keranjang->update(['jumlah' => $fields['jumlah']]);

        return response()->json([
            'success' => true,
            'message' => 'Jumlah item berhasil diperbarui',
            'data'    => [
                'id'       => $keranjang->id,
                'produk'   => $keranjang->produk,
                'jumlah'   => $keranjang->jumlah,
                'subtotal' => $produk->harga * $keranjang->jumlah,
            ],
        ]);
    }

    /**
     * Hapus satu item dari keranjang.
     */
    public function destroy(Request $request, Keranjang $keranjang)
    {
        if ($keranjang->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.',
            ], 403);
        }

        $keranjang->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil dihapus dari keranjang',
        ]);
    }

    /**
     * Kosongkan seluruh keranjang milik user yang sedang login.
     */
    public function clear(Request $request)
    {
        $deleted = Keranjang::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => "Keranjang berhasil dikosongkan ({$deleted} item dihapus)",
        ]);
    }

    /**
     * Checkout semua item di keranjang.
     * Stok dikurangi, keranjang dikosongkan, transaksi dibuat.
     */
    public function checkout(Request $request)
    {
        $user = $request->user();

        $items = Keranjang::with('produk')
            ->where('user_id', $user->id)
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Keranjang kosong, tidak ada yang di-checkout',
            ], 422);
        }

        try {
            $transaksi = DB::transaction(function () use ($user, $items) {
                $totalHarga    = 0;
                $itemsToCreate = [];

                // 1. Validasi stok & hitung total
                foreach ($items as $item) {
                    $produk = Produk::lockForUpdate()->find($item->produk_id);

                    if ($produk->stok < $item->jumlah) {
                        throw new \Exception(
                            "Stok produk '{$produk->nama_produk}' tidak mencukupi "
                            . "(sisa: {$produk->stok}, diminta: {$item->jumlah})"
                        );
                    }

                    $subtotal   = $produk->harga * $item->jumlah;
                    $totalHarga += $subtotal;

                    $itemsToCreate[] = [
                        'produk'   => $produk,
                        'jumlah'   => $item->jumlah,
                        'subtotal' => $subtotal,
                    ];
                }

                // 2. Buat Transaksi
                $transaksiBaru = Transaksi::create([
                    'user_id'     => $user->id,
                    'total_harga' => $totalHarga,
                    'status'      => 'pending',
                ]);

                // 3. Simpan detail & kurangi stok
                foreach ($itemsToCreate as $data) {
                    $transaksiBaru->detailTransaksi()->create([
                        'produk_id' => $data['produk']->id,
                        'jumlah'    => $data['jumlah'],
                        'subtotal'  => $data['subtotal'],
                    ]);

                    $data['produk']->decrement('stok', $data['jumlah']);
                }

                // 4. Kosongkan keranjang
                Keranjang::where('user_id', $user->id)->delete();

                return $transaksiBaru;
            });

            return response()->json([
                'success' => true,
                'message' => 'Checkout berhasil! Transaksi sedang diproses.',
                'data'    => $transaksi->load('detailTransaksi.produk'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
