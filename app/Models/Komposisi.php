<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Komposisi extends Model
{
    protected $table = 'komposisi'; // TAMBAHKAN INI!
    protected $fillable = ['produk_id', 'bahan_id', 'quantity'];

    public function produk() { return $this->belongsTo(Produk::class); }
    public function bahan() { return $this->belongsTo(Bahan::class); }
}