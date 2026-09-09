<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role', 'no_hp', 'alamat'];
    protected $hidden = ['password', 'remember_token'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPembeli(): bool
    {
        return $this->role === 'pembeli';
    }

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class);
    }
}