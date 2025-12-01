<?php

namespace App\Exports;

use App\Models\Transaksi;
use App\Models\DailyReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class LaporanCsvExport implements FromCollection, WithHeadings, WithMapping
{
    protected $start;
    protected $end;
    protected $useDailyReport;

    public function __construct($start, $end, $useDailyReport = true)
    {
        $this->start = $start;
        $this->end = $end;
        $this->useDailyReport = $useDailyReport;
    }

    public function collection()
    {
        if ($this->useDailyReport && \Schema::hasTable('daily_reports')) {
            return DailyReport::whereBetween('tanggal', [$this->start, $this->end])
                ->orderBy('tanggal', 'desc')
                ->get();
        }

        // Fallback: detail per transaksi
        return Transaksi::with(['outlet', 'karyawan', 'itemTransaksi.produk'])
            ->whereBetween('tanggal', [
                Carbon::parse($this->start)->startOfDay(),
                Carbon::parse($this->end)->endOfDay()
            ])
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        if ($this->useDailyReport && \Schema::hasTable('daily_reports')) {
            return [
                'Tanggal',
                'Total Pendapatan (Rp)',
                'Jumlah Transaksi',
                'Jumlah Item Terjual'
            ];
        }

        // Detail per transaksi
        return [
            'No',
            'Tanggal & Jam',
            'Outlet',
            'Kasir',
            'Metode Bayar',
            'Total (Rp)',
            'Item Dibeli'
        ];
    }

    public function map($row): array
    {
        if ($this->useDailyReport && \Schema::hasTable('daily_reports')) {
            return [
                Carbon::parse($row->tanggal)->format('d/m/Y'),
                number_format($row->total_pendapatan, 0, ',', '.'),
                $row->jumlah_transaksi,
                $row->jumlah_item_terjual
            ];
        }

        // Detail transaksi
        $items = $row->itemTransaksi->map(function ($item) {
            return "{$item->quantity}x {$item->produk->nama}";
        })->implode(', ');

        return [
            $row->id,
            Carbon::parse($row->tanggal)->format('d/m/Y H:i'),
            $row->outlet?->nama ?? 'Tanpa Outlet',
            $row->karyawan?->username ?? 'System',
            strtoupper($row->metode_bayar),
            number_format($row->total, 0, ',', '.'),
            $items ?: '-'
        ];
    }
}