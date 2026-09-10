<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\KeranjangController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

// Public Routes (Auth)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Kategori & Produk Publik (opsional jika pembeli belum login)
Route::get('/kategori', [KategoriController::class, 'index']);
Route::get('/kategori/{kategori}', [KategoriController::class, 'show']);

// Protected Routes (Wajib Login Sanctum: Pembeli & Admin)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profil Pelanggan
    Route::get('/pelanggan/profil', [PelangganController::class, 'profil']);
    Route::put('/pelanggan/profil', [PelangganController::class, 'updateProfil']);
    // Jika ingin pakai POST untuk update, bisa ganti menjadi Route::post('/pelanggan/profil', [PelangganController::class, 'updateProfil']);

    // Pembeli & Admin: Melihat Katalog Produk
    Route::get('/products', [ProdukController::class, 'index']);
    Route::get('/products/{product}', [ProdukController::class, 'show']);

    // Pembeli & Admin: Transaksi / Membeli Barang
    Route::post('/transaksi', [TransaksiController::class, 'store']);
    Route::get('/transaksi', [TransaksiController::class, 'index']);
    Route::get('/transaksi/{transaksi}', [TransaksiController::class, 'show']);

    // ==========================================
    // KERANJANG BELANJA
    // ==========================================
    Route::get('/keranjang', [KeranjangController::class, 'index']);
    Route::post('/keranjang', [KeranjangController::class, 'store']);
    // checkout HARUS sebelum /{keranjang} agar tidak tertangkap sebagai ID
    Route::post('/keranjang/checkout', [KeranjangController::class, 'checkout']);
    Route::put('/keranjang/{keranjang}', [KeranjangController::class, 'update']);
    Route::delete('/keranjang', [KeranjangController::class, 'clear']);
    Route::delete('/keranjang/{keranjang}', [KeranjangController::class, 'destroy']);

    // ==========================================
    // RIWAYAT TRANSAKSI (Khusus Pembeli)
    // ==========================================
    Route::get('/riwayat', [TransaksiController::class, 'riwayat']);
    Route::post('/transaksi/{transaksi}/batal', [TransaksiController::class, 'cancel']);

    // ==========================================
    // KHUSUS ADMIN (Dilindungi Middleware 'admin')
    // ==========================================
    Route::middleware('admin')->group(function () {
        // Kelola Produk (Admin)
        Route::post('/products', [ProdukController::class, 'store']);
        Route::post('/products/{product}', [ProdukController::class, 'update']);
        Route::put('/products/{product}', [ProdukController::class, 'update']);
        Route::delete('/products/{product}', [ProdukController::class, 'destroy']);

        // Kelola Kategori (Admin)
        Route::post('/kategori', [KategoriController::class, 'store']);
        Route::put('/kategori/{kategori}', [KategoriController::class, 'update']);
        Route::delete('/kategori/{kategori}', [KategoriController::class, 'destroy']);

        // Kelola Status Pesanan (Admin)
        Route::patch('/transaksi/{transaksi}/status', [TransaksiController::class, 'updateStatus']);
        Route::post('/transaksi/{transaksi}/verifikasi', [TransaksiController::class, 'verify']);

        Route::get('/dashboard', [DashboardController::class, 'index']);

    });
});
