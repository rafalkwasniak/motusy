@props(['typ', 'rozmiar' => 24])

{{--
    Piktogramy pięciu mierzonych parametrów i śladu trasy, rysowane w tej samej konwencji
    co <x-moto-box-drawing>: rysunek techniczny, proste zakończenia, cienka
    linia odniesienia pod grubym obrysem. Gotowe zestawy (Heroicons, Lucide)
    odpadły — nie mają ani kąta przechyłu, ani hamowania, a ich zaokrąglony
    rysunek kłóci się z ostrym językiem strony.

    Kolor bierze się z `currentColor`, więc ikona chodzi za tekstem obok.
    Rozmiar podajemy atrybutem SVG, nie klasą Tailwinda — dzięki temu ikona
    nie potrzebuje niczego, czego nie ma w zbudowanym arkuszu stylów.

    Poniżej 20 px linia odniesienia znika w antyaliasingu i zostaje sam
    położony słupek — w nagłówku tabeli (11 px) rysunek się nie broni.
--}}
@php
    // Każdy wpis to [ścieżka, grubość, krycie]. Krycie oddziela obrys
    // właściwy od linii pomocniczych, tak jak na rysunku pudełka.
    $sciezki = match ($typ) {
        'lewo' => [
            ['M4 20 H20', 1.25, 0.5],
            ['M12 20 V6', 1.25, 0.45],
            ['M12 20 L4.5 8', 2.25, 1],
        ],
        'prawo' => [
            ['M4 20 H20', 1.25, 0.5],
            ['M12 20 V6', 1.25, 0.45],
            ['M12 20 L19.5 8', 2.25, 1],
        ],
        'przyspieszenie' => [
            ['M7 12 H19', 2.25, 1],
            ['M15 8 L19 12 L15 16', 2.25, 1],
            ['M2 7 H8', 1.25, 0.55],
            ['M2 17 H8', 1.25, 0.55],
        ],
        'hamowanie' => [
            ['M19.5 5 V19', 2.25, 1],
            ['M4 12 H15', 2.25, 1],
            ['M11 8 L15 12 L11 16', 2.25, 1],
        ],
        'predkosc' => [
            ['M3.5 18 A 8.5 8.5 0 0 1 20.5 18', 2.25, 1],
            ['M12 18 L18 12', 2.25, 1],
            ['M12 18 H20.5', 1.25, 0.45],
        ],
        // Ślad trasy stoi w tabeli przy numerze przejazdu, czyli przy tekście
        // o wysokości 14 px — dlatego bez linii pomocniczej, która przy tym
        // rozmiarze znika w antyaliasingu.
        //
        // Serpentyna, a nie łamana w górę: ta druga czyta się jak wykres
        // giełdowy. Kwadraciki na końcach są stąd, co plakietka w logo,
        // i mówią, gdzie trasa się zaczyna, a gdzie kończy.
        'slad' => [
            ['M5.5 20 C 5.5 15, 11.5 15, 11.5 10.5 S 14 5.5, 18.5 5', 2.25, 1],
            ['M4 18.5 h3 v3 h-3 z', 1.5, 1],
            ['M17 3.5 h3 v3 h-3 z', 1.5, 1],
        ],
    };

    $opisy = [
        'lewo' => 'Przechył w lewo',
        'prawo' => 'Przechył w prawo',
        'przyspieszenie' => 'Przyspieszenie',
        'hamowanie' => 'Hamowanie',
        'predkosc' => 'Prędkość maksymalna',
        'slad' => 'Ślad trasy',
    ];
@endphp

<svg
    width="{{ $rozmiar }}"
    height="{{ $rozmiar }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-linecap="butt"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-label="{{ __($opisy[$typ]) }}"
    {{ $attributes }}
>
    @foreach ($sciezki as [$d, $grubosc, $krycie])
        <path d="{{ $d }}" stroke-width="{{ $grubosc }}" opacity="{{ $krycie }}" />
    @endforeach
</svg>
