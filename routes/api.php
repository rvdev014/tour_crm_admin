<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function() {
    Route::get('/user', fn(Request $request) => $request->user());

    Route::get('/hotels', [\App\Http\Controllers\Api\ManualController::class, 'getHotels']);
    Route::get('/countries', [\App\Http\Controllers\Api\ManualController::class, 'getCountries']);
    Route::get('/cities', [\App\Http\Controllers\Api\ManualController::class, 'getCities']);
});

Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login'])->name('login');
