<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Keranjang extends Model
{
    protected $fillable = ['user_id', 'produk_id', 'jumlah'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    /**
     * Hitung subtotal item keranjang ini.
     */
    public function getSubtotalAttribute(): int
    {
        return $this->produk ? $this->produk->harga * $this->jumlah : 0;
    }
}
