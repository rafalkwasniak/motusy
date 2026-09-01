<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRidesRequest;
use App\Models\Device;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * `POST /api/v1/rides` — przyjmowanie przejazdów (kontrakt telemetrii §3 i §7).
 */
class RideController extends Controller
{
    public function store(StoreRidesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();
        $deviceId = $data['device_id'];

        $device = Device::firstWhere('device_id', $deviceId);

        // Identyfikator układu jest publiczny (kontrakt §2), więc sam
        // w sobie niczego nie dowodzi. Gdyby ktoś podszył się pod cudze
        // pudełko, wszedłby w jego numerację i nadpisał cudze przejazdy.
        if ($device !== null && $device->user_id !== $user->id) {
            return response()->json([
                'message' => __('To urządzenie należy do innego konta.'),
            ], Response::HTTP_FORBIDDEN);
        }

        $accepted = DB::transaction(function () use ($user, $data, $deviceId, $device): int {
            $this->zapiszUrzadzenie($user, $deviceId, $data, $device);

            return $this->zapiszPrzejazdy($user, $deviceId, $data);
        });

        return response()->json(['accepted_through' => $accepted]);
    }

    /**
     * Nieznane pudełko dopisuje się do konta właściciela tokena przy
     * pierwszej udanej wysyłce — bez osobnego parowania (kontrakt §2).
     * Znane odświeża to, co przydaje się przy zgłoszeniach „dziwne wyniki".
     *
     * @param  array<string, mixed>  $data
     */
    private function zapiszUrzadzenie(User $user, string $deviceId, array $data, ?Device $device): void
    {
        ($device ?? new Device)->forceFill([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'fw' => $data['fw'],
            'calibrated' => $data['calibrated'],
            'last_seen_at' => now(),
        ])->save();
    }

    /**
     * Zapisuje przejazdy po kolei i zwraca numer, do którego ciąg jest pełny.
     *
     * @param  array<string, mixed>  $data
     */
    private function zapiszPrzejazdy(User $user, string $deviceId, array $data): int
    {
        $accepted = $this->ostatniBezPrzerwy($deviceId);

        foreach ($this->posortowane($data['rides']) as $ride) {
            $seq = (int) $ride['seq'];

            // Dziura w numeracji: dalej nie idziemy, bo potwierdzenie numeru,
            // którego nie mamy, kasuje przejazd z urządzenia bezpowrotnie.
            if ($seq > $accepted + 1) {
                break;
            }

            $this->zapiszPrzejazd($user, $deviceId, $seq, $ride, $data);

            $accepted = max($accepted, $seq);
        }

        return $accepted;
    }

    /**
     * Przejazdy rosnąco po numerze.
     *
     * Urządzenie wysyła je już uporządkowane, ale serwer nie powinien na tym
     * polegać — kolejność decyduje o tym, co potwierdzimy.
     *
     * @return list<array<mixed>>
     */
    private function posortowane(mixed $rides): array
    {
        $lista = is_array($rides)
            ? array_values(array_filter($rides, is_array(...)))
            : [];

        usort($lista, fn (array $a, array $b): int => ((int) ($a['seq'] ?? 0)) <=> ((int) ($b['seq'] ?? 0)));

        return $lista;
    }

    /**
     * @param  array<mixed>  $ride
     * @param  array<string, mixed>  $data
     */
    private function zapiszPrzejazd(User $user, string $deviceId, int $seq, array $ride, array $data): void
    {
        $istniejacy = Ride::withTrashed()
            ->where('device_id', $deviceId)
            ->where('seq', $seq)
            ->first();

        // Skasowany w panelu zostaje skasowany. Kasowanie jest miękkie
        // właśnie po to, żeby przejazd nie wracał przy kolejnej wysyłce
        // (kontrakt §1) — ale liczy się jako przyjęty.
        if ($istniejacy?->trashed()) {
            return;
        }

        Ride::updateOrCreate(
            ['device_id' => $deviceId, 'seq' => $seq],
            [
                ...$ride,
                'seq' => $seq,
                'user_id' => $user->id,
                'fw' => $data['fw'],
                'calibrated' => $data['calibrated'],
            ],
        );
    }

    /**
     * Ostatni numer, przed którym nie ma przerwy w ciągu (kontrakt §3).
     *
     * Skasowane miękko wiersze liczą się jako obecne — inaczej dziura po
     * skasowanym przejeździe obniżałaby potwierdzenie na zawsze i urządzenie
     * dosyłałoby go w nieskończoność.
     *
     * Zwykle ciąg jest pełny, więc wystarczy porównać liczbę wierszy
     * z największym numerem; szukanie dziury odpala się tylko wtedy,
     * gdy coś poszło nie tak.
     */
    private function ostatniBezPrzerwy(string $deviceId): int
    {
        $zapytanie = fn () => Ride::withTrashed()->where('device_id', $deviceId);

        $najwyzszy = (int) $zapytanie()->max('seq');

        if ($najwyzszy === 0) {
            return 0;
        }

        if ($zapytanie()->count() === $najwyzszy) {
            return $najwyzszy;
        }

        $oczekiwany = 1;

        foreach ($zapytanie()->orderBy('seq')->pluck('seq') as $seq) {
            if ((int) $seq !== $oczekiwany) {
                break;
            }

            $oczekiwany++;
        }

        return $oczekiwany - 1;
    }
}
