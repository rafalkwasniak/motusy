<?php

namespace App\Support;

/**
 * Skala barw przechyłu — wspólna dla linii na mapie i dla wykresu.
 *
 * Obie rzeczy pokazują ten sam pomiar, więc muszą używać tej samej skali;
 * dwie osobne tablice kolorów rozjechałyby się przy pierwszej poprawce.
 *
 * Szarość na dole skali jest celowa: jazda na wprost nie jest wydarzeniem
 * i nie ma się świecić na czerwono. Czerwień rośnie dopiero tam, gdzie
 * motocykl faktycznie leży.
 */
final class Przechyl
{
    /**
     * Progi w stopniach (co do modułu), kolor i podpis do legendy.
     * Ostatni próg jest otwarty w górę.
     *
     * @var list<array{int, string, string}>
     */
    public const SKALA = [
        [10, '#71717a', 'do 10°'],
        [25, '#f87171', '10–25°'],
        [40, '#dc2626', '25–40°'],
        [PHP_INT_MAX, '#991b1b', 'powyżej 40°'],
    ];

    /**
     * Barwa dla danego przechyłu. Znak nie ma znaczenia — lewo i prawo są
     * tak samo mocne; kierunek widać na mapie z samego kształtu trasy.
     */
    public static function kolor(?int $lean): string
    {
        $kat = abs($lean ?? 0);

        foreach (self::SKALA as [$prog, $kolor, $podpis]) {
            if ($kat < $prog) {
                return $kolor;
            }
        }

        return self::SKALA[array_key_last(self::SKALA)][1];
    }

    /**
     * Legenda: lista par [kolor, podpis].
     *
     * @return list<array{string, string}>
     */
    public static function legenda(): array
    {
        return array_map(fn (array $poziom): array => [$poziom[1], $poziom[2]], self::SKALA);
    }
}
