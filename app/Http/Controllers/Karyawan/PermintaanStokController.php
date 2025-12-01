<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\PermintaanStok;
use Illuminate\Http\Request;

class PermintaanStokController extends Controller
{
    /**
     * Menampilkan daftar permintaan stok untuk outlet pengguna
     */
    public function index()
    {
        return PermintaanStok::where('outlet_id', auth()->user()->outlet_id)
            ->with('bahan')
            ->get();
    }

    /**
     * Membuat permintaan stok baru
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->outlet_id) {
                return response()->json(['error' => 'Pengguna tidak memiliki outlet yang valid'], 403);
            }

            $request->validate([
                'bahan_id' => 'required|exists:bahan,id',
                'jumlah' => 'required|numeric|min:0.001'
            ]);

            $permintaan = PermintaanStok::create([
                'outlet_id' => $user->outlet_id,
                'bahan_id' => $request->bahan_id,
                'jumlah' => $request->jumlah,
                'status' => 'diajukan' // Sesuai dengan enum di tabel permintaan_stok
            ]);

            return response()->json($permintaan, 201);
        } catch (\Exception $e) {
            \Log::error('Permintaan Stok Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menampilkan detail permintaan stok tertentu
     */
    public function show($id)
    {
        return PermintaanStok::where('outlet_id', auth()->user()->outlet_id)
            ->with('bahan')
            ->findOrFail($id);
    }
}