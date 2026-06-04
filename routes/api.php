<?php

use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\HomepageFeaturedPhoneController;
use App\Http\Controllers\Api\HomepageSlideController;
use App\Http\Controllers\Api\PhoneController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->get('/me', function (Request $request) {
    $user = $request->user();

    return response()->json([
        'authenticated' => $user !== null,
        'user' => $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ] : null,
    ]);
});

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{brand}/search', [PhoneController::class, 'brandSearch']);
Route::get('/homepage-featured-phones', [HomepageFeaturedPhoneController::class, 'index']);
Route::get('/homepage-slides', [HomepageSlideController::class, 'index']);
Route::get('/phones', [PhoneController::class, 'index']);
Route::get('/phones/detail', [PhoneController::class, 'detail']);
Route::get('/phones/{phone}', [PhoneController::class, 'show'])->whereNumber('phone');
Route::get('/search', [PhoneController::class, 'search']);
