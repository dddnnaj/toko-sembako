<?php
namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SembakoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Akun Uji Pelanggan
        User::updateOrCreate(
            ['email' => 'user@test.com'],
            [
                'name'     => 'Pelanggan Uji',
                'password' => Hash::make('password'),
            ],
        );

        // 2. Buat Kategori Sembako
        $beras  = Kategori::firstOrCreate(['nama_kategori' => 'Beras & Tepung']);
        $minyak = Kategori::firstOrCreate(['nama_kategori' => 'Minyak & Gula']);

        // 3. Buat Data Produk Dummy
        Produk::updateOrCreate(
            ['nama_produk' => 'Beras Premium 5kg'],
            [
                'kategori_id' => $beras->id,
                'harga'       => 68000,
                'stok'        => 50,
                'deskripsi'   => 'Beras putih pulen kualitas super',
            ],
        );

        Produk::updateOrCreate(
            ['nama_produk' => 'Minyak Goreng 2L'],
            [
                'kategori_id' => $minyak->id,
                'harga'       => 34000,
                'stok'        => 30,
                'deskripsi'   => 'Minyak goreng kelapa sawit jernih',
            ],
        );
    }
}
