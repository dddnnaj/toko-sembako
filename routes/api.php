<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KategoriController;
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
    Route::get('/pelanggan/profil', [PelangganController::class, 'profil']);

    // Pembeli & Admin: Melihat Katalog Produk
    Route::get('/products', [ProdukController::class, 'index']);
    Route::get('/products/{product}', [ProdukController::class, 'show']);

    // Pembeli & Admin: Transaksi / Membeli Barang
    Route::post('/transaksi', [TransaksiController::class, 'store']);
    Route::get('/transaksi', [TransaksiController::class, 'index']);
    Route::get('/transaksi/{transaksi}', [TransaksiController::class, 'show']);

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
    });
});

