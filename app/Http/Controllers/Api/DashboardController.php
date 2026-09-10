<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $queryTransaksi = Transaksi::query();
        if ($status && $status !== 'semua') {
            $queryTransaksi->where('status', $status);
        }

        $totalProduk     = Produk::count();
        $totalTransaksi  = (clone $queryTransaksi)->count();
        $totalPendapatan = (clone $queryTransaksi)->sum('total_harga');
        $totalPelanggan  = User::where('role', 'pembeli')->count();

        $latestOrders = (clone $queryTransaksi)
            ->with(['user', 'detailTransaksi.produk'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($transaksi) {
                return [
                    'id'          => $transaksi->id,
                    'user'        => $transaksi->user ? $transaksi->user->name : null,
                    'status'      => $transaksi->status,
                    'total_harga' => $transaksi->total_harga,
                    'created_at'  => $transaksi->created_at,
                    'items'       => $transaksi->detailTransaksi->map(function ($detail) {
                        return [
                            'produk'   => $detail->produk ? $detail->produk->nama_produk : null,
                            'jumlah'   => $detail->jumlah,
                            'subtotal' => $detail->subtotal,
                        ];
                    })->values(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'total_produk'     => $totalProduk,
                'total_transaksi'  => $totalTransaksi,
                'total_pendapatan' => $totalPendapatan,
                'total_pelanggan'  => $totalPelanggan,
                'latest_orders'    => $latestOrders,
            ],
        ]);
    }
}
