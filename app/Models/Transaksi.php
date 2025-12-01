<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $table = 'transaksi';
    protected $fillable = [
        'outlet_id', 'karyawan_id', 'tanggal', 'total',
        'metode_bayar', 'bukti_qris'
    ];

    protected $dates = ['tanggal'];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function karyawan()
    {
        return $this->belongsTo(User::class, 'karyawan_id');
    }

    public function itemTransaksi()
    {
        return $this->hasMany(ItemTransaksi::class);
    }
}