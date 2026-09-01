<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/v1/ping` — sprawdzenie konfiguracji zaraz po jej zapisaniu.
 *
 * Kontrakt telemetrii §4: odpowiedź 200 z dowolną treścią znaczy „token
 * dobry", 401 znaczy „token zły". Nic więcej urządzeniu nie jest potrzebne,
 * więc treść trzymamy minimalną.
 */
class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
