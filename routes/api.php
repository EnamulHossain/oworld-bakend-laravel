<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::get('google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);
});

Route::get('public/categories', [PublicController::class, 'categories']);
Route::get('public/categories/{id}', [PublicController::class, 'categoryDetail']);
Route::get('public/areas', [PublicController::class, 'areas']);
Route::get('public/events', [PublicController::class, 'events']);
Route::get('public/events/highlights', [PublicController::class, 'eventHighlights']);
Route::get('public/events/{event}', [PublicController::class, 'eventDetail']);
Route::get('public/offers', [PublicController::class, 'offers']);
Route::get('public/offers/highlights', [PublicController::class, 'offerHighlights']);
Route::get('public/offers/{offer}', [PublicController::class, 'offerDetail']);
Route::get('public/organizations', [PublicController::class, 'organizations']);
Route::get('public/organizations/{organization}', [PublicController::class, 'organizationDetail']);
Route::get('public/highlights', [PublicController::class, 'highlights']);
Route::get('public/content-blocks', [PublicController::class, 'contentBlocks']);
Route::get('public/attributes', [PublicController::class, 'attributes']);
Route::get('public/search', [PublicController::class, 'search']);
Route::get('public/settings/{key}', [PublicController::class, 'setting']);
Route::post('public/coupons/validate', [PublicController::class, 'validateCoupon']);
Route::post('public/analytics/events', [PublicController::class, 'trackAnalyticsEvent'])->middleware('throttle:240,1');
Route::post('highlights/{highlight}/share', [PublicController::class, 'shareHighlight']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [UserProfileController::class, 'show']);
    Route::get('profile/coupons', [UserProfileController::class, 'coupons']);
    Route::match(['put', 'patch'], 'profile', [UserProfileController::class, 'update']);
    Route::post('profile/avatar', [UserProfileController::class, 'updateAvatar']);
    Route::post('profile/password', [UserProfileController::class, 'updatePassword']);

    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist', [WishlistController::class, 'store']);
    Route::delete('wishlist', [WishlistController::class, 'destroy']);

    Route::post('coupons/claim', [PublicController::class, 'claimCoupon']);
    Route::post('coupons/redeem', [PublicController::class, 'redeemCoupon']);
    Route::get('highlights/reactions', [PublicController::class, 'highlightReactions']);
    Route::post('highlights/{highlight}/react', [PublicController::class, 'reactToHighlight']);
});

