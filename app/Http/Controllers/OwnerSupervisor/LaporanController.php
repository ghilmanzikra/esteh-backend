<?php

namespace App\Http\Controllers\OwnerSupervisor;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PenjualanExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LaporanController extends Controller
{
    public function pendapatan(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['owner', 'supervisor'])) {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            $start = $request->query('start_date', now()->subDays(30)->format('Y-m-d'));
            $end   = $request->query('end_date', now()->format('Y-m-d'));

            $startDate = Carbon::parse($start)->startOfDay();
            $endDate   = Carbon::parse($end)->endOfDay();

            $data = Transaksi::selectRaw('DATE(tanggal) as tanggal, SUM(total) as total_pemasukan')
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->groupBy('tanggal')
                ->orderBy('tanggal', 'desc')
                ->get();

            $total = $data->sum('total_pemasukan');

            if ($data->isEmpty()) {
                return response()->json([
                    'message'          => 'Tidak ada transaksi dalam periode ini',
                    'periode'          => "$start sampai $end",
                    'total_pendapatan' => 0,
                    'detail_per_hari'  => []
                ]);
            }

            return response()->json([
                'message'          => 'Laporan pendapatan berhasil diambil',
                'periode'          => "$start sampai $end",
                'total_pendapatan' => $total,
                'detail_per_hari'  => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Laporan Pendapatan Error: ' . $e->getMessage());
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function exportCsv(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['owner', 'supervisor'])) {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            $start = $request->query('start_date', now()->subDays(30)->format('Y-m-d'));
            $end   = $request->query('end_date', now()->format('Y-m-d'));

            $filename = "Laporan_Penjualan_{$start}_sd_{$end}.xlsx";

            return Excel::download(new PenjualanExport($start, $end), $filename);

        } catch (\Exception $e) {
            Log::error('Export Error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal export'], 500);
        }
    }

    public function transaksiDetail(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['owner', 'supervisor'])) {
                return response()->json(['message' => 'Akses ditolak'], 403);
            }

            $start = $request->query('start_date', now()->subDays(7)->format('Y-m-d'));
            $end   = $request->query('end_date', now()->format('Y-m-d'));

            $transaksi = Transaksi::with(['outlet', 'karyawan', 'itemTransaksi.produk'])
                ->whereBetween('tanggal', [
                    Carbon::parse($start)->startOfDay(),
                    Carbon::parse($end)->endOfDay()
                ])
                ->latest()
                ->get();

            return response()->json([
                'message'         => 'Detail transaksi berhasil diambil',
                'periode'         => "$start sampai $end",
                'total_transaksi' => $transaksi->count(),
                'total_nominal'   => $transaksi->sum('total'),
                'data'            => $transaksi
            ]);

        } catch (\Exception $e) {
            Log::error('Detail Transaksi Error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan'], 500);
        }
    }
}