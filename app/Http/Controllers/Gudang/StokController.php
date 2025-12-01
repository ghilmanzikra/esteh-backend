<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\StokGudang;

class StokController extends Controller
{
    public function gudang()
    {
        return StokGudang::with('bahan')
            ->get()
            ->map(function ($item) {
                $item->status = $item->stok <= $item->bahan->stok_minimum_gudang ? 'Kritis' : 'Aman';
                return $item;
            });
    }
}