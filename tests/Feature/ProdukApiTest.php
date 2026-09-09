<?php
namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_produk_api_supports_search_and_pagination(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $kategori = Kategori::create(['nama_kategori' => 'Minyak & Gula']);

        for ($i = 1; $i <= 15; $i++) {
            Produk::create([
                'kategori_id' => $kategori->id,
                'nama_produk' => "Produk Sembako $i",
                'harga'       => 10000 * $i,
                'stok'        => 10,
                'deskripsi'   => "Deskripsi produk $i",
            ]);
        }

        // Uji Pagination (per_page = 5 dari total 15 produk)
        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('total', 15)
            ->assertJsonCount(5, 'data');

        // Uji Pencarian (Search)
        $this->getJson('/api/products?search=Sembako 10')
            ->assertOk()
            ->assertJsonPath('data.0.nama_produk', 'Produk Sembako 10');
    }

    public function test_admin_bisa_upload_gambar_produk_jpg(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $kategori = Kategori::create(['nama_kategori' => 'Beras Organik']);

        $file = UploadedFile::fake()->image('beras.jpg', 600, 600);

        $response = $this->post('/api/products', [
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Beras Organik 5kg',
            'harga'       => 85000,
            'stok'        => 20,
            'deskripsi'   => 'Beras organik tanpa pestisida',
            'foto'        => $file,
        ])->assertCreated();

        $fotoUrl = $response->json('data.foto');
        $this->assertNotNull($fotoUrl);
        $this->assertStringStartsWith(url('/storage/'), $fotoUrl);

        $storedPath = Produk::query()->latest('id')->first()->getRawOriginal('foto');
        $this->assertNotNull($storedPath);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_produk_mengembalikan_url_gambar_yang_bisa_digunakan_frontend(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());
        $kategori = Kategori::create(['nama_kategori' => 'Beras Organik']);

        $product = Produk::create([
            'kategori_id' => $kategori->id,
            'nama_produk' => 'Beras Premium 5kg',
            'harga'       => 70000,
            'stok'        => 15,
            'deskripsi'   => 'Beras premium',
            'foto'        => 'produks/beras.jpg',
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.foto', asset('storage/' . $product->getRawOriginal('foto')))
            ->assertJsonPath('data.0.foto_url', asset('storage/' . $product->getRawOriginal('foto')))
            ->assertJsonPath('data.0.gambar_url', asset('storage/' . $product->getRawOriginal('foto')))
            ->assertJsonPath('data.0.gambar', asset('storage/' . $product->getRawOriginal('foto')));
    }
}
