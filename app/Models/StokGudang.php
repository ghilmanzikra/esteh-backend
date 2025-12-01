<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokGudang extends Model
{
    protected $table = 'stok_gudang';
    protected $fillable = ['bahan_id', 'stok'];

    public function bahan()
    {
        return $this->belongsTo(Bahan::class);
    }
}