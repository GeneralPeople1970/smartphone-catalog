<?php

use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\HomepageFeaturedPhoneController;
use App\Http\Controllers\Api\HomepageSlideController;
use App\Http\Controllers\Api\PhoneController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:120,1,api'])->get('/me', SessionController::class);

Route::middleware('throttle:120,1,api')->group(function () {
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brands/{brand}/search', [PhoneController::class, 'brandSearch']);
    Route::get('/homepage-featured-phones', [HomepageFeaturedPhoneController::class, 'index']);
    Route::get('/homepage-slides', [HomepageSlideController::class, 'index']);
    Route::get('/phones', [PhoneController::class, 'index']);
    Route::get('/phones/detail', [PhoneController::class, 'detail']);
    Route::get('/phones/{phone}', [PhoneController::class, 'show'])->whereNumber('phone');
    Route::get('/search', [PhoneController::class, 'search']);
});
