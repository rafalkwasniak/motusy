<?php

namespace Tests\Unit;

use App\Services\TrackFormatException;
use App\Services\TrackParser;
use App\Services\TrackStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrackParserTest extends TestCase
{
    /**
     * Ślad z kontraktu §7. Odtwarza się na cztery punkty w dwóch segmentach,
     * w okolicach Piotrkowa.
     */
    private const SLAD = "MMBT1\ndev=70041ddc6bc8\nfw=1.0.0\neps=8\nt0=1757001234\n"
        ."p0=1957648,5133528\n\n113,-182,5,12\n26,-3,1,-31\n-\n340,84,900,0\n";

    private function parse(string $body): array
    {
        return (new TrackParser)->parse($body);
    }

    public function test_it_rebuilds_the_example_from_the_contract(): void
    {
        $parsed = $this->parse(self::SLAD);

        $this->assertSame('70041ddc6bc8', $parsed['header']['dev']);
        $this->assertSame('8', $parsed['header']['eps']);
        $this->assertCount(2, $parsed['segments']);
        $this->assertCount(3, $parsed['segments'][0]);
        $this->assertCount(1, $parsed['segments'][1]);
    }

    /**
     * Najdroższa pomyłka w tym formacie: `lon` jest pierwsze. Zamiana daje
     * ślad w Somalii, a nikt tego nie zauważa od razu.
     */
    public function test_longitude_comes_first(): void
    {
        $start = $this->parse(self::SLAD)['segments'][0][0];

        $this->assertEqualsWithDelta(19.57648, $start['lon'], 0.000005);
        $this->assertEqualsWithDelta(51.33528, $start['lat'], 0.000005);
    }

    public function test_increments_add_up_from_the_starting_point(): void
    {
        $drugi = $this->parse(self::SLAD)['segments'][0][1];

        $this->assertEqualsWithDelta(19.57761, $drugi['lon'], 0.000005);
        $this->assertEqualsWithDelta(51.33346, $drugi['lat'], 0.000005);
        $this->assertSame(1757001239, $drugi['at']);
        $this->assertSame(12, $drugi['lean']);
    }

    /**
     * Znacznik `-` mówi tylko tyle, że między punktami nie było jazdy.
     * Gdyby zerował sumowanie, punkt po przerwie wróciłby do `p0`.
     */
    public function test_the_gap_marker_does_not_reset_the_running_sum(): void
    {
        $poPrzerwie = $this->parse(self::SLAD)['segments'][1][0];

        $this->assertEqualsWithDelta(19.58127, $poPrzerwie['lon'], 0.000005);
        $this->assertEqualsWithDelta(51.33427, $poPrzerwie['lat'], 0.000005);
        $this->assertSame(1757002140, $poPrzerwie['at']);
    }

    /**
     * `t0=0` znaczy „urządzenie nie znało czasu" — i nie zna go **cały ślad**.
     *
     * Bez tego sumowanie `dt` robi z kolejnych punktów 1 stycznia 1970,
     * a taka data w pliku GPX myli programy do map bardziej niż jej brak.
     */
    public function test_an_unknown_start_time_leaves_every_point_without_time(): void
    {
        $body = str_replace('t0=1757001234', 't0=0', self::SLAD);

        foreach ($this->parse($body)['segments'] as $segment) {
            foreach ($segment as $point) {
                $this->assertNull($point['at']);
            }
        }
    }

    public function test_the_starting_point_has_no_lean(): void
    {
        // Przed punktem startowym nie ma odcinka, więc nie ma czego zmierzyć.
        $this->assertNull($this->parse(self::SLAD)['segments'][0][0]['lean']);
    }

    public function test_two_gap_markers_in_a_row_do_not_leave_an_empty_segment(): void
    {
        $body = str_replace("-\n340", "-\n-\n340", self::SLAD);

        $this->assertCount(2, $this->parse($body)['segments']);
    }

    /**
     * Kontrakt §8: format jest pozycyjny i piąte pole da się dołożyć bez
     * przebudowy. Serwer, który odbija taką linię kodem 422, kasuje ślad
     * z pudełka na zawsze — i to przy pierwszym nowszym firmwarze.
     */
    public function test_it_ignores_extra_fields_in_a_point_line(): void
    {
        $body = str_replace('113,-182,5,12', '113,-182,5,12,64', self::SLAD);

        $punkt = $this->parse($body)['segments'][0][1];

        $this->assertSame(12, $punkt['lean']);
        $this->assertEqualsWithDelta(19.57761, $punkt['lon'], 0.000005);
    }

    public function test_it_tolerates_windows_line_endings(): void
    {
        $parsed = $this->parse(str_replace("\n", "\r\n", self::SLAD));

        $this->assertCount(2, $parsed['segments']);
        $this->assertSame('1.0.0', $parsed['header']['fw']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function uszkodzoneSlady(): array
    {
        return [
            'nieznana wersja' => ["MMBT9\n\n"],
            'brak magii' => ["dev=70041ddc6bc8\n\n"],
            'brak pola naglowka' => ["MMBT1\ndev=70041ddc6bc8\nfw=1.0.0\neps=8\nt0=1\n\n"],
            'naglowek bez znaku rownosci' => ["MMBT1\ndev 70041ddc6bc8\n\n"],
            'p0 z jedna liczba' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=1\np0=1957648\n\n"],
            'p0 nieliczbowe' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=1\np0=abc,5133528\n\n"],
            'linia punktu z trzema polami' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=1\np0=1,2\n\n1,2,3\n"],
            'linia punktu z jednym polem' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=1\np0=1,2\n\n7\n"],
            'punkt nieliczbowy' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=1\np0=1,2\n\n1,2,x,4\n"],
            'wspolrzedne poza globem' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=1\np0=1,2\n\n0,99000000,1,0\n"],
            'przechyl poza zakresem' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=1\np0=1,2\n\n1,2,3,40000\n"],
            'eps nieliczbowe' => ["MMBT1\ndev=a\nfw=1\neps=osiem\nt0=1\np0=1,2\n\n"],
            'eps poza zakresem' => ["MMBT1\ndev=a\nfw=1\neps=99999\nt0=1\np0=1,2\n\n"],
            't0 ujemne' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=-5\np0=1,2\n\n"],
            'dt ujemne' => ["MMBT1\ndev=a\nfw=1\neps=8\nt0=100\np0=1,2\n\n1,2,-5,0\n"],
        ];
    }

    /**
     * Uszkodzony plik ma dać wyjątek, który kontroler zamieni na 422.
     * Cicha tolerancja zapisałaby bzdurę, a 500 kazałoby pudełku ponawiać
     * coś, co nigdy nie przejdzie.
     */
    #[DataProvider('uszkodzoneSlady')]
    public function test_it_rejects_a_broken_track(string $body): void
    {
        $this->expectException(TrackFormatException::class);

        $this->parse($body);
    }

    // — statystyki —

    public function test_it_summarizes_the_example(): void
    {
        $stats = (new TrackStats)->summarize($this->parse(self::SLAD)['segments']);

        $this->assertSame(4, $stats['point_count']);
        $this->assertSame(2, $stats['segment_count']);
        $this->assertSame(1757001234, $stats['started_at']);
        $this->assertSame(1757002140, $stats['ended_at']);

        // Rekordem jest przechył największy co do modułu, ze znakiem:
        // −31° bije +12°.
        $this->assertSame(-31, $stats['max_lean_deg']);

        $this->assertEqualsWithDelta(51.33343, $stats['min_lat'], 0.000005);
        $this->assertEqualsWithDelta(51.33528, $stats['max_lat'], 0.000005);
        $this->assertEqualsWithDelta(19.57648, $stats['min_lon'], 0.000005);
        $this->assertEqualsWithDelta(19.58127, $stats['max_lon'], 0.000005);
    }

    /**
     * Bez tego łatwo policzyć dystans przez całą przerwę i dostać przejazd
     * „o 40 km dłuższy", gdy ktoś przejechał tunelem.
     */
    public function test_it_does_not_count_distance_across_a_gap(): void
    {
        // Dwa punkty obok siebie, przerwa, i punkt oddalony o pół stopnia
        // długości geograficznej — czyli o kilkadziesiąt kilometrów.
        $body = "MMBT1\ndev=a\nfw=1\neps=8\nt0=100\np0=1957648,5133528\n\n"
              ."100,0,1,0\n-\n50000,0,60,0\n";

        $stats = (new TrackStats)->summarize($this->parse($body)['segments']);

        $this->assertSame(3, $stats['point_count']);
        $this->assertSame(2, $stats['segment_count']);

        // Sam pierwszy odcinek: 0,001° długości to ~70 m na tej szerokości.
        $this->assertLessThan(100, $stats['distance_m']);
    }

    public function test_an_unknown_time_leaves_the_range_empty(): void
    {
        $stats = (new TrackStats)->summarize(
            $this->parse(str_replace('t0=1757001234', 't0=0', self::SLAD))['segments'],
        );

        $this->assertNull($stats['started_at']);
        $this->assertNull($stats['ended_at']);
    }
}
