<?php

namespace App\Support;

/**
 * Zapis wartości pomiarowych w panelu — jedno miejsce dla wszystkich ekranów.
 *
 * Każdy pomiar pojawia się w kilku widokach naraz (pulpit, historia
 * przejazdów), więc formatowanie trzymamy tutaj. Inaczej po pierwszej
 * zmianie zasad część ekranów zostałaby przy starym zapisie.
 */
final class Pomiar
{
    /**
     * Brak pomiaru. Nie „0" — kontrakt telemetrii §3 mówi wprost, że `null`
     * znaczy „urządzenie nie umiało tego zmierzyć", a zero to co innego.
     */
    public const BRAK = '———';

    /**
     * Przechył w stopniach.
     *
     * Od wersji firmware'u wprowadzającej całkowite stopnie przechył
     * przychodzi bez części ułamkowej, a „42,0°" czytało się dziwnie.
     * Miejsce po przecinku pokazujemy więc tylko wtedy, gdy coś wnosi —
     * starsze wpisy z pomiarem 38,4° nadal wyglądają poprawnie.
     */
    public static function stopnie(?float $wartosc): string
    {
        if ($wartosc === null) {
            return self::BRAK;
        }

        $miejsca = fmod($wartosc, 1.0) === 0.0 ? 0 : 1;

        return number_format($wartosc, $miejsca, ',', ' ').'°';
    }

    /**
     * Przyspieszenie i hamowanie w g — zawsze dwa miejsca po przecinku.
     *
     * Tu ułamek jest treścią pomiaru: różnica między 0,75 g a 0,80 g
     * jest odczuwalna, więc zaokrąglanie nic by nie dało.
     */
    public static function przeciazenie(?float $wartosc): string
    {
        if ($wartosc === null) {
            return self::BRAK;
        }

        return number_format($wartosc, 2, ',', ' ').' g';
    }

    /**
     * Dystans ze śladu trasy.
     *
     * Poniżej kilometra w metrach, wyżej w kilometrach z jednym miejscem —
     * „0,2 km" mówi mniej niż „177 m", a „12 345 m" mniej niż „12,3 km".
     *
     * Sam pomiar jest zaniżony na zakrętach (cięciwa zamiast łuku), więc
     * dokładność poniżej metra i tak byłaby udawana.
     */
    public static function dystans(?int $metry): string
    {
        if ($metry === null) {
            return self::BRAK;
        }

        return $metry < 1000
            ? number_format($metry, 0, ',', ' ').' m'
            : number_format($metry / 1000, 1, ',', ' ').' km';
    }

    /**
     * Prędkość maksymalna w km/h, w pełnych kilometrach.
     */
    public static function predkosc(?float $wartosc): string
    {
        if ($wartosc === null) {
            return self::BRAK;
        }

        return number_format($wartosc, 0, ',', ' ').' km/h';
    }
}
