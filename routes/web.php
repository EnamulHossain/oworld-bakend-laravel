<?php

use App\Http\Controllers\FrontendController;
use App\Http\Controllers\Admin\AdminWebController;
use App\Http\Controllers\Auth\WebAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FrontendController::class, 'home'])->name('home');
Route::get('/categories', [FrontendController::class, 'categories'])->name('categories.index');
Route::get('/categories/{category}', [FrontendController::class, 'category'])->name('categories.show');
Route::get('/events', [FrontendController::class, 'events'])->name('events.index');
Route::get('/events/{event}', [FrontendController::class, 'event'])->name('events.show');
Route::get('/offers', [FrontendController::class, 'offers'])->name('offers.index');
Route::get('/offers/{offer}', [FrontendController::class, 'offer'])->name('offers.show');
Route::get('/search', [FrontendController::class, 'search'])->name('search');
Route::get('/login', [FrontendController::class, 'loginForm'])->name('login');
Route::post('/login', [WebAuthController::class, 'login'])->name('login.store');
Route::get('/register', [FrontendController::class, 'registerForm'])->name('register');
Route::post('/register', [WebAuthController::class, 'register'])->name('register.store');
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [AdminWebController::class, 'dashboard'])->name('dashboard');

    Route::get('/categories', [AdminWebController::class, 'categories'])->name('categories');
    Route::post('/categories', [AdminWebController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{category}', [AdminWebController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminWebController::class, 'deleteCategory'])->name('categories.delete');

    Route::get('/events', [AdminWebController::class, 'events'])->name('events');
    Route::post('/events', [AdminWebController::class, 'storeEvent'])->name('events.store');
    Route::put('/events/{event}', [AdminWebController::class, 'updateEvent'])->name('events.update');
    Route::delete('/events/{event}', [AdminWebController::class, 'deleteEvent'])->name('events.delete');
    Route::get('/offers', [AdminWebController::class, 'offers'])->name('offers');
    Route::get('/offers/create', [AdminWebController::class, 'createOffer'])->name('offers.create');
    Route::get('/offers/{offer}/edit', [AdminWebController::class, 'editOffer'])->name('offers.edit');
    Route::post('/offers', [AdminWebController::class, 'storeOffer'])->name('offers.store');
    Route::put('/offers/{offer}', [AdminWebController::class, 'updateOffer'])->name('offers.update');
    Route::delete('/offers/{offer}', [AdminWebController::class, 'deleteOffer'])->name('offers.delete');

    Route::get('/users', [AdminWebController::class, 'users'])->name('users');
    Route::patch('/users/{user}/role', [AdminWebController::class, 'updateUserRole'])->name('users.role');
    Route::delete('/users/{user}', [AdminWebController::class, 'deleteUser'])->name('users.delete');

    Route::get('/settings', [AdminWebController::class, 'settings'])->name('settings');
    Route::get('/settings/website', [AdminWebController::class, 'website'])->name('settings.website');
    Route::put('/settings/website', [AdminWebController::class, 'updateWebsite'])->name('settings.website.update');
    Route::get('/settings/content/home-slider', [AdminWebController::class, 'homeSlider'])->name('settings.content.home-slider');
    Route::put('/settings/content/home-slider', [AdminWebController::class, 'updateHomeSlider'])->name('settings.content.home-slider.update');
    Route::get('/settings/content/block-1', [AdminWebController::class, 'contentBlockOne'])->name('settings.content.block-one');
    Route::put('/settings/content/block-1', [AdminWebController::class, 'updateContentBlockOne'])->name('settings.content.block-one.update');
    Route::get('/settings/content/block-2', [AdminWebController::class, 'contentBlockTwo'])->name('settings.content.block-two');
    Route::put('/settings/content/block-2', [AdminWebController::class, 'updateContentBlockTwo'])->name('settings.content.block-two.update');
    Route::post('/settings', [AdminWebController::class, 'storeSetting'])->name('settings.store');
    Route::put('/settings/{setting}', [AdminWebController::class, 'updateSetting'])->name('settings.update');
    Route::delete('/settings/{setting}', [AdminWebController::class, 'deleteSetting'])->name('settings.delete');
});
