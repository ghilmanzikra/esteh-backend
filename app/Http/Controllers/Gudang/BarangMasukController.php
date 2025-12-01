<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\BarangMasuk;
use App\Models\StokGudang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BarangMasukController extends Controller
{
    /**
     * Menampilkan daftar barang masuk
     */
    public function index()
    {
        return BarangMasuk::with('bahan')->latest()->get();
    }

    /**
     * Menampilkan detail barang masuk tertentu
     */
    public function show($id)
    {
        try {
            $barangMasuk = BarangMasuk::with('bahan')->findOrFail($id);
            return response()->json([
                'message' => 'Detail barang masuk ditemukan',
                'data' => $barangMasuk
            ], 200);
        } catch (\Exception $e) {
            Log::error('Barang Masuk Show Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Membuat record barang masuk baru
     */
    public function store(Request $request)
    {
        try {
            if ($request->user()->role !== 'gudang') {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            $request->validate([
                'bahan_id' => 'required|exists:bahan,id',
                'jumlah' => 'required|numeric|min:0.001',
                'supplier' => 'required|string|max:255'
            ]);

            $jumlah = floatval(str_replace('.', '', $request->jumlah)) / 1000;
            Log::info("Converted jumlah: {$request->jumlah} to {$jumlah}");

            $masuk = BarangMasuk::create([
                'bahan_id' => $request->bahan_id,
                'jumlah' => $request->jumlah,
                'tanggal' => now(),
                'supplier' => $request->supplier
            ]);

            StokGudang::updateOrCreate(
                ['bahan_id' => $request->bahan_id],
                ['stok' => DB::raw("COALESCE(stok, 0) + " . $jumlah)]
            );

            return response()->json([
                'message' => 'Barang masuk berhasil dicatat!',
                'data' => $masuk->load('bahan')
            ], 201);
        } catch (\Exception $e) {
            Log::error('Barang Masuk Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Memperbarui record barang masuk
     */
    public function update(Request $request, $id)
    {
        try {
            $barangMasuk = BarangMasuk::findOrFail($id);

            if ($request->user()->role !== 'gudang') {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            $request->validate([
                'bahan_id' => 'required|exists:bahan,id',
                'jumlah' => 'required|numeric|min:0.001',
                'supplier' => 'required|string|max:255'
            ]);

            $jumlahSebelum = floatval(str_replace('.', '', $barangMasuk->jumlah)) / 1000;
            $jumlahBaru = floatval(str_replace('.', '', $request->jumlah)) / 1000;
            $selisih = $jumlahBaru - $jumlahSebelum;

            $barangMasuk->update([
                'bahan_id' => $request->bahan_id,
                'jumlah' => $request->jumlah,
                'supplier' => $request->supplier
            ]);

            $stokGudang = StokGudang::where('bahan_id', $request->bahan_id)->first();
            if ($stokGudang) {
                $stokGudang->increment('stok', $selisih);
            } else {
                StokGudang::create(['bahan_id' => $request->bahan_id, 'stok' => $selisih]);
            }

            return response()->json([
                'message' => 'Barang masuk berhasil diperbarui!',
                'data' => $barangMasuk->load('bahan')
            ], 200);
        } catch (\Exception $e) {
            Log::error('Barang Masuk Update Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menghapus record barang masuk
     */
    public function destroy(Request $request, $id)
    {
        try {
            $barangMasuk = BarangMasuk::findOrFail($id);

            if ($request->user()->role !== 'gudang') {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            $jumlah = floatval(str_replace('.', '', $barangMasuk->jumlah)) / 1000;
            $barangMasuk->delete();

            $stokGudang = StokGudang::where('bahan_id', $barangMasuk->bahan_id)->first();
            if ($stokGudang) {
                $stokGudang->decrement('stok', $jumlah);
            }

            return response()->json(['message' => 'Barang masuk berhasil dihapus!'], 200);
        } catch (\Exception $e) {
            Log::error('Barang Masuk Destroy Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}