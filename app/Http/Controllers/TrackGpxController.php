<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Services\TrackParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pobranie śladu jako GPX — docs/api-slad-trasy.md §6.
 *
 * Trasa stoi w `routes/web.php`, pod zwykłą sesją, a nie w API: link ma być
 * do klikania w panelu, a przeglądarka nie ma tokena urządzenia. Cudzy
 * przejazd wygląda tu jak nieistniejący.
 *
 * Ten sam kontroler obsługuje publiczny wariant `p/{token}/track.gpx`, żeby
 * odbiorca udostępnionego linku widział dokładnie to samo co właściciel.
 * Tam sesji nie ma i rolę poświadczenia gra token w adresie.
 */
class TrackGpxController extends Controller
{
    public function __invoke(Request $request, Ride $ride): Response
    {
        // Publiczny link niesie `share_token` w adresie i to on jest
        // poświadczeniem — sesji nie ma tu kogo pytać o właściciela.
        // 404, nie 403 — właściciel cudzego przejazdu nie ma się dowiedzieć,
        // że ten przejazd w ogóle istnieje.
        abort_unless(
            $request->routeIs('rides.shared.track.gpx')
                || $ride->user_id === $request->user()?->id,
            404,
        );

        $track = $ride->track()->firstOrFail();

        $parsed = app(TrackParser::class)->parse($track->contents());

        return response($this->gpx($ride, $parsed['segments']), 200, [
            'Content-Type' => 'application/gpx+xml',
            'Content-Disposition' => "attachment; filename=\"przejazd-{$ride->seq}.gpx\"",
        ]);
    }

    /**
     * @param  list<list<array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}>>  $segments
     */
    private function gpx(Ride $ride, array $segments): string
    {
        $nazwa = htmlspecialchars(
            __('Przejazd :seq — :device', ['seq' => $ride->seq, 'device' => $ride->deviceName()]),
            ENT_XML1,
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<gpx version="1.1" creator="Motusy Moto Box" '
              .'xmlns="http://www.topografix.com/GPX/1/1">'."\n";
        $xml .= "<trk><name>{$nazwa}</name>\n";

        // Każdy segment osobno. To jest dokładnie ta informacja, którą niesie
        // linia `-`: między segmentami motocykl nie przejechał po linii
        // prostej, więc mapa nie ma prawa jej narysować.
        foreach ($segments as $segment) {
            $xml .= "<trkseg>\n";

            foreach ($segment as $point) {
                $xml .= sprintf('<trkpt lat="%.5f" lon="%.5f">', $point['lat'], $point['lon']);

                // Czas nieznany zapisujemy jako BRAK <time>, nie jako rok 1970
                // — data 1970 myli programy do map bardziej niż jej brak.
                if ($point['at'] !== null) {
                    $xml .= '<time>'.gmdate('Y-m-d\TH:i:s\Z', $point['at']).'</time>';
                }

                $xml .= "</trkpt>\n";
            }

            $xml .= "</trkseg>\n";
        }

        return $xml."</trk></gpx>\n";
    }
}
