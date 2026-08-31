<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('rides', 'pages::rides.index')->name('rides.index');
    Route::livewire('devices', 'pages::devices.index')->name('devices.index');
});

require __DIR__.'/settings.php';