Route::middleware(['auth:sanctum', 'role:admin|superAdmin'])->prefix('admin')->group(function () {
    Route::get('users', [AdminController::class, 'users']);
    Route::patch('users/{user}/role', [AdminController::class, 'updateUserRole']);
    Route::get('admins', [AdminController::class, 'admins']);
    Route::post('admins/assign-bulk', [AdminController::class, 'assignAdminsBulk']);
    Route::post('admins/{user}/assign', [AdminController::class, 'assignAdmin']);
    Route::patch('admins/{user}/status', [AdminController::class, 'updateAdminStatus']);
    Route::delete('users/{user}', [AdminController::class, 'deleteUser']);
    Route::get('organizations', [AdminController::class, 'organizations']);
    Route::post('organizations', [AdminController::class, 'storeOrganization']);
    Route::put('organizations/{user}', [AdminController::class, 'updateOrganization']);
    Route::get('stats', [AdminController::class, 'stats']);
    Route::get('analytics/clicks', [AdminController::class, 'analyticsClicks']);

    Route::get('categories', [AdminController::class, 'listCategories']);
    Route::post('categories', [AdminController::class, 'storeCategory']);
    Route::put('categories/{category}', [AdminController::class, 'updateCategory']);
    Route::delete('categories/{category}', [AdminController::class, 'deleteCategory']);
    Route::post('categories/upload-image', [AdminController::class, 'uploadCategoryImage']);

    Route::get('areas', [AdminController::class, 'listAreas']);
    Route::post('areas', [AdminController::class, 'storeArea']);
    Route::put('areas/{area}', [AdminController::class, 'updateArea']);
    Route::delete('areas/{area}', [AdminController::class, 'deleteArea']);

    Route::get('events', [AdminController::class, 'listEvents']);
    Route::post('events', [AdminController::class, 'storeEvent']);
    Route::put('events/reorder', [AdminController::class, 'reorderEvents']);
    Route::put('events/{event}', [AdminController::class, 'updateEvent']);
    Route::delete('events/{event}', [AdminController::class, 'deleteEvent']);
    Route::post('events/upload-banner', [AdminController::class, 'uploadEventBanner']);
    Route::post('events/upload-thumbnail', [AdminController::class, 'uploadEventThumbnail']);

    Route::get('offers', [AdminController::class, 'listOffers']);
    Route::post('offers', [AdminController::class, 'storeOffer']);
    Route::put('offers/reorder', [AdminController::class, 'reorderOffers']);
    Route::put('offers/{offer}', [AdminController::class, 'updateOffer']);
    Route::delete('offers/{offer}', [AdminController::class, 'deleteOffer']);
    Route::post('offers/upload-media', [AdminController::class, 'uploadOfferMedia']);
    Route::get('coupons', [AdminController::class, 'listCoupons']);
    Route::post('coupons', [AdminController::class, 'storeCoupon']);
    Route::put('coupons/{coupon}', [AdminController::class, 'updateCoupon']);
    Route::delete('coupons/{coupon}', [AdminController::class, 'deleteCoupon']);
    Route::post('coupons/upload-image', [AdminController::class, 'uploadCouponImage']);

    Route::get('highlights', [AdminController::class, 'listHighlights']);
    Route::post('highlights', [AdminController::class, 'storeHighlight']);
    Route::put('highlights/{highlight}', [AdminController::class, 'updateHighlight']);
    Route::delete('highlights/{highlight}', [AdminController::class, 'deleteHighlight']);
    Route::post('highlights/upload', [AdminController::class, 'uploadHighlightMedia']);

    Route::get('settings', [AdminController::class, 'listSettings']);
    Route::post('settings', [AdminController::class, 'storeSetting']);
    Route::put('settings/{setting}', [AdminController::class, 'updateSetting']);
    Route::delete('settings/{setting}', [AdminController::class, 'deleteSetting']);
    Route::post('settings/upload', [AdminController::class, 'uploadSettingImage']);

    Route::get('content-blocks', [AdminController::class, 'listContentBlocks']);
    Route::get('content-blocks/{contentBlock}', [AdminController::class, 'showContentBlock']);
    Route::post('content-blocks', [AdminController::class, 'storeContentBlock']);
    Route::post('content-blocks/upload-thumbnail', [AdminController::class, 'uploadContentBlockThumbnail']);
    Route::put('content-blocks/{contentBlock}', [AdminController::class, 'updateContentBlock']);
    Route::put('content-blocks/{contentBlock}/items', [AdminController::class, 'updateContentBlockItems']);
    Route::delete('content-blocks/{contentBlock}', [AdminController::class, 'deleteContentBlock']);

    Route::get('attributes', [AdminController::class, 'listAttributes']);
    Route::post('attributes', [AdminController::class, 'storeAttribute']);
    Route::put('attributes/{attribute}', [AdminController::class, 'updateAttribute']);
    Route::delete('attributes/{attribute}', [AdminController::class, 'deleteAttribute']);
});

Route::middleware(['auth:sanctum', 'role:organization'])->prefix('organization')->group(function () {
    Route::get('stats', [OrganizationController::class, 'stats']);
    Route::get('profile', [OrganizationController::class, 'profile']);
    Route::put('profile', [OrganizationController::class, 'updateProfile']);
    Route::get('branches', [OrganizationController::class, 'listBranches']);
    Route::post('branches', [OrganizationController::class, 'storeBranch']);
    Route::put('branches/{branch}', [OrganizationController::class, 'updateBranch']);
    Route::delete('branches/{branch}', [OrganizationController::class, 'deleteBranch']);
    Route::get('categories', [OrganizationController::class, 'categories']);
    Route::get('attributes', [OrganizationController::class, 'attributes']);

    Route::get('events', [OrganizationController::class, 'listEvents']);
    Route::post('events', [OrganizationController::class, 'storeEvent']);
    Route::put('events/{event}', [OrganizationController::class, 'updateEvent']);
    Route::delete('events/{event}', [OrganizationController::class, 'deleteEvent']);
    Route::post('events/upload-banner', [OrganizationController::class, 'uploadBanner']);
    Route::post('events/upload-thumbnail', [OrganizationController::class, 'uploadThumbnail']);

    Route::get('offers', [OrganizationController::class, 'listOffers']);
    Route::post('offers', [OrganizationController::class, 'storeOffer']);
    Route::put('offers/{offer}', [OrganizationController::class, 'updateOffer']);
    Route::delete('offers/{offer}', [OrganizationController::class, 'deleteOffer']);
    Route::post('offers/upload-media', [OrganizationController::class, 'uploadOfferMedia']);
});
