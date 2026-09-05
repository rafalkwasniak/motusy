<?php

use App\Http\Controllers\TrackGpxController;
use App\Models\Ride;
use Illuminate\Support\Facades\Route;

// Strona główna pokazuje ostatni zapisany przejazd zamiast wymyślonych
// liczb. Kolejność po `id`, nie po `recorded_at` — przejazdy sprzed modułu
// GPS mają czas pusty, a chodzi o to, co dotarło do nas najpóźniej.
Route::get('/', fn () => view('welcome', [
    'ostatniaJazda' => Ride::query()->latest('id')->first(),
]))->name('home');

// Publiczny podgląd jednego przejazdu. Poświadczeniem jest sam adres:
// `share_token` ma 128 bitów losowości, więc trafienie w cudzy link jest
// nierealne, a właściciel decyduje o dostępie tym, komu go wyśle.
//
// Krótki prefiks `p/` zamiast `rides/`, bo ten adres ląduje w komunikatorach
// i ma się mieścić w jednej linii razem z tokenem.
Route::get('p/{ride:share_token}/track.gpx', TrackGpxController::class)
    ->name('rides.shared.track.gpx');

Route::livewire('p/{ride:share_token}', 'pages::rides.show')
    ->name('rides.shared');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('rides', 'pages::rides.index')->name('rides.index');

    Route::livewire('rides/{ride}', 'pages::rides.show')->name('rides.show');

    // Ślad do pobrania jako GPX. Pod sesją, nie w API — link klika właściciel
    // w panelu, a przeglądarka nie ma tokena urządzenia.
    Route::get('rides/{ride}/track.gpx', TrackGpxController::class)->name('rides.track.gpx');

    Route::livewire('devices', 'pages::devices.index')->name('devices.index');
});

require __DIR__.'/settings.php';
