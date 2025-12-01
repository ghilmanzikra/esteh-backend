<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// ============================== TANPA AUTH ==============================
Route::post('/login', [AuthController::class, 'login']);

// ============================== BUTUH AUTH ==============================
Route::middleware('auth:sanctum')->group(function () {

    // ---------- Auth ----------
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ====================== OWNER & SUPERVISOR ======================
    Route::middleware('role:owner,supervisor')->group(function () {
        Route::apiResource('outlets', \App\Http\Controllers\OwnerSupervisor\OutletController::class);
        Route::apiResource('users', \App\Http\Controllers\OwnerSupervisor\UserController::class);
        
        Route::get('laporan/pendapatan', [\App\Http\Controllers\OwnerSupervisor\LaporanController::class, 'pendapatan']);
        Route::get('laporan/export', [\App\Http\Controllers\OwnerSupervisor\LaporanController::class, 'exportCsv']);
        Route::get('dashboard', [\App\Http\Controllers\OwnerSupervisor\DashboardController::class, 'index']);
    });

    // ====================== GUDANG ======================
    Route::prefix('gudang')->group(function () {
        // Endpoint hanya untuk gudang
        Route::middleware('role:gudang')->group(function () {
            Route::apiResource('bahan', \App\Http\Controllers\Gudang\BahanController::class);
            Route::apiResource('barang-masuk', \App\Http\Controllers\Gudang\BarangMasukController::class);
            Route::apiResource('permintaan-stok', \App\Http\Controllers\Gudang\PermintaanStokController::class)
                 ->only(['index', 'show', 'update']);
            Route::get('stok', [\App\Http\Controllers\Gudang\StokController::class, 'gudang']);
            
            // Endpoint barang-keluar kecuali terima
            Route::apiResource('barang-keluar', \App\Http\Controllers\Gudang\BarangKeluarController::class)
                 ->except(['terima']);
        });

        // Endpoint terima untuk gudang dan karyawan
        Route::post('barang-keluar/{id}/terima', [\App\Http\Controllers\Gudang\BarangKeluarController::class, 'terima'])
             ->middleware('role:gudang,karyawan');
    });

    // ====================== KARYAWAN (KASIR) ======================
    Route::middleware('role:karyawan')->group(function () {
        Route::apiResource('produk', \App\Http\Controllers\Karyawan\ProdukController::class);
        Route::apiResource('transaksi', \App\Http\Controllers\Karyawan\TransaksiController::class);

        Route::apiResource('permintaan-stok', \App\Http\Controllers\Karyawan\PermintaanStokController::class)
             ->only(['index', 'store', 'show']);

        Route::get('stok/outlet', [\App\Http\Controllers\Karyawan\StokController::class, 'outlet']);
    });

});