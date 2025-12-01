<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\BarangKeluar;
use App\Models\StokGudang;
use App\Models\StokOutlet;
use App\Models\PermintaanStok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class BarangKeluarController extends Controller
{
    /**
     * Menampilkan daftar barang keluar
     */
    public function index()
    {
        try {
            $barangKeluar = BarangKeluar::with(['bahan', 'outlet'])->latest()->get();
            return response()->json([
                'message' => 'Daftar barang keluar ditemukan',
                'data' => $barangKeluar
            ], 200);
        } catch (\Exception $e) {
            Log::error('Barang Keluar Index Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menampilkan detail barang keluar tertentu
     */
    public function show($id)
    {
        try {
            $barangKeluar = BarangKeluar::with(['bahan', 'outlet'])->findOrFail($id);
            return response()->json([
                'message' => 'Detail barang keluar ditemukan',
                'data' => $barangKeluar
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Barang Keluar Not Found: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Barang keluar tidak ditemukan'], 404);
        } catch (\Exception $e) {
            Log::error('Barang Keluar Show Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Membuat record barang keluar baru
     */
    public function store(Request $request)
    {
        try {
            // Pastikan pengguna memiliki peran 'gudang'
            if ($request->user()->role !== 'gudang') {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            // Validasi input
            $request->validate([
                'permintaan_id' => 'required|exists:permintaan_stok,id'
            ]);

            // Gunakan transaksi untuk memastikan operasi atomik
            return DB::transaction(function () use ($request) {
                // Ambil data permintaan
                $permintaan = PermintaanStok::findOrFail($request->permintaan_id);
                Log::info("Permintaan ditemukan: id={$permintaan->id}, status={$permintaan->status}, jumlah={$permintaan->jumlah}");

                // Pastikan status permintaan adalah 'diajukan'
                if ($permintaan->status !== 'diajukan') {
                    return response()->json(['message' => 'Permintaan sudah diproses'], 400);
                }

                // Validasi bahan_id dan outlet_id
                if (!\App\Models\Bahan::find($permintaan->bahan_id)) {
                    return response()->json(['message' => 'Bahan tidak ditemukan'], 400);
                }
                if (!\App\Models\Outlet::find($permintaan->outlet_id)) {
                    return response()->json(['message' => 'Outlet tidak ditemukan'], 400);
                }

                // Ambil stok gudang
                $stokGudang = StokGudang::where('bahan_id', $permintaan->bahan_id)->first();
                Log::info("Stok gudang: bahan_id={$permintaan->bahan_id}, stok=" . ($stokGudang ? $stokGudang->stok : 'null'));

                // Pastikan stok cukup
                if (!$stokGudang || $stokGudang->stok < $permintaan->jumlah) {
                    return response()->json(['message' => 'Stok gudang tidak cukup'], 400);
                }

                // Validasi stok tidak menjadi negatif
                if ($stokGudang->stok - $permintaan->jumlah < 0) {
                    return response()->json(['message' => 'Stok gudang tidak boleh negatif'], 400);
                }

                // Pastikan jumlah adalah angka desimal yang valid untuk decimal(10,3)
                $jumlah = (float) $permintaan->jumlah;
                if ($jumlah <= 0 || $jumlah > 9999999.999) {
                    return response()->json(['message' => 'Jumlah tidak valid untuk kolom decimal(10,3)'], 400);
                }

                // Buat record barang keluar
                $keluar = BarangKeluar::create([
                    'bahan_id' => $permintaan->bahan_id,
                    'outlet_id' => $permintaan->outlet_id,
                    'permintaan_id' => $permintaan->id,
                    'jumlah' => $jumlah,
                    'tanggal_keluar' => now(),
                    'status' => 'dikirim'
                ]);
                Log::info("Record barang_keluar dibuat: id={$keluar->id}");

                // Kurangi stok gudang
                $stokGudang->decrement('stok', $jumlah);
                Log::info("Stok gudang setelah decrement: {$stokGudang->stok}");

                // Perbarui status permintaan stok menjadi 'dikirim'
                $permintaan->update(['status' => 'dikirim']);
                Log::info("Status permintaan stok diperbarui: id={$permintaan->id}, status=dikirim");

                return response()->json([
                    'message' => 'Barang keluar berhasil dicatat!',
                    'data' => $keluar->load(['bahan', 'outlet'])
                ], 201);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Kesalahan Database: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Kesalahan database: ' . $e->getMessage()], 500);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Model Tidak Ditemukan: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Permintaan tidak ditemukan'], 404);
        } catch (\Exception $e) {
            Log::error('Kesalahan: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Memperbarui record barang keluar
     */
    public function update(Request $request, $id)
    {
        try {
            $barangKeluar = BarangKeluar::findOrFail($id);

            if ($request->user()->role !== 'gudang') {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            $request->validate([
                'bahan_id' => 'required|exists:bahan,id',
                'outlet_id' => 'required|exists:outlets,id',
                'permintaan_id' => 'nullable|exists:permintaan_stok,id',
                'jumlah' => 'required|numeric|min:0.001',
                'status' => 'required|in:dikirim,diterima'
            ]);

            $selisih = $request->jumlah - $barangKeluar->jumlah;

            $barangKeluar->update([
                'bahan_id' => $request->bahan_id,
                'outlet_id' => $request->outlet_id,
                'permintaan_id' => $request->permintaan_id,
                'jumlah' => $request->jumlah,
                'status' => $request->status
            ]);

            if ($selisih !== 0) {
                $stokGudang = StokGudang::where('bahan_id', $request->bahan_id)->first();
                if ($stokGudang) {
                    $stokGudang->increment('stok', -$selisih); // Tambah jika selisih negatif, kurangi jika positif
                }

                $stokOutlet = StokOutlet::where('outlet_id', $request->outlet_id)->where('bahan_id', $request->bahan_id)->first();
                if ($stokOutlet) {
                    $stokOutlet->increment('stok', $selisih);
                }
            }

            return response()->json([
                'message' => 'Barang keluar berhasil diperbarui!',
                'data' => $barangKeluar->load(['bahan', 'outlet'])
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Barang Keluar Not Found: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Barang keluar tidak ditemukan'], 404);
        } catch (\Exception $e) {
            Log::error('Barang Keluar Update Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menghapus record barang keluar
     */
    public function destroy(Request $request, $id)
    {
        try {
            $barangKeluar = BarangKeluar::findOrFail($id);

            if ($request->user()->role !== 'gudang') {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            $jumlah = $barangKeluar->jumlah;
            $bahanId = $barangKeluar->bahan_id;
            $outletId = $barangKeluar->outlet_id;

            $barangKeluar->delete();

            $stokGudang = StokGudang::where('bahan_id', $bahanId)->first();
            if ($stokGudang) {
                $stokGudang->increment('stok', $jumlah);
            }

            $stokOutlet = StokOutlet::where('outlet_id', $outletId)->where('bahan_id', $bahanId)->first();
            if ($stokOutlet) {
                $stokOutlet->decrement('stok', $jumlah);
            }

            return response()->json(['message' => 'Barang keluar berhasil dihapus!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Barang Keluar Not Found: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Barang keluar tidak ditemukan'], 404);
        } catch (\Exception $e) {
            Log::error('Barang Keluar Destroy Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menerima barang keluar
     */
    public function terima(Request $request, $id)
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $keluar = BarangKeluar::findOrFail($id);

                // Jika pengguna adalah karyawan, pastikan barang untuk outlet mereka
                if ($request->user()->role === 'karyawan') {
                    if (!$request->user()->outlet_id || $keluar->outlet_id !== $request->user()->outlet_id) {
                        return response()->json(['message' => 'Anda hanya bisa menerima barang untuk outlet Anda'], 403);
                    }
                }

                if ($keluar->status !== 'dikirim') {
                    return response()->json(['message' => 'Barang sudah diterima atau belum dikirim'], 400);
                }

                if ($request->hasFile('bukti_foto')) {
                    $uploaded = Cloudinary::upload($request->file('bukti_foto')->getRealPath(), [
                        'folder' => 'bukti_penerimaan'
                    ]);
                    $keluar->bukti_foto = $uploaded->getSecurePath();
                }

                $keluar->status = 'diterima';
                $keluar->save();
                Log::info("Status barang keluar diperbarui oleh {$request->user()->role}: id={$keluar->id}, status=diterima");

                if ($keluar->permintaan_id) {
                    $permintaan = PermintaanStok::find($keluar->permintaan_id);
                    if ($permintaan) {
                        $permintaan->update(['status' => 'diterima']);
                        Log::info("Status permintaan stok diperbarui: id={$permintaan->id}, status=diterima");
                    }
                }

                $stokOutlet = StokOutlet::firstOrCreate(
                    ['outlet_id' => $keluar->outlet_id, 'bahan_id' => $keluar->bahan_id],
                    ['stok' => 0]
                );
                $stokOutlet->increment('stok', $keluar->jumlah);
                Log::info("Stok outlet bertambah: outlet_id={$keluar->outlet_id}, bahan_id={$keluar->bahan_id}, jumlah={$keluar->jumlah}, stok_baru={$stokOutlet->stok}");

                return response()->json([
                    'message' => 'Barang berhasil diterima dan stok outlet diperbarui!',
                    'data' => $keluar->load(['bahan', 'outlet'])
                ], 200);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Barang Keluar Not Found: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Barang keluar tidak ditemukan'], 404);
        } catch (\Exception $e) {
            Log::error('Barang Keluar Terima Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}