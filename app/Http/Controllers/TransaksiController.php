<?php
namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    /**
     * Membeli Barang / Checkout (Bisa dilakukan oleh Pembeli yang sudah login).
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'items'             => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produks,id',
            'items.*.jumlah'    => 'sometimes|integer|min:1',
        ]);

        $fields['items'] = array_map(function (array $item) {
            $item['jumlah'] = $item['jumlah'] ?? 1;

            return $item;
        }, $fields['items']);

        try {
            $transaksi = DB::transaction(function () use ($request, $fields) {
                $totalHarga    = 0;
                $itemsToCreate = [];

                // 1. Cek ketersediaan stok & hitung subtotal
                foreach ($fields['items'] as $item) {
                    $produk = Produk::lockForUpdate()->find($item['produk_id']);

                    if ($produk->stok < $item['jumlah']) {
                        throw new \Exception("Stok produk '{$produk->nama_produk}' tidak mencukupi (sisa: {$produk->stok}, diminta: {$item['jumlah']})");
                    }

                    $subtotal   = $produk->harga * $item['jumlah'];
                    $totalHarga += $subtotal;

                    $itemsToCreate[] = [
                        'produk'   => $produk,
                        'jumlah'   => $item['jumlah'],
                        'subtotal' => $subtotal,
                    ];
                }

                // 2. Buat Transaksi Master
                $transaksiBaru = Transaksi::create([
                    'user_id'     => $request->user()->id,
                    'total_harga' => $totalHarga,
                    'status'      => 'pending',
                ]);

                // 3. Simpan Detail Transaksi dan Kurangi Stok Produk
                foreach ($itemsToCreate as $data) {
                    $transaksiBaru->detailTransaksi()->create([
                        'produk_id' => $data['produk']->id,
                        'jumlah'    => $data['jumlah'],
                        'subtotal'  => $data['subtotal'],
                    ]);

                    $data['produk']->decrement('stok', $data['jumlah']);
                }

                return $transaksiBaru;
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibuat',
                'data'    => $transaksi->load('detailTransaksi.produk'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Menampilkan riwayat transaksi.
     * Pembeli: hanya riwayat milik sendiri.
     * Admin: melihat seluruh transaksi dari semua pembeli.
     */
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Transaksi::with(['user', 'detailTransaksi.produk']);

        if ($user->isPembeli()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $transaksis = $query->latest()->paginate(10);

        return response()->json([
            'success'      => true,
            'data'         => $transaksis->items(),
            'current_page' => $transaksis->currentPage(),
            'last_page'    => $transaksis->lastPage(),
            'per_page'     => $transaksis->perPage(),
            'total'        => $transaksis->total(),
        ]);
    }

    /**
     * Menampilkan detail satu transaksi / nota pesanan.
     */
    public function show(Request $request, Transaksi $transaksi)
    {
        $user = $request->user();

        // Jika bukan admin dan bukan pemilik transaksi, tolak akses
        if ($user->isPembeli() && $transaksi->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki akses ke transaksi ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $transaksi->load(['user', 'detailTransaksi.produk']),
        ]);
    }

    /**
     * Mengubah status pesanan (Khusus Admin).
     */
    public function updateStatus(Request $request, Transaksi $transaksi)
    {
        $fields = $request->validate([
            'status' => 'required|string|in:pending,diproses,dikirim,selesai,dibatalkan',
        ]);

        DB::transaction(function () use ($transaksi, $fields) {
            // Jika status diubah menjadi 'dibatalkan', kembalikan stok produk
            if ($fields['status'] === 'dibatalkan' && $transaksi->status !== 'dibatalkan') {
                foreach ($transaksi->detailTransaksi as $detail) {
                    $detail->produk()->increment('stok', $detail->jumlah);
                }
            }

            $transaksi->update(['status' => $fields['status']]);
        });

        return response()->json([
            'success' => true,
            'message' => "Status transaksi berhasil diperbarui menjadi '{$fields['status']}'",
            'data' => $transaksi->load('detailTransaksi.produk'),
        ]);
    }

    /**
     * Riwayat transaksi milik user yang sedang login.
     * Mendukung filter: status, tanggal_dari, tanggal_sampai.
     */
    public function riwayat(Request $request)
    {
        $user  = $request->user();
        $query = Transaksi::with(['user', 'detailTransaksi.produk'])
            ->where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('created_at', '>=', $request->tanggal_dari);
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('created_at', '<=', $request->tanggal_sampai);
        }

        $transaksis = $query->latest()->paginate(10);

        $data = collect($transaksis->items())->map(function (Transaksi $transaksi) {
            return [
                'id'          => $transaksi->id,
                'status'      => $transaksi->status,
                'total_harga' => $transaksi->total_harga,
                'created_at'  => $transaksi->created_at,
                'bukti'       => [
                    'nomor'     => 'INV-' . str_pad((string) $transaksi->id, 6, '0', STR_PAD_LEFT),
                    'tanggal'   => $transaksi->created_at?->format('Y-m-d H:i:s'),
                    'pelanggan' => $transaksi->user?->name,
                    'status'    => $transaksi->status,
                    'total'     => $transaksi->total_harga,
                    'items'     => $transaksi->detailTransaksi->map(function ($detail) {
                        return [
                            'nama_produk'  => $detail->produk?->nama_produk,
                            'jumlah'       => $detail->jumlah,
                            'harga_satuan' => $detail->produk?->harga,
                            'subtotal'     => $detail->subtotal,
                        ];
                    })->values()->all(),
                ],
            ];
        })->all();

        return response()->json([
            'success'      => true,
            'data'         => $data,
            'current_page' => $transaksis->currentPage(),
            'last_page'    => $transaksis->lastPage(),
            'per_page'     => $transaksis->perPage(),
            'total'        => $transaksis->total(),
        ]);
    }

    public function verify(Request $request, Transaksi $transaksi)
    {
        if (! $request->user() || $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya admin yang bisa memverifikasi transaksi.',
            ], 403);
        }

        if ($transaksi->status === 'dibatalkan') {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi sudah dibatalkan dan tidak dapat diverifikasi.',
            ], 422);
        }

        $transaksi->update(['status' => 'diproses']);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil diverifikasi dan status diubah dari pending menjadi diproses.',
            'data'    => $transaksi->load(['user', 'detailTransaksi.produk']),
        ]);
    }

    /**
     * Pembeli membatalkan transaksi miliknya sendiri.
     * Hanya bisa dilakukan jika status masih 'pending'.
     */
    public function cancel(Request $request, Transaksi $transaksi)
    {
        $user = $request->user();

        // Hanya pemilik transaksi yang boleh membatalkan
        if ($transaksi->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki akses ke transaksi ini.',
            ], 403);
        }

        if ($transaksi->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Transaksi tidak dapat dibatalkan karena status sudah '{$transaksi->status}'",
            ], 422);
        }

        DB::transaction(function () use ($transaksi) {
            // Kembalikan stok produk
            foreach ($transaksi->detailTransaksi as $detail) {
                $detail->produk()->increment('stok', $detail->jumlah);
            }

            $transaksi->update(['status' => 'dibatalkan']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibatalkan dan stok produk telah dikembalikan',
            'data'    => $transaksi->load('detailTransaksi.produk'),
        ]);
    }
}
