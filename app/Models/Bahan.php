<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bahan extends Model
{
    protected $table = 'bahan'; // TAMBAHKAN INI!
    protected $fillable = ['nama', 'satuan', 'stok_minimum_gudang', 'stok_minimum_outlet'];

    public function komposisi() { return $this->hasMany(Komposisi::class); }
    public function stokGudang() { return $this->hasOne(StokGudang::class); }
    public function stokOutlet() { return $this->hasMany(StokOutlet::class); }
    public function barangMasuk() { return $this->hasMany(BarangMasuk::class); }
    public function barangKeluar() { return $this->hasMany(BarangKeluar::class); }
    public function permintaanStok() { return $this->hasMany(PermintaanStok::class); }
}