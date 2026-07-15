<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\HomepageSlideController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', FrontendController::class)->name('home');

Route::middleware(['auth', 'active', 'role:editor,admin,owner'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/homepage', [HomepageController::class, 'index'])->name('homepage.index');
    Route::post('/admin/homepage/featured-phones', [HomepageController::class, 'store'])->name('homepage.featured-phones.store');
    Route::patch('/admin/homepage/featured-phones/{featuredPhone}/move-up', [HomepageController::class, 'moveUp'])->name('homepage.featured-phones.move-up');
    Route::patch('/admin/homepage/featured-phones/{featuredPhone}/move-down', [HomepageController::class, 'moveDown'])->name('homepage.featured-phones.move-down');
    Route::put('/admin/homepage/featured-phones/{featuredPhone}', [HomepageController::class, 'update'])->name('homepage.featured-phones.update');
    Route::delete('/admin/homepage/featured-phones/{featuredPhone}', [HomepageController::class, 'destroy'])->name('homepage.featured-phones.destroy');
    Route::get('/admin/products/import', [ProductController::class, 'importForm'])->name('products.import');
    Route::post('/admin/products/import', [ProductController::class, 'import']);
    Route::resource('/admin/products', ProductController::class)->except('show');
    Route::patch('/admin/homepage-slides/{homepageSlide}/move-up', [HomepageSlideController::class, 'moveUp'])->name('homepage-slides.move-up');
    Route::patch('/admin/homepage-slides/{homepageSlide}/move-down', [HomepageSlideController::class, 'moveDown'])->name('homepage-slides.move-down');
    Route::resource('/admin/homepage-slides', HomepageSlideController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'active', 'role:admin,owner'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/admin/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
    Route::patch('/admin/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('{any}', FrontendController::class)
    ->where('any', '^(?!(admin|api|assets|build|confirm-password|dashboard|dist|email|forgot-password|frontend|login|logout|password|profile|register|reset-password|storage|up|verify-email|_ignition)(/|$)).*$');
