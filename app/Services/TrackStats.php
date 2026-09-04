<?php

namespace App\Services;

/**
 * Podsumowanie śladu liczone raz, przy przyjęciu.
 *
 * Dystans, liczba punktów, zakres czasu i prostokąt otaczający są potrzebne
 * na liście przejazdów; liczenie ich przy każdym wyświetleniu znaczyłoby
 * parsowanie pliku przy każdym odświeżeniu strony.
 */
class TrackStats
{
    /** Dystans trafia do unsignedInteger — więcej niż tyle metrów się nie zapisze. */
    private const MAX_DISTANCE_M = 4_294_967_295;

    /**
     * @param  list<list<array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}>>  $segments
     * @return array<string, int|float|null>
     */
    public function summarize(array $segments): array
    {
        $points = 0;
        $distance = 0.0;
        $maxLean = null;
        $minLat = $maxLat = $minLon = $maxLon = null;
        $first = $last = null;

        foreach ($segments as $segment) {
            $previous = null;

            foreach ($segment as $point) {
                $points++;

                $minLat = $minLat === null ? $point['lat'] : min($minLat, $point['lat']);
                $maxLat = $maxLat === null ? $point['lat'] : max($maxLat, $point['lat']);
                $minLon = $minLon === null ? $point['lon'] : min($minLon, $point['lon']);
                $maxLon = $maxLon === null ? $point['lon'] : max($maxLon, $point['lon']);

                // Przechył jest kierunkowy (+ w prawo), więc rekordem jest
                // największy co do modułu — z zachowanym znakiem.
                if ($point['lean'] !== null
                    && ($maxLean === null || abs($point['lean']) > abs($maxLean))) {
                    $maxLean = $point['lean'];
                }

                if ($point['at'] !== null) {
                    $first ??= $point['at'];
                    $last = $point['at'];
                }

                // Dystans TYLKO wewnątrz segmentu. Przez przerwę motocykl nie
                // przejechał po linii prostej — doliczenie jej zrobiłoby
                // z przejazdu tunelem jazdę „o 40 km dłuższą".
                if ($previous !== null) {
                    $distance += self::metry($previous, $point);
                }

                $previous = $point;
            }
        }

        return [
            'point_count' => $points,
            'segment_count' => count($segments),
            'distance_m' => (int) min(round($distance), self::MAX_DISTANCE_M),
            'started_at' => $first,
            'ended_at' => $last,
            'min_lat' => $minLat,
            'max_lat' => $maxLat,
            'min_lon' => $minLon,
            'max_lon' => $maxLon,
            'max_lean_deg' => $maxLean,
        ];
    }

    /**
     * Odległość między dwoma punktami w metrach.
     *
     * Publiczna, bo tej samej miary używa karta przejazdu do wyliczenia
     * prędkości na odcinku — dwa osobne wzory rozjechałyby się przy
     * pierwszej poprawce.
     *
     * @param  array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}  $a
     * @param  array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}  $b
     */
    public static function metry(array $a, array $b): float
    {
        // Płaska siatka lokalna wystarcza: odcinki mają najwyżej kilkaset metrów.
        $latM = 111_320.0;
        $lonM = $latM * cos(deg2rad($a['lat']));

        $dx = ($b['lon'] - $a['lon']) * $lonM;
        $dy = ($b['lat'] - $a['lat']) * $latM;

        return sqrt($dx * $dx + $dy * $dy);
    }
}
