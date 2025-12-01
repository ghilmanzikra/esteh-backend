<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokOutlet extends Model
{
    protected $table = 'stok_outlet';
    protected $fillable = ['outlet_id', 'bahan_id', 'stok'];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function bahan()
    {
        return $this->belongsTo(Bahan::class);
    }
}