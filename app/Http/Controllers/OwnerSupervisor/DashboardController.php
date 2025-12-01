<?php

namespace App\Http\Controllers\OwnerSupervisor;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\Outlet;
use App\Models\User;
use App\Models\StokGudang;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $hariIni = Carbon::today();

            // Ambil semua stok gudang + nama bahan + status kritis (opsional bonus)
            $stokGudang = StokGudang::with('bahan')
                ->get()
                ->map(function ($item) {
                    $stok = floatval(str_replace(['.', ','], ['', '.'], $item->stok));
                    $minimum = $item->bahan?->stok_minimum_gudang ?? 0;

                    $item->stok_aktual = $stok; // dalam angka bersih
                    $item->is_kritis   = $stok <= $minimum;
                    $item->status       = $stok <= $minimum ? 'Kritis' : 'Aman';

                    return $item;
                })
                ->sortBy('status') // yang kritis di atas
                ->values();

            $data = [
                'total_outlet'        => Outlet::count(),
                'total_karyawan'      => User::where('role', 'karyawan')->count(),
                'total_gudang'        => User::where('role', 'gudang')->count(),
                'pendapatan_hari_ini' => Transaksi::whereDate('tanggal', $hariIni)->sum('total'),
                'transaksi_hari_ini'  => Transaksi::whereDate('tanggal', $hariIni)->count(),

                // INI YANG PALING PENTING BUAT OWNER
                'stok_gudang'        => $stokGudang,

                // Bonus: tetap kasih tahu ada berapa yang kritis
                'jumlah_stok_kritis'  => $stokGudang->where('is_kritis', true)->count(),

                'permintaan_pending'  => \App\Models\PermintaanStok::where('status', 'diajukan')->count(),
            ];

            return response()->json([
                'message'      => 'Data dashboard berhasil diambil',
                'data'         => $data,
                'last_updated' => now()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard Error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}