<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function() {
    //    phpinfo();
    return redirect('/admin');
});

Route::get('export/{tour}', [\App\Http\Controllers\ExportController::class, 'export'])->name('export');
Route::get('export-client/{tour}', [\App\Http\Controllers\ExportController::class, 'exportClient'])->name(
    'export-client'
);
Route::get('export-museum/{tour}', [\App\Http\Controllers\ExportController::class, 'exportMuseum'])->name(
    'export-museum'
);
Route::get('export-hotel/{tour}', [\App\Http\Controllers\ExportController::class, 'exportHotelsZip'])->name('export-hotel');
Route::get('export-all/{tour}', [\App\Http\Controllers\ExportController::class, 'exportAllZip'])->name('export-all');
Route::get('export-transfer/{transfer}', [\App\Http\Controllers\ExportController::class, 'exportTransfer'])->name('export-transfer');

