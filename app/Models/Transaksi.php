<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model {
    protected $fillable = ['user_id', 'total_harga', 'status'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function detailTransaksi() {
        return $this->hasMany(DetailTransaksi::class);
    }
}