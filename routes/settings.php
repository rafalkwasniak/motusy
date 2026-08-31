<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Jedna strona na całe konto — dane, hasło i usunięcie konta.
    // Nazwa trasy zostaje `profile.edit`, bo odwołuje się do niej menu Flux.
    Route::livewire('settings', 'pages::settings.profile')->name('profile.edit');
});
