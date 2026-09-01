<?php

namespace Tests\Unit;

use App\Support\Pomiar;
use PHPUnit\Framework\TestCase;

class PomiarTest extends TestCase
{
    /**
     * Od wersji firmware'u z całkowitymi stopniami przechył przychodzi bez
     * części ułamkowej i „42,0°" czytało się dziwnie.
     */
    public function test_whole_degrees_are_written_without_a_decimal(): void
    {
        $this->assertSame('42°', Pomiar::stopnie(42.0));
        $this->assertSame('38°', Pomiar::stopnie(38.0));
        $this->assertSame('0°', Pomiar::stopnie(0.0));
    }

    /**
     * Starsze wpisy mają pomiar z jednym miejscem po przecinku i mają
     * pozostać czytelne — zaokrąglanie ich do pełnych stopni gubiłoby pomiar.
     */
    public function test_fractional_degrees_keep_their_precision(): void
    {
        $this->assertSame('38,4°', Pomiar::stopnie(38.4));
        $this->assertSame('7,5°', Pomiar::stopnie(7.5));
    }

    /**
     * Przy przeciążeniach ułamek jest treścią pomiaru, więc zostaje zawsze.
     */
    public function test_g_forces_always_keep_two_decimals(): void
    {
        $this->assertSame('0,75 g', Pomiar::przeciazenie(0.75));
        $this->assertSame('0,50 g', Pomiar::przeciazenie(0.5));
        $this->assertSame('1,00 g', Pomiar::przeciazenie(1.0));
    }

    public function test_speed_is_written_in_whole_kilometres(): void
    {
        $this->assertSame('187 km/h', Pomiar::predkosc(187.0));
        $this->assertSame('188 km/h', Pomiar::predkosc(187.6));
    }

    /**
     * Brak pomiaru to nie zero (kontrakt telemetrii §3).
     */
    public function test_a_missing_measurement_is_never_shown_as_zero(): void
    {
        foreach ([Pomiar::stopnie(null), Pomiar::przeciazenie(null), Pomiar::predkosc(null)] as $zapis) {
            $this->assertSame(Pomiar::BRAK, $zapis);
            $this->assertStringNotContainsString('0', $zapis);
        }
    }
}
