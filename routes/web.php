<?php

use App\Models\Ride;
use Illuminate\Support\Facades\Route;

// Strona główna pokazuje ostatni zapisany przejazd zamiast wymyślonych
// liczb. Kolejność po `id`, nie po `recorded_at` — przejazdy sprzed modułu
// GPS mają czas pusty, a chodzi o to, co dotarło do nas najpóźniej.
Route::get('/', fn () => view('welcome', [
    'ostatniaJazda' => Ride::query()->latest('id')->first(),
]))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('rides', 'pages::rides.index')->name('rides.index');
    Route::livewire('devices', 'pages::devices.index')->name('devices.index');
});

require __DIR__.'/settings.php';
