<?php

use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\RideController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
| API telemetrii urządzeń — kontrakt w docs/api-telemetria.md.
|
| Uwierzytelnianie idzie tokenem konta, nie Sanctumem. Ścieżki są wspólne
| dla wszystkich rodzajów urządzeń: rodzaj rozpoznajemy po danych, nie po
| adresie (CLAUDE.md §5).
*/
// Kolejność ma znaczenie: ogranicznik stoi **przed** sprawdzeniem tokena,
// więc liczy także nieudane próby. Odwrotnie zgadywanie tokena nie byłoby
// w żaden sposób limitowane.
Route::prefix('v1')->middleware(['throttle:telemetria', 'account.token'])->group(function () {
    Route::get('ping', PingController::class)->name('api.v1.ping');
    Route::post('rides', [RideController::class, 'store'])->name('api.v1.rides.store');
});
