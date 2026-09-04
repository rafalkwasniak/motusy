<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Ride;
use App\Models\RideTrack;
use App\Models\User;
use App\Services\TrackFormatException;
use App\Services\TrackParser;
use App\Services\TrackStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * `POST /api/v1/devices/{deviceId}/rides/{seq}/track` — docs/api-slad-trasy.md §2.
 *
 * Ślad jest **dodatkiem, nie zamiennikiem wyniku** (§1): jedzie osobnym
 * żądaniem i jego brak niczego nie blokuje.
 *
 * Kod odpowiedzi decyduje o losie pliku w pudełku, więc jest tu ważniejszy
 * niż zwykle. 413 i 422 kasują ślad z urządzenia bezpowrotnie, 5xx każe
 * ponawiać z rosnącym opóźnieniem. Pomyłka w którąkolwiek stronę kończy się
 * albo utratą danych, albo radiem budzonym co kilka minut aż do rozładowania
 * baterii.
 */
class RideTrackController extends Controller
{
    /** Zapas na przyszłe wersje formatu i gęstą zabudowę (kontrakt §3). */
    private const MAX_BYTES = 1_048_576;

    /** `seq` trafia do unsignedInteger — wyżej zapis wywróciłby zapytanie. */
    private const MAX_SEQ = 4_294_967_295;

    public function store(Request $request, string $deviceId, string $seq): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->tekstem($request)) {
            // 415 urządzenie traktuje jak awarię i ponawia (§2), więc czepiamy
            // się wyłącznie typu jawnie innego niż tekstowy. Brak nagłówka
            // przepuszczamy: gdyby przyszłe firmware przestało go wysyłać,
            // ostrzejsza reguła zakleszczyłaby pudełko w wiecznym ponawianiu.
            return $this->blad(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'Ciało żądania ma być tekstem.');
        }

        $body = $request->getContent();

        // Ciało jest tekstem, nie JSON-em, więc nie ma czego dać FormRequestowi.
        if ($body === '') {
            return $this->blad(Response::HTTP_UNPROCESSABLE_ENTITY, 'Puste ciało żądania.');
        }

        if (strlen($body) > self::MAX_BYTES) {
            return $this->blad(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'Ślad jest za duży.');
        }

        // Trasa przepuszcza same cyfry, ale liczba spoza zakresu kolumny
        // wywróciłaby zapis — a 500 kazałoby pudełku ponawiać coś, co nigdy
        // nie przejdzie.
        if ((float) $seq < 1 || (float) $seq > self::MAX_SEQ) {
            return $this->blad(Response::HTTP_UNPROCESSABLE_ENTITY, 'Zły numer przejazdu.');
        }

        $numer = (int) $seq;

        try {
            $parsed = app(TrackParser::class)->parse($body);
        } catch (TrackFormatException $e) {
            return $this->blad(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }

        if (strtolower($parsed['header']['dev']) !== $deviceId) {
            return $this->blad(Response::HTTP_UNPROCESSABLE_ENTITY, 'Nagłówek dev nie zgadza się z adresem.');
        }

        // Identyfikator układu jest publiczny (kontrakt telemetrii §2), więc
        // sam z siebie niczego nie dowodzi. Bez tej kontroli ktoś ze swoim
        // tokenem wszedłby w cudzą numerację — klucz idempotencji to samo
        // `(device_id, seq)`, więc nadpisałby cudzy ślad.
        $device = Device::firstWhere('device_id', $deviceId);

        if ($device !== null && $device->user_id !== $user->id) {
            return response()->json([
                'message' => __('To urządzenie należy do innego konta.'),
            ], Response::HTTP_FORBIDDEN);
        }

        $ride = Ride::withTrashed()
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->where('seq', $numer)
            ->first();

        if ($ride?->trashed()) {
            return $this->skasowany($deviceId, $numer);
        }

        $this->zapisz($user, $deviceId, $numer, $body, $ride, $parsed);

        return response()->json(['stored' => true]);
    }

    /**
     * Przejazd skasowany w panelu zostaje skasowany razem ze śladem.
     *
     * Odpowiadamy 200, żeby urządzenie przestało go dosyłać, ale nie
     * zapisujemy nic — inaczej ślad wracałby tylnymi drzwiami po tym, jak
     * właściciel wyrzucił przejazd z historii.
     */
    private function skasowany(string $deviceId, int $seq): JsonResponse
    {
        RideTrack::where('device_id', $deviceId)->where('seq', $seq)->delete();

        return response()->json(['stored' => true]);
    }

    /**
     * @param  array{header: array<string, string>, segments: list<list<array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}>>}  $parsed
     */
    private function zapisz(User $user, string $deviceId, int $seq, string $body, ?Ride $ride, array $parsed): void
    {
        $stats = app(TrackStats::class)->summarize($parsed['segments']);

        // Plik na dysku jest źródłem prawdy: statystyki da się z niego
        // przeliczyć od nowa, gdy parser się poprawi.
        $path = "{$user->id}/{$deviceId}/{$seq}.mmbt";
        Storage::disk('tracks')->put($path, $body);

        // `withTrashed`, bo indeks unikalny nie odróżnia skasowanych: bez tego
        // powtórka po skasowaniu uderzyłaby w klucz i oddała 500, po którym
        // urządzenie ponawia w nieskończoność.
        $track = RideTrack::withTrashed()->firstOrNew([
            'device_id' => $deviceId,
            'seq' => $seq,
        ]);

        $track->fill([
            'user_id' => $user->id,
            // Ślad bywa szybszy niż wynik przejazdu — wtedy `ride_id` zostaje
            // pusty i podepnie go kontroler przejazdów, gdy wynik dojdzie.
            'ride_id' => $ride?->id,
            'path' => $path,
            'bytes' => strlen($body),
            'format' => TrackParser::FORMAT,
            'fw' => $parsed['header']['fw'],
            'corridor_m' => (int) $parsed['header']['eps'],
            ...$stats,
        ]);

        // Tu docieramy tylko wtedy, gdy przejazd nie jest w koszu, więc
        // powtórna wysyłka ma prawo przywrócić ślad skasowany osobno.
        $track->deleted_at = null;

        $track->save();
    }

    /**
     * Czy ciało jest tekstem. Brak nagłówka uznajemy za tekst.
     */
    private function tekstem(Request $request): bool
    {
        $typ = $request->header('Content-Type');

        if (! is_string($typ) || trim($typ) === '') {
            return true;
        }

        return str_starts_with(strtolower(trim($typ)), 'text/');
    }

    /**
     * Odpowiedź błędu w tej samej kopercie co reszta API — sam kod jest
     * tym, na co patrzy urządzenie, ale treść trafia do logów właściciela.
     */
    private function blad(int $status, string $message): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
