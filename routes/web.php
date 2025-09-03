<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'products.search')->name('home');
Volt::route('/products', 'products.search')->name('products.search');
Volt::route('/products/{product}', 'products.show')->name('products.show');

// Ecommerce Search Routes
Volt::route('/ecommerce-search', 'ecommerce-search')->name('ecommerce.search');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
