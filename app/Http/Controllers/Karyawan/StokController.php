<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\StokOutlet;

class StokController extends Controller
{
    public function outlet()
    {
        $outletId = auth()->user()->outlet_id;

        return StokOutlet::with('bahan')
            ->where('outlet_id', $outletId)
            ->get()
            ->map(function ($item) {
                $item->status = $item->stok <= $item->bahan->stok_minimum_outlet ? 'Kritis' : 'Aman';
                return $item;
            });
    }
}