<?php

namespace Tests\Feature\Api;

use App\Http\Requests\Api\V1\StoreRidesRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Reguły sprawdzane wprost, bez trasy — endpoint dochodzi w kolejnym kroku.
 *
 * Przesyłka w metodzie `poprawnaPrzesylka()` to dosłownie ta z kontraktu
 * telemetrii §3, razem z wartościami.
 */
class StoreRidesValidationTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $zmiany
     * @return array<string, mixed>
     */
    private function poprawnaPrzesylka(array $zmiany = []): array
    {
        return array_replace([
            'device_id' => 'a1b2c3d4e5f6',
            'fw' => '1.0.0',
            'calibrated' => true,
            'rides' => [[
                'seq' => 7,
                'recorded_at' => null,
                'duration_s' => 1832,
                'lean_left_deg' => 42.0,
                'lean_right_deg' => 38.0,
                'accel_g' => 0.75,
                'brake_g' => 0.50,
                'speed_kmh' => null,
            ]],
        ], $zmiany);
    }

    /**
     * @param  array<string, mixed>  $dane
     */
    private function przechodzi(array $dane): bool
    {
        return Validator::make($dane, (new StoreRidesRequest)->rules())->passes();
    }

    public function test_the_payload_from_the_contract_is_accepted(): void
    {
        $this->assertTrue($this->przechodzi($this->poprawnaPrzesylka()));
    }

    /**
     * Urządzenie wysyła pustą tablicę, żeby sprawdzić łączność (§3).
     */
    public function test_an_empty_ride_list_is_valid(): void
    {
        $this->assertTrue($this->przechodzi($this->poprawnaPrzesylka(['rides' => []])));
    }

    public function test_a_full_batch_of_ten_is_accepted_but_eleven_is_not(): void
    {
        $wzor = $this->poprawnaPrzesylka()['rides'][0];

        $dziesiec = [];
        foreach (range(1, 10) as $seq) {
            $dziesiec[] = [...$wzor, 'seq' => $seq];
        }

        $this->assertTrue($this->przechodzi($this->poprawnaPrzesylka(['rides' => $dziesiec])));
        $this->assertFalse($this->przechodzi($this->poprawnaPrzesylka([
            'rides' => [...$dziesiec, [...$wzor, 'seq' => 11]],
        ])));
    }

    /**
     * Uwaga: to jest poziom samych reguł. Wielkie litery są tu odrzucane,
     * ale w pełnym żądaniu nie dotrą do walidacji — `prepareForValidation()`
     * sprowadza identyfikator do małych liter wcześniej. Sprawdzimy to
     * końcem do końca razem z endpointem.
     */
    public function test_the_device_identifier_must_be_twelve_hex_characters(): void
    {
        foreach (['a1b2c3d4e5f', 'a1b2c3d4e5f6a', 'a1b2c3d4e5fg', '', 'A1B2C3D4E5F6'] as $bledny) {
            $this->assertFalse(
                $this->przechodzi($this->poprawnaPrzesylka(['device_id' => $bledny])),
                "identyfikator [{$bledny}] nie powinien przejść",
            );
        }
    }

    /**
     * Brak GPS-a to stan normalny: `recorded_at` i `speed_kmh` przychodzą
     * puste, ale klucze muszą być obecne.
     */
    public function test_null_time_and_speed_are_valid_but_the_keys_must_be_present(): void
    {
        $this->assertTrue($this->przechodzi($this->poprawnaPrzesylka()));

        foreach (['recorded_at', 'speed_kmh'] as $pole) {
            $przejazd = $this->poprawnaPrzesylka()['rides'][0];
            unset($przejazd[$pole]);

            $this->assertFalse(
                $this->przechodzi($this->poprawnaPrzesylka(['rides' => [$przejazd]])),
                "brak klucza [{$pole}] powinien zostać odrzucony",
            );
        }
    }

    public function test_measurements_are_required(): void
    {
        foreach (['seq', 'duration_s', 'lean_left_deg', 'lean_right_deg', 'accel_g', 'brake_g'] as $pole) {
            $przejazd = $this->poprawnaPrzesylka()['rides'][0];
            unset($przejazd[$pole]);

            $this->assertFalse(
                $this->przechodzi($this->poprawnaPrzesylka(['rides' => [$przejazd]])),
                "brak pola [{$pole}] powinien zostać odrzucony",
            );
        }
    }

    /**
     * Kontrakt §7: zakresy luźno. Przechył 70° jest fizycznie podejrzany,
     * ale ma zostać zapisany i oznaczony na stronie, a nie odbity — inaczej
     * przejazd wracałby z urządzenia bez końca.
     */
    public function test_physically_odd_values_are_still_accepted(): void
    {
        $this->assertTrue($this->przechodzi($this->poprawnaPrzesylka([
            'rides' => [[
                'seq' => 1,
                'recorded_at' => null,
                'duration_s' => 0,
                'lean_left_deg' => 89.9,
                'lean_right_deg' => 0.0,
                'accel_g' => 9.99,
                'brake_g' => -3.5,
                'speed_kmh' => 0.0,
            ]],
        ])));
    }

    /**
     * Jedyna granica liczbowa, jaką stawiamy, to pojemność kolumny.
     * Wartość, której nie da się zapisać, dałaby 500 zamiast 422.
     */
    public function test_values_too_large_for_their_column_are_rejected(): void
    {
        $zaDuze = [
            'lean_left_deg' => 1000.0,   // decimal(4,1)
            'accel_g' => 100.0,          // decimal(4,2)
            'speed_kmh' => 10000.0,      // decimal(5,1)
        ];

        foreach ($zaDuze as $pole => $wartosc) {
            $przejazd = [...$this->poprawnaPrzesylka()['rides'][0], $pole => $wartosc];

            $this->assertFalse(
                $this->przechodzi($this->poprawnaPrzesylka(['rides' => [$przejazd]])),
                "wartość [{$wartosc}] w polu [{$pole}] nie mieści się w kolumnie",
            );
        }
    }

    public function test_the_sequence_number_starts_at_one(): void
    {
        foreach ([0, -1] as $seq) {
            $przejazd = [...$this->poprawnaPrzesylka()['rides'][0], 'seq' => $seq];

            $this->assertFalse($this->przechodzi($this->poprawnaPrzesylka(['rides' => [$przejazd]])));
        }
    }

    public function test_firmware_and_calibration_are_required(): void
    {
        $this->assertFalse($this->przechodzi($this->poprawnaPrzesylka(['fw' => ''])));
        $this->assertFalse($this->przechodzi($this->poprawnaPrzesylka(['fw' => str_repeat('x', 17)])));
        $this->assertFalse($this->przechodzi($this->poprawnaPrzesylka(['calibrated' => 'może'])));
    }
}
