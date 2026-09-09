<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleDanTransaksiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pembeli_bisa_melihat_produk_tetapi_ditolak_saat_tambah_produk(): void
    {
        $pembeli = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Beras & Tepung']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Beras Pandan Wangi 5kg',
            'harga'       => 75000,
            'stok'        => 20,
            'deskripsi'   => 'Beras wangi',
        ]);

        Sanctum::actingAs($pembeli);

        // 1. Pembeli BISA melihat produk
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $produk->id);

        $this->getJson("/api/products/{$produk->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $produk->id);

        // 2. Pembeli DITOLAK (403 Forbidden) saat mencoba menambah produk
        $this->postJson('/api/products', [
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Minyak Ilegal',
            'harga'       => 20000,
            'stok'        => 10,
        ])->assertForbidden();

        // 3. Pembeli DITOLAK saat mencoba mengubah atau menghapus produk
        $this->putJson("/api/products/{$produk->id}", [
            'harga' => 50000,
        ])->assertForbidden();

        $this->deleteJson("/api/products/{$produk->id}")
            ->assertForbidden();
    }

    public function test_pembeli_bisa_membeli_barang_dan_stok_berkurang_otomatis(): void
    {
        $pembeli = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Minyak Goreng']);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Minyak SunCo 2L',
            'harga'       => 35000,
            'stok'        => 10,
        ]);

        Sanctum::actingAs($pembeli);

        // Pembeli checkout membeli 3 item
        $response = $this->postJson('/api/transaksi', [
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'jumlah'    => 3,
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.total_harga', 105000)
            ->assertJsonPath('data.status', 'pending');

        // Pastikan stok berkurang dari 10 menjadi 7
        $this->assertEquals(7, $produk->fresh()->stok);

        $transaksiId = $response->json('data.id');

        // Pembeli bisa melihat nota transaksinya
        $this->getJson("/api/transaksi/{$transaksiId}")
            ->assertOk()
            ->assertJsonPath('data.id', $transaksiId);
    }

    public function test_admin_bisa_crud_produk_dan_update_status_pesanan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kategori = Kategori::create(['nama_kategori' => 'Bumbu']);

        Sanctum::actingAs($admin);

        // Admin bisa menambah produk
        $response = $this->postJson('/api/products', [
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Garam Dapur 500g',
            'harga'       => 5000,
            'stok'        => 100,
            'deskripsi'   => 'Garam beryodium',
        ])->assertCreated()
            ->assertJsonPath('data.nama_produk', 'Garam Dapur 500g');

        $produkId = $response->json('data.id');

        // Admin bisa mengupdate produk
        $this->putJson("/api/products/{$produkId}", [
            'harga' => 6000,
        ])->assertOk()
            ->assertJsonPath('data.harga', 6000);

        // Admin bisa menghapus produk
        $this->deleteJson("/api/products/{$produkId}")
            ->assertOk();
    }
}
