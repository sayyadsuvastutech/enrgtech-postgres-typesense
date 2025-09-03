<?php

use App\Http\Controllers\EcommerceSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('v1')->group(function () {
    
    // Ecommerce Search API Routes
    Route::prefix('ecommerce-search')->group(function () {
        Route::get('/search', [EcommerceSearchController::class, 'search'])
            ->name('api.ecommerce.search');
        
        Route::get('/facets', [EcommerceSearchController::class, 'facets'])
            ->name('api.ecommerce.facets');
        
        Route::get('/autocomplete', [EcommerceSearchController::class, 'autocomplete'])
            ->name('api.ecommerce.autocomplete');
        
        Route::get('/price-ranges', [EcommerceSearchController::class, 'priceRanges'])
            ->name('api.ecommerce.price-ranges');
    });
});