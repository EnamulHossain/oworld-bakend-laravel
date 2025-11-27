<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

Route::get('public/categories', [PublicController::class, 'categories']);
Route::get('public/categories/{id}', [PublicController::class, 'categoryDetail']);
Route::get('public/events', [PublicController::class, 'events']);
Route::get('public/events/highlights', [PublicController::class, 'eventHighlights']);
Route::get('public/offers', [PublicController::class, 'offers']);
Route::get('public/offers/highlights', [PublicController::class, 'offerHighlights']);
Route::get('public/search', [PublicController::class, 'search']);
Route::get('public/settings/{key}', [PublicController::class, 'setting']);

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('users', [AdminController::class, 'users']);
    Route::patch('users/{user}/role', [AdminController::class, 'updateUserRole']);
    Route::delete('users/{user}', [AdminController::class, 'deleteUser']);
    Route::get('organizations', [AdminController::class, 'organizations']);
    Route::get('stats', [AdminController::class, 'stats']);

    Route::get('categories', [AdminController::class, 'listCategories']);
    Route::post('categories', [AdminController::class, 'storeCategory']);
    Route::put('categories/{category}', [AdminController::class, 'updateCategory']);
    Route::delete('categories/{category}', [AdminController::class, 'deleteCategory']);
    Route::post('categories/upload-image', [AdminController::class, 'uploadCategoryImage']);

    Route::get('events', [AdminController::class, 'listEvents']);
    Route::post('events', [AdminController::class, 'storeEvent']);
    Route::put('events/{event}', [AdminController::class, 'updateEvent']);
    Route::delete('events/{event}', [AdminController::class, 'deleteEvent']);
    Route::post('events/upload-banner', [AdminController::class, 'uploadEventBanner']);

    Route::get('offers', [AdminController::class, 'listOffers']);
    Route::post('offers', [AdminController::class, 'storeOffer']);
    Route::put('offers/{offer}', [AdminController::class, 'updateOffer']);
    Route::delete('offers/{offer}', [AdminController::class, 'deleteOffer']);
    Route::post('offers/upload-media', [AdminController::class, 'uploadOfferMedia']);

    Route::get('settings', [AdminController::class, 'listSettings']);
    Route::post('settings', [AdminController::class, 'storeSetting']);
    Route::put('settings/{setting}', [AdminController::class, 'updateSetting']);
    Route::delete('settings/{setting}', [AdminController::class, 'deleteSetting']);
    Route::post('settings/upload', [AdminController::class, 'uploadSettingImage']);
});

Route::middleware(['auth:sanctum', 'role:organization'])->prefix('organization')->group(function () {
    Route::get('stats', [OrganizationController::class, 'stats']);
    Route::get('categories', [OrganizationController::class, 'categories']);

    Route::get('events', [OrganizationController::class, 'listEvents']);
    Route::post('events', [OrganizationController::class, 'storeEvent']);
    Route::put('events/{event}', [OrganizationController::class, 'updateEvent']);
    Route::delete('events/{event}', [OrganizationController::class, 'deleteEvent']);
    Route::post('events/upload-banner', [OrganizationController::class, 'uploadBanner']);

    Route::get('offers', [OrganizationController::class, 'listOffers']);
    Route::post('offers', [OrganizationController::class, 'storeOffer']);
    Route::put('offers/{offer}', [OrganizationController::class, 'updateOffer']);
    Route::delete('offers/{offer}', [OrganizationController::class, 'deleteOffer']);
    Route::post('offers/upload-media', [OrganizationController::class, 'uploadOfferMedia']);
});
