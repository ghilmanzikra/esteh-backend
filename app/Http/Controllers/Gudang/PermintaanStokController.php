<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\PermintaanStok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PermintaanStokController extends Controller
{
    /**
     * Menampilkan daftar permintaan stok
     */
    public function index()
    {
        return PermintaanStok::with('bahan', 'outlet')->get();
    }

    /**
     * Menampilkan detail permintaan stok tertentu
     */
    public function show($id)
    {
        return PermintaanStok::with('bahan', 'outlet')->findOrFail($id);
    }

    /**
     * Memperbarui status permintaan stok
     */
    public function update(Request $request, $id)
    {
        try {
            $permintaan = PermintaanStok::findOrFail($id);
            Log::info("Updating permintaan ID {$id} to status: {$request->status}");

            $permintaan->update(['status' => $request->status]);

            if ($request->status === 'approved') {
                $stokOutlet = \App\Models\StokOutlet::firstOrCreate(
                    ['outlet_id' => $permintaan->outlet_id, 'bahan_id' => $permintaan->bahan_id],
                    ['stok' => 0]
                );
                Log::info("Incrementing stok outlet: outlet_id={$permintaan->outlet_id}, bahan_id={$permintaan->bahan_id}, jumlah={$permintaan->jumlah}");
                $stokOutlet->increment('stok', $permintaan->jumlah);

                $stokGudang = \App\Models\StokGudang::where('bahan_id', $permintaan->bahan_id)->first();
                if ($stokGudang) {
                    Log::info("Decrementing stok gudang: bahan_id={$permintaan->bahan_id}, stok_sekarang={$stokGudang->stok}, jumlah={$permintaan->jumlah}");
                    if ($stokGudang->stok >= $permintaan->jumlah) {
                        $stokGudang->decrement('stok', $permintaan->jumlah);
                    } else {
                        Log::warning("Stok gudang tidak cukup: stok={$stokGudang->stok}, dibutuhkan={$permintaan->jumlah}");
                        return response()->json(['error' => 'Stok gudang tidak cukup'], 400);
                    }
                } else {
                    Log::warning("Tidak ada stok gudang untuk bahan_id={$permintaan->bahan_id}");
                    return response()->json(['error' => 'Tidak ada stok gudang untuk bahan ini'], 400);
                }
            }

            return response()->json($permintaan->load('bahan', 'outlet'));
        } catch (\Exception $e) {
            Log::error('Permintaan Stok Update Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}