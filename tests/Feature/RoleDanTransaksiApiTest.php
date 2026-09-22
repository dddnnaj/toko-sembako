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
        $pembeli  = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Beras & Tepung']);
        $produk   = Produk::create([
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

    public function test_pembeli_bisa_menambah_dan_melihat_keranjang(): void
    {
        $pembeli  = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Minyak Goreng']);
        $produk   = Produk::create([
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Minyak SunCo 2L',
            'harga'       => 35000,
            'stok'        => 10,
        ]);

        Sanctum::actingAs($pembeli);

        $this->postJson('/api/keranjang', [
            'produk_id' => $produk->id,
            'jumlah'    => 2,
        ])->assertCreated()
            ->assertJsonPath('data.jumlah', 2)
            ->assertJsonPath('data.subtotal', 70000);

        $this->getJson('/api/keranjang')
            ->assertOk()
            ->assertJsonPath('total_item', 1)
            ->assertJsonPath('total_harga', 70000)
            ->assertJsonPath('data.0.produk.id', $produk->id);

        $this->putJson('/api/keranjang/' . $this->getJson('/api/keranjang')->json('data.0.id'), [
            'jumlah' => 3,
        ])->assertOk()
            ->assertJsonPath('data.jumlah', 3)
            ->assertJsonPath('data.subtotal', 105000);
    }

    public function test_keranjang_untuk_user_belum_login_dan_kosong_sesuaikan_response(): void
    {
        $this->getJson('/api/keranjang')->assertUnauthorized();

        $pembeli = User::factory()->pembeli()->create();
        Sanctum::actingAs($pembeli);

        $this->getJson('/api/keranjang')
            ->assertOk()
            ->assertJsonPath('total_item', 0)
            ->assertJsonPath('total_harga', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_pembeli_tidak_bisa_akses_keranjang_user_lain(): void
    {
        $pemilik  = User::factory()->pembeli()->create();
        $penyusup = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Minyak Goreng']);
        $produk   = Produk::create([
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Minyak SunCo 2L',
            'harga'       => 35000,
            'stok'        => 10,
        ]);

        Sanctum::actingAs($pemilik);
        $this->postJson('/api/keranjang', [
            'produk_id' => $produk->id,
            'jumlah'    => 2,
        ])->assertCreated();

        Sanctum::actingAs($penyusup);
        $this->getJson('/api/keranjang')
            ->assertOk()
            ->assertJsonPath('total_item', 0);
    }

    public function test_checkout_bisa_menerima_item_tanpa_jumlah_dan_mengambil_default_1(): void
    {
        $pembeli  = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Minyak Goreng']);
        $produk   = Produk::create([
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Minyak SunCo 2L',
            'harga'       => 35000,
            'stok'        => 10,
        ]);

        Sanctum::actingAs($pembeli);

        $this->postJson('/api/transaksi', [
            'items' => [[
                'produk_id' => $produk->id,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.total_harga', 35000)
            ->assertJsonPath('data.detail_transaksi.0.jumlah', 1);
    }

    public function test_endpoint_checkout_midtrans_menggunakan_alur_checkout_yang_sama(): void
    {
        $pembeli = User::factory()->pembeli()->create();

        Sanctum::actingAs($pembeli);

        $this->postJson('/api/checkout-midtrans')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Keranjang kosong, tidak ada yang di-checkout');
    }

    public function test_pembeli_bisa_membeli_barang_dan_stok_berkurang_otomatis(): void
    {
        $pembeli  = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Minyak Goreng']);
        $produk   = Produk::create([
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

    public function test_admin_bisa_melihat_dashboard_dengan_statistik_transaksi_yang_benar(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        $pembeli  = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Bumbu']);
        $produk   = Produk::create([
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Garam Dapur 500g',
            'harga'       => 5000,
            'stok'        => 100,
            'deskripsi'   => 'Garam beryodium',
        ]);

        Sanctum::actingAs($pembeli);
        $this->postJson('/api/transaksi', [
            'items' => [[
                'produk_id' => $produk->id,
                'jumlah'    => 2,
            ]],
        ])->assertCreated();

        Sanctum::actingAs($admin);
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_produk', 1)
            ->assertJsonPath('data.total_transaksi', 1)
            ->assertJsonPath('data.total_pendapatan', 10000)
            ->assertJsonPath('data.total_pelanggan', 1)
            ->assertJsonPath('data.latest_orders.0.total_harga', 10000);
    }

    public function test_riwayat_transaksi_menyertakan_bukti_dan_admin_bisa_verifikasi_status(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        $pembeli  = User::factory()->pembeli()->create();
        $kategori = Kategori::create(['nama_kategori' => 'Bumbu']);
        $produk   = Produk::create([
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Garam Dapur 500g',
            'harga'       => 5000,
            'stok'        => 100,
            'deskripsi'   => 'Garam beryodium',
        ]);

        Sanctum::actingAs($pembeli);
        $tResponse = $this->postJson('/api/transaksi', [
            'items' => [[
                'produk_id' => $produk->id,
                'jumlah'    => 2,
            ]],
        ])->assertCreated();

        $transaksiId = $tResponse->json('data.id');

        Sanctum::actingAs($pembeli);
        $this->getJson('/api/riwayat')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.bukti.nomor', 'INV-' . str_pad((string) $transaksiId, 6, '0', STR_PAD_LEFT))
            ->assertJsonPath('data.0.bukti.total', 10000);

        Sanctum::actingAs($admin);
        $this->postJson("/api/transaksi/{$transaksiId}/verifikasi")
            ->assertOk()
            ->assertJsonPath('data.status', 'diproses');

        Sanctum::actingAs($pembeli);
        $this->getJson('/api/riwayat')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'diproses');
    }

    public function test_admin_bisa_crud_produk_dan_update_status_pesanan(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
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
