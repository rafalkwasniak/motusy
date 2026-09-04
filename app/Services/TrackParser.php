<?php

namespace App\Services;

/**
 * Rozkłada ślad w formacie MMBT1 na segmenty punktów — docs/api-slad-trasy.md §2.
 *
 * Bez zależności od HTTP: dzięki temu da się go sprawdzić na zwykłym napisie
 * i puścić jeszcze raz po wszystkich zapisanych plikach, gdyby kiedyś trzeba
 * było przeliczyć statystyki od nowa.
 *
 * Dwa miejsca, w których najłatwiej się pomylić — oba pilnowane testami:
 *
 *  1. **`lon` jest pierwsze.** Zamiana z `lat` daje ślad w Somalii.
 *  2. **Znacznik `-` nie zeruje sumowania.** Mówi tylko tyle, że między
 *     tymi punktami motocykl nie jechał, więc nie wolno rysować odcinka.
 */
class TrackParser
{
    public const FORMAT = 'MMBT1';

    /** Pola nagłówka, bez których śladu nie da się odtworzyć (kontrakt §2). */
    private const REQUIRED = ['dev', 'fw', 'eps', 't0', 'p0'];

    /** Granice, poza którymi współrzędna nie mieści się w kolumnie decimal(9,6). */
    private const MAX_LAT_E5 = 9_000_000;

    private const MAX_LON_E5 = 18_000_000;

    /** Przechył trafia do smallInteger; kontrakt przewiduje −60…60 stopni. */
    private const MAX_LEAN = 32_767;

    /** Szerokość korytarza trafia do unsignedSmallInteger; realnie to 8 m. */
    private const MAX_EPS = 65_535;

    /**
     * @return array{header: array<string, string>, segments: list<list<array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}>>}
     *
     * @throws TrackFormatException
     */
    public function parse(string $body): array
    {
        // Urządzenie kończy linie samym `\n`, ale plik przepisany ręcznie
        // albo przepuszczony przez Windows przyniesie `\r` — kosztuje jedną
        // instrukcję, a oszczędza 422 za nic.
        $lines = array_map(fn (string $line): string => rtrim($line, "\r"), explode("\n", $body));

        if (($lines[0] ?? '') !== self::FORMAT) {
            throw new TrackFormatException('nieznana wersja formatu sladu');
        }

        $i = 1;
        $header = $this->naglowek($lines, $i);

        [$lon, $lat] = $this->punktStartowy($header['p0']);

        // `eps` trafia do unsignedSmallInteger, `t0` do unsignedInteger.
        // Wartość spoza zakresu wywróciłaby zapis, a 500 kazałoby pudełku
        // ponawiać coś, co nigdy nie przejdzie.
        $eps = $this->liczba($header['eps'], 'eps');

        if ($eps < 0 || $eps > self::MAX_EPS) {
            throw new TrackFormatException("korytarz poza zakresem: {$eps}");
        }

        $czas = $this->liczba($header['t0'], 't0');

        if ($czas < 0) {
            throw new TrackFormatException("czas startu przed epoka: {$czas}");
        }

        // `t0=0` znaczy „urządzenie nie znało czasu" (kontrakt §2) — i wtedy
        // nie zna go **cały ślad**, nie tylko punkt startowy. Bez tej flagi
        // sumowanie `dt` robi z kolejnych punktów 1 stycznia 1970, a taka
        // data w pliku GPX myli programy do map bardziej niż jej brak.
        $czasZnany = $czas > 0;

        $segments = [];
        $current = [$this->punkt($lon, $lat, $czas, null, $czasZnany)];

        for ($n = count($lines); $i < $n; $i++) {
            $row = $lines[$i];

            if ($row === '') {
                continue;                   // końcowa nowa linia
            }

            if ($row === '-') {
                // „Podnieś ołówek”: nowy segment, ale sumowanie płynie dalej.
                $segments[] = $current;
                $current = [];

                continue;
            }

            $parts = explode(',', $row);

            // Cztery pola albo więcej. Kontrakt §8 zapowiada, że format jest
            // pozycyjny i piąte pole (choćby prędkość w punkcie) da się dołożyć
            // bez przebudowy — a odbicie takiej linii kodem 422 skasowałoby
            // ślad z pudełka na zawsze, przy pierwszym nowszym firmwarze.
            // Nadmiarowe pola pomijamy, dopóki serwer nie wie, co znaczą.
            if (count($parts) < 4) {
                throw new TrackFormatException("zla linia punktu: {$row}");
            }

            $dt = $this->liczba($parts[2], 'dt');

            // Kontrakt §2: `dt` jest nieujemne. Czas cofający się przed
            // epokę i tak nie zmieściłby się w kolumnie `started_at`.
            if ($dt < 0) {
                throw new TrackFormatException("czas plynie wstecz: {$dt}");
            }

            $lon += $this->liczba($parts[0], 'dlon');
            $lat += $this->liczba($parts[1], 'dlat');
            $czas += $dt;
            $lean = $this->liczba($parts[3], 'lean');

            if (abs($lean) > self::MAX_LEAN) {
                throw new TrackFormatException("przechyl poza zakresem: {$lean}");
            }

            $current[] = $this->punkt($lon, $lat, $czas, $lean, $czasZnany, $dt);
        }

        $segments[] = $current;

        return [
            'header' => $header,
            // Dwa znaczniki `-` pod rząd zostawiają pusty segment; wyrzucamy go,
            // żeby nie zawyżał licznika i nie robił pustego <trkseg> w GPX.
            'segments' => array_values(array_filter($segments)),
        ];
    }

