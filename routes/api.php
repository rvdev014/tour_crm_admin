<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\ManualController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/me', [AuthController::class, 'updateMe'])->name('update_me'); // tested
    Route::get('/me/web-tours', [AuthController::class, 'getWebTours'])->name('me.web_tours');
});

Route::controller(ManualController::class)->group(function () {
    Route::get('/tours', 'getTours');
    Route::get('/tours/{id}', 'getTour');
    Route::get('/banners', 'getBanners');
    Route::get('/services', 'getServices');
    Route::get('/countries', 'getCountries');
    Route::get('/cities', 'getCities');
});

Route::controller(HotelController::class)->group(function () {
    Route::get('/hotels', 'getHotels');
    Route::get('/hotels/{id}', 'getHotel');
    Route::get('/hotels/{id}/others', 'getHotelOthers');
    Route::post('/hotels/{id}/review', 'storeReview');
    Route::get('/recommended-hotels', 'getRecommendedHotels');
});

Route::post('/login', [AuthController::class, 'login'])->name('login'); // tested
Route::post('/register', [AuthController::class, 'register'])->name('register'); // tested
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:sanctum'); // tested
Route::get('/me', [AuthController::class, 'me'])->name('me'); // tested
Route::post('/auth/google', [AuthController::class, 'googleAuth'])->name('google_auth');
