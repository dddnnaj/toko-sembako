<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    use HasFactory;

    protected $fillable = [
        'kategori_id',
        'nama_produk',
        'harga',
        'stok',
        'deskripsi',
        'foto',
    ];

    protected $appends = ['foto_url', 'gambar_url', 'gambar', 'image_url', 'image'];

    protected function foto(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (! $value) {
                    return null;
                }

                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    return $value;
                }

                return asset('storage/' . $value);
            },
            set: function ($value) {
                if (! $value) {
                    return null;
                }

                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    $path = parse_url($value, PHP_URL_PATH);
                    return ltrim(str_replace('/storage/', '', $path), '/');
                }

                return $value;
            },
        );
    }

    protected function fotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->foto,
        );
    }

    protected function gambarUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->foto,
        );
    }

    protected function gambar(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->foto,
        );
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->foto,
        );
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->foto,
        );
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class);
    }
}
