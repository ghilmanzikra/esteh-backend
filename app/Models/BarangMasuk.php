<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangMasuk extends Model
{
    protected $table = 'barang_masuk';
    protected $fillable = ['bahan_id', 'jumlah', 'tanggal', 'supplier'];

    protected $dates = ['tanggal'];

    public function bahan()
    {
        return $this->belongsTo(Bahan::class);
    }
}