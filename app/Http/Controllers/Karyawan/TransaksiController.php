<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\StokOutlet;
use App\Models\Transaksi;
use App\Models\ItemTransaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Validator;

class TransaksiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $outletId = auth()->user()->outlet_id;
            $transaksi = Transaksi::where('outlet_id', $outletId)
                ->with('itemTransaksi.produk')
                ->latest()
                ->get();

            return response()->json($transaksi);
        } catch (\Exception $e) {
            Log::error('Transaksi Index Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->outlet_id) {
                return response()->json(['error' => 'Outlet tidak valid'], 403);
            }

            // Validasi input dasar
            $request->validate([
                'tanggal' => 'nullable|date_format:Y-m-d H:i:s',
                'metode_bayar' => 'required|in:tunai,qris',
                'bukti_qris' => 'nullable|file|mimes:jpg,png,jpeg|max:5120', // Maks 5MB
                'items' => 'required',
            ]);

            // Urai items dari string JSON ke array
            $items = json_decode($request->input('items'), true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($items) || empty($items)) {
                Log::error('JSON Decode Error for items (store): ', ['input' => $request->input('items'), 'error' => json_last_error_msg()]);
                return response()->json(['error' => ['items' => ['The items field must be a valid JSON array']]], 422);
            }

            // Validasi setiap item dalam array
            foreach ($items as $index => $item) {
                $validator = Validator::make($item, [
                    'produk_id' => 'required|exists:produk,id',
                    'quantity' => 'required|integer|min:1'
                ]);

                if ($validator->fails()) {
                    Log::error('Item Validation Failed for index ' . $index . ' (store): ', $validator->errors()->toArray());
                    return response()->json(['error' => ["items.$index" => $validator->errors()->all()]], 422);
                }
            }

            return DB::transaction(function () use ($request, $user, $items) {
                $total = 0;

                // Validasi stok outlet berdasarkan komposisi
                foreach ($items as $item) {
                    $produk = Produk::with('komposisi.bahan')->findOrFail($item['produk_id']);
                    foreach ($produk->komposisi as $komposisi) {
                        $stokOutlet = StokOutlet::where('outlet_id', $user->outlet_id)
                            ->where('bahan_id', $komposisi->bahan_id)
                            ->first();

                        $kebutuhanBahan = $item['quantity'] * $komposisi->quantity;
                        if (!$stokOutlet || $stokOutlet->stok < $kebutuhanBahan) {
                            return response()->json(['message' => "Stok outlet tidak cukup untuk produk ID {$item['produk_id']} (bahan ID {$komposisi->bahan_id})"], 400);
                        }
                    }

                    $subtotal = $produk->harga * $item['quantity'];
                    $total += $subtotal;
                }

                // Unggah bukti QRIS jika ada
                $buktiQris = null;
                if ($request->hasFile('bukti_qris') && $request->metode_bayar === 'qris') {
                    $uploaded = Cloudinary::upload($request->file('bukti_qris')->getRealPath(), [
                        'folder' => 'bukti_qris'
                    ]);
                    $buktiQris = $uploaded->getSecurePath();
                }

                // Buat transaksi
                $transaksi = Transaksi::create([
                    'outlet_id' => $user->outlet_id,
                    'karyawan_id' => $user->id,
                    'tanggal' => $request->input('tanggal', Carbon::now()->format('Y-m-d H:i:s')),
                    'total' => $total,
                    'metode_bayar' => $request->metode_bayar,
                    'bukti_qris' => $buktiQris
                ]);

                // Buat item transaksi dan kurangi stok outlet
                foreach ($items as $item) {
                    $produk = Produk::with('komposisi.bahan')->findOrFail($item['produk_id']);
                    $subtotal = $produk->harga * $item['quantity'];

                    ItemTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'produk_id' => $item['produk_id'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal
                    ]);

                    foreach ($produk->komposisi as $komposisi) {
                        $stokOutlet = StokOutlet::where('outlet_id', $user->outlet_id)
                            ->where('bahan_id', $komposisi->bahan_id)
                            ->first();
                        $kebutuhanBahan = $item['quantity'] * $komposisi->quantity;
                        $stokOutlet->decrement('stok', $kebutuhanBahan);
                    }
                }

                return response()->json([
                    'message' => 'Transaksi berhasil dibuat!',
                    'data' => $transaksi->load('itemTransaksi.produk')
                ], 201);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error (store): ', $e->errors()->toArray());
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Transaksi Store Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaksi $transaksi)
    {
        try {
            $outletId = auth()->user()->outlet_id;

            if ($transaksi->outlet_id !== $outletId) {
                return response()->json(['error' => 'Akses ditolak'], 403);
            }

            return response()->json($transaksi->load('itemTransaksi.produk'));
        } catch (\Exception $e) {
            Log::error('Transaksi Show Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaksi $transaksi)
    {
        try {
            // Debugging: Log semua input mentah yang diterima
            Log::info('Update Input received (raw): ', $request->all());
            $rawInput = file_get_contents('php://input');
            Log::info('Update Input (php://input): ', ['raw' => $rawInput]);

            $user = auth()->user();
            if (!$user || !$user->outlet_id || $transaksi->outlet_id !== $user->outlet_id) {
                return response()->json(['error' => 'Akses ditolak'], 403);
            }

            // Validasi input dasar
            $validator = Validator::make($request->all(), [
                'tanggal' => 'nullable|date_format:Y-m-d H:i:s',
                'metode_bayar' => 'required|in:tunai,qris',
                'bukti_qris' => 'nullable|file|mimes:jpg,png,jpeg|max:5120',
                'items' => 'required',
            ]);

            if ($validator->fails()) {
                Log::error('Validation Failed (update): ', $validator->errors()->toArray());
                return response()->json(['error' => $validator->errors()], 422);
            }

            // Urai items dari string JSON ke array
            $itemsInput = $request->input('items');
            Log::info('Items input before decode: ', ['value' => $itemsInput]);
            $items = json_decode($itemsInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON Decode Error for items (update): ', ['input' => $itemsInput, 'error' => json_last_error_msg()]);
                return response()->json(['error' => ['items' => ['The items field must be a valid JSON array']]], 422);
            }
            if (!is_array($items) || empty($items)) {
                Log::error('Items is not an array or empty: ', ['decoded' => $items]);
                return response()->json(['error' => ['items' => ['The items field must be a valid JSON array']]], 422);
            }

            // Validasi setiap item dalam array
            foreach ($items as $index => $item) {
                $validator = Validator::make($item, [
                    'produk_id' => 'required|exists:produk,id',
                    'quantity' => 'required|integer|min:1'
                ]);

                if ($validator->fails()) {
                    Log::error('Item Validation Failed for index ' . $index . ' (update): ', $validator->errors()->toArray());
                    return response()->json(['error' => ["items.$index" => $validator->errors()->all()]], 422);
                }
            }

            return DB::transaction(function () use ($request, $user, $transaksi, $items) {
                $total = 0;

                // Kembalikan stok outlet dari item transaksi sebelumnya
                foreach ($transaksi->itemTransaksi as $oldItem) {
                    $produk = Produk::with('komposisi.bahan')->findOrFail($oldItem->produk_id);
                    foreach ($produk->komposisi as $komposisi) {
                        $stokOutlet = StokOutlet::where('outlet_id', $user->outlet_id)
                            ->where('bahan_id', $komposisi->bahan_id)
                            ->first();
                        if ($stokOutlet) {
                            $stokOutlet->increment('stok', $oldItem->quantity * $komposisi->quantity);
                        }
                    }
                }

                // Hapus item transaksi lama
                $transaksi->itemTransaksi()->delete();

                // Validasi stok outlet untuk item baru
                foreach ($items as $item) {
                    $produk = Produk::with('komposisi.bahan')->findOrFail($item['produk_id']);
                    foreach ($produk->komposisi as $komposisi) {
                        $stokOutlet = StokOutlet::where('outlet_id', $user->outlet_id)
                            ->where('bahan_id', $komposisi->bahan_id)
                            ->first();

                        $kebutuhanBahan = $item['quantity'] * $komposisi->quantity;
                        if (!$stokOutlet || $stokOutlet->stok < $kebutuhanBahan) {
                            return response()->json(['message' => "Stok outlet tidak cukup untuk produk ID {$item['produk_id']} (bahan ID {$komposisi->bahan_id})"], 400);
                        }
                    }

                    $subtotal = $produk->harga * $item['quantity'];
                    $total += $subtotal;
                }

                // Unggah bukti QRIS jika ada
                $buktiQris = $transaksi->bukti_qris;
                if ($request->hasFile('bukti_qris') && $request->metode_bayar === 'qris') {
                    $uploaded = Cloudinary::upload($request->file('bukti_qris')->getRealPath(), [
                        'folder' => 'bukti_qris'
                    ]);
                    $buktiQris = $uploaded->getSecurePath();
                } elseif ($request->metode_bayar === 'tunai') {
                    $buktiQris = null;
                }

                // Perbarui transaksi
                $transaksi->update([
                    'tanggal' => $request->input('tanggal', $transaksi->tanggal),
                    'total' => $total,
                    'metode_bayar' => $request->metode_bayar,
                    'bukti_qris' => $buktiQris
                ]);

                // Buat item transaksi baru dan kurangi stok outlet
                foreach ($items as $item) {
                    $produk = Produk::with('komposisi.bahan')->findOrFail($item['produk_id']);
                    $subtotal = $produk->harga * $item['quantity'];

                    ItemTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'produk_id' => $item['produk_id'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal
                    ]);

                    foreach ($produk->komposisi as $komposisi) {
                        $stokOutlet = StokOutlet::where('outlet_id', $user->outlet_id)
                            ->where('bahan_id', $komposisi->bahan_id)
                            ->first();
                        $kebutuhanBahan = $item['quantity'] * $komposisi->quantity;
                        $stokOutlet->decrement('stok', $kebutuhanBahan);
                    }
                }

                return response()->json([
                    'message' => 'Transaksi berhasil diperbarui!',
                    'data' => $transaksi->load('itemTransaksi.produk')
                ], 200);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error (update): ', $e->errors()->toArray());
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Transaksi Update Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaksi $transaksi)
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->outlet_id || $transaksi->outlet_id !== $user->outlet_id) {
                return response()->json(['error' => 'Akses ditolak'], 403);
            }

            return DB::transaction(function () use ($transaksi, $user) {
                // Kembalikan stok outlet
                foreach ($transaksi->itemTransaksi as $item) {
                    $produk = Produk::with('komposisi.bahan')->findOrFail($item->produk_id);
                    foreach ($produk->komposisi as $komposisi) {
                        $stokOutlet = StokOutlet::where('outlet_id', $user->outlet_id)
                            ->where('bahan_id', $komposisi->bahan_id)
                            ->first();
                        if ($stokOutlet) {
                            $stokOutlet->increment('stok', $item->quantity * $komposisi->quantity);
                        }
                    }
                }

                // Hapus item transaksi dan transaksi
                $transaksi->itemTransaksi()->delete();
                $transaksi->delete();

                return response()->json(['message' => 'Transaksi berhasil dihapus'], 200);
            });
        } catch (\Exception $e) {
            Log::error('Transaksi Destroy Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}