<?php
namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProdukApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_produk_api_requires_authentication(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_produk_api_validates_input_and_supports_crud(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $kategori = Kategori::create(['nama_kategori' => 'Beras & Tepung']);

        $this->postJson('/api/products', [
            'kategori_id' => $kategori->id,
            'nama_produk' => '',
            'harga'       => -1,
            'stok'        => 'bukan angka',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['nama_produk', 'harga', 'stok']);

        $response = $this->postJson('/api/products', [
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Beras Premium 5kg',
            'harga'       => 68000,
            'stok'        => 50,
            'deskripsi'   => 'Beras putih pulen',
        ])->assertCreated()
            ->assertJsonPath('data.nama_produk', 'Beras Premium 5kg')
            ->assertJsonPath('data.kategori.id', $kategori->id);

        $produk = Produk::firstOrFail();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $produk->id);

        $this->getJson("/api/products/{$produk->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $produk->id);

        $this->putJson("/api/products/{$produk->id}", [
            'stok' => 40,
        ])->assertOk()
            ->assertJsonPath('data.stok', 40);

        $this->deleteJson("/api/products/{$produk->id}")
            ->assertOk();

        $this->assertDatabaseMissing('produks', ['id' => $produk->id]);
    }
}