    /**
     * Nagłówek `klucz=wartość`, po jednej parze na linię. Pusta linia go kończy.
     *
     * @param  list<string>  $lines
     * @param  int  $i  wskaźnik linii, przesuwany za pustą linię kończącą
     * @return array<string, string>
     */
    private function naglowek(array $lines, int &$i): array
    {
        $header = [];

        for ($n = count($lines); $i < $n && $lines[$i] !== ''; $i++) {
            if (! str_contains($lines[$i], '=')) {
                throw new TrackFormatException("zla linia naglowka: {$lines[$i]}");
            }

            [$key, $value] = explode('=', $lines[$i], 2);
            $header[$key] = $value;
        }

        $i++;

        foreach (self::REQUIRED as $required) {
            if (! isset($header[$required])) {
                throw new TrackFormatException("brak pola naglowka: {$required}");
            }
        }

        return $header;
    }

    /**
     * Punkt startowy z nagłówka `p0`, w jednostkach 1e-5 stopnia.
     *
     * @return array{int, int} kolejno lon i lat — **w tej kolejności**
     */
    private function punktStartowy(string $p0): array
    {
        $parts = explode(',', $p0);

        if (count($parts) !== 2) {
            throw new TrackFormatException("zle pole naglowka p0: {$p0}");
        }

        return [$this->liczba($parts[0], 'p0.lon'), $this->liczba($parts[1], 'p0.lat')];
    }

    /**
     * Liczba całkowita albo 422.
     *
     * `intval()` przepuściłby „abc” jako zero i zapisał ślad na Wyspach
     * Świętego Tomasza zamiast odbić uszkodzony plik.
     */
    private function liczba(string $value, string $pole): int
    {
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new TrackFormatException("pole {$pole} nie jest liczba: {$value}");
        }

        return (int) $value;
    }

    /**
     * @return array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}
     */
    private function punkt(int $lonE5, int $latE5, int $czas, ?int $lean, bool $czasZnany, int $dt = 0): array
    {
        // Współrzędna spoza globu oznacza uszkodzony plik — i tak nie
        // zmieściłaby się w kolumnie, a 500 kazałoby pudełku ponawiać
        // w kółko coś, co nigdy nie przejdzie.
        if (abs($latE5) > self::MAX_LAT_E5 || abs($lonE5) > self::MAX_LON_E5) {
            throw new TrackFormatException("wspolrzedne poza globem: {$lonE5},{$latE5}");
        }

        return [
            'lon' => $lonE5 / 1e5,
            'lat' => $latE5 / 1e5,
            'at' => $czasZnany ? $czas : null,

            // Sekundy od poprzedniego punktu, zachowane osobno od `at`.
            // Znacznik bezwzględny znika, gdy urządzenie nie znało czasu
            // (`t0=0`), ale odstęp między punktami jest znany zawsze — bez
            // niego nie dałoby się policzyć prędkości na odcinku.
            'dt' => $dt,

            'lean' => $lean,        // + = w prawo; null tylko w punkcie startowym
        ];
    }
}
