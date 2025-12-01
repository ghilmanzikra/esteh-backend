<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemTransaksi extends Model
{
    protected $table = 'item_transaksi';
    protected $fillable = ['transaksi_id', 'produk_id', 'quantity', 'subtotal'];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }
}