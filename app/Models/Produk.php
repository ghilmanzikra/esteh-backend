<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    protected $table = 'produk'; // TAMBAHKAN INI!
    protected $fillable = ['nama', 'harga', 'gambar', 'is_available'];

    public function komposisi() { return $this->hasMany(Komposisi::class); }
    public function itemTransaksi() { return $this->hasMany(ItemTransaksi::class); }
}