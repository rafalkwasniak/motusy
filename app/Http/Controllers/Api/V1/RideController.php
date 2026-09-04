<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRidesRequest;
use App\Models\Device;
use App\Models\Ride;
use App\Models\RideTrack;
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
        $accepted = $this->najwyzszyZapisany($deviceId);
        $poprzedni = null;

        foreach ($this->posortowane($data['rides']) as $ride) {
            $seq = (int) $ride['seq'];

            // Dziura **wewnątrz przesyłki**: dalej nie idziemy, bo
            // potwierdzenie numeru, którego nie zapisaliśmy, kasuje przejazd
            // z urządzenia bezpowrotnie (kontrakt §3).
            //
            // Ciągłości nie wymagamy natomiast wobec historii w bazie.
            // Licznik `seq` rośnie przez całe życie urządzenia i nie jest
            // zerowany, a pudełko wysyła zaległości od najstarszej, po dziesięć
            // naraz — więc najniższy numer w przesyłce to najstarszy przejazd,
            // jaki jeszcze ma. Wszystko poniżej albo już potwierdziliśmy, albo
            // nigdy do nas nie trafi. Wymaganie ciągu od jedynki zakleszczało
            // urządzenie: po wyczyszczeniu bazy serwer czekał na numer 1,
            // którego pudełko nie ma i nigdy nie przyśle.
            if ($poprzedni !== null && $seq !== $poprzedni + 1) {
                break;
            }

            $this->zapiszPrzejazd($user, $deviceId, $seq, $ride, $data);

            $accepted = max($accepted, $seq);
            $poprzedni = $seq;
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

        $zapisany = Ride::updateOrCreate(
            ['device_id' => $deviceId, 'seq' => $seq],
            [
                ...$ride,
                'seq' => $seq,
                'user_id' => $user->id,
                'fw' => $data['fw'],
                'calibrated' => $data['calibrated'],
            ],
        );

        $this->podepnijSlad($user, $deviceId, $seq, $zapisany);
    }

    /**
     * Ślad bywa szybszy niż wynik przejazdu — wysyłki są niezależne, a
     * o kolejności decyduje moment złapania sieci (docs/api-slad-trasy.md §2).
     * Ślad, który przyszedł pierwszy, czeka z pustym `ride_id`; tutaj dostaje
     * swój przejazd.
     *
     * Skasowane ślady zostają nietknięte: domyślny zasięg modelu je pomija.
     */
    private function podepnijSlad(User $user, string $deviceId, int $seq, Ride $ride): void
    {
        RideTrack::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->where('seq', $seq)
            ->whereNull('ride_id')
            ->update(['ride_id' => $ride->id]);
    }

    /**
     * Najwyższy numer, jaki mamy dla tego urządzenia — punkt wyjścia
     * potwierdzenia i zarazem odpowiedź na pustą przesyłkę (kontrakt §3).
     *
     * Skasowane miękko wiersze liczą się jako obecne. Kasowanie jest miękkie
     * właśnie po to, żeby przejazd nie wracał przy kolejnej wysyłce (§1) —
     * gdyby obniżało potwierdzenie, urządzenie dosyłałoby go bez końca.
     */
    private function najwyzszyZapisany(string $deviceId): int
    {
        return (int) Ride::withTrashed()->where('device_id', $deviceId)->max('seq');
    }
}
