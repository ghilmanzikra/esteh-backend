<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangKeluar extends Model
{
    protected $table = 'barang_keluar';
    protected $fillable = [
        'bahan_id', 'outlet_id', 'permintaan_id', 'jumlah',
        'tanggal_keluar', 'status', 'bukti_foto'
    ];

    protected $dates = ['tanggal_keluar'];

    public function bahan()
    {
        return $this->belongsTo(Bahan::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function permintaan()
    {
        return $this->belongsTo(PermintaanStok::class, 'permintaan_id');
    }
}