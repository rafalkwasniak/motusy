<?php

namespace Tests\Unit;

use App\Models\Ride;
use Tests\TestCase;

class RideRecordedAtTest extends TestCase
{
    /**
     * Czas z GPS-a przychodzi w UTC, a panel ma pokazywać godzinę, o której
     * jazda naprawdę się skończyła — pierwszy przejazd z modułem GPS wyszedł
     * na ekranie o dwie godziny za wcześnie.
     */
    public function test_recorded_time_is_shown_in_the_display_timezone(): void
    {
        // 3 września 2026, 20:27:53 UTC — u nas 22:27:53 czasu letniego.
        $jazda = new Ride(['recorded_at' => 1788467273]);

        $this->assertSame('Europe/Warsaw', $jazda->recordedAt()->timezone->getName());
        $this->assertSame('2026-09-03 22:27:53', $jazda->recordedAt()->format('Y-m-d H:i:s'));
    }

    /**
     * Zimą różnica jest godzinna, więc przesunięcie nie może być zaszyte
     * na sztywno — strefa sama pilnuje czasu letniego.
     */
    public function test_winter_time_shifts_by_one_hour(): void
    {
        // 1 stycznia 2026, 12:00:00 UTC — u nas 13:00:00.
        $jazda = new Ride(['recorded_at' => 1767268800]);

        $this->assertSame('2026-01-01 13:00:00', $jazda->recordedAt()->format('Y-m-d H:i:s'));
    }

    /**
     * Bez pozycji z GPS-a urządzenie nie zna czasu i daty nie pokazujemy.
     */
    public function test_a_ride_without_gps_has_no_time(): void
    {
        $this->assertNull((new Ride(['recorded_at' => null]))->recordedAt());
    }
}
