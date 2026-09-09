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
        // 1. Buat Akun Admin & Akun Pembeli
        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name'     => 'Administrator Toko',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ],
        );

        User::updateOrCreate(
            ['email' => 'user@test.com'],
            [
                'name'     => 'Pembeli Sembako',
                'password' => Hash::make('password'),
                'role'     => 'pembeli',
            ],
        );

        // 2. Buat Kategori Sembako
        $beras  = Kategori::firstOrCreate(['nama_kategori' => 'Beras & Tepung']);
        $minyak = Kategori::firstOrCreate(['nama_kategori' => 'Minyak & Margarin']);
        $gula   = Kategori::firstOrCreate(['nama_kategori' => 'Gula & Garam']);
        $bumbu  = Kategori::firstOrCreate(['nama_kategori' => 'Bumbu Dapur']);

        // 3. Buat Data Produk Dummy (Foto dikosongkan agar diupload dari frontend)
        Produk::updateOrCreate(
            ['nama_produk' => 'Beras Premium 5kg'],
            [
                'kategori_id' => $beras->id,
                'harga'       => 68000,
                'stok'        => 50,
                'deskripsi'   => 'Beras putih pulen kualitas super',
                'foto'        => null,
            ],
        );

        Produk::updateOrCreate(
            ['nama_produk' => 'Minyak Goreng 2L'],
            [
                'kategori_id' => $minyak->id,
                'harga'       => 34000,
                'stok'        => 30,
                'deskripsi'   => 'Minyak goreng kelapa sawit jernih',
                'foto'        => null,
            ],
        );

        Produk::updateOrCreate(
            ['nama_produk' => 'Gula Pasir Kristal 1kg'],
            [
                'kategori_id' => $gula->id,
                'harga'       => 17500,
                'stok'        => 40,
                'deskripsi'   => 'Gula pasir tebu alami manis bersih',
                'foto'        => null,
            ],
        );

        Produk::updateOrCreate(
            ['nama_produk' => 'Garam Beryodium 500g'],
            [
                'kategori_id' => $gula->id,
                'harga'       => 5000,
                'stok'        => 100,
                'deskripsi'   => 'Garam halus beryodium untuk bumbu dapur',
                'foto'        => null,
            ],
        );
    }
}
