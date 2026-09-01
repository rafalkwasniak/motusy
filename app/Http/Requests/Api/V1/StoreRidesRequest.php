<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Przesyłka z przejazdami — kontrakt telemetrii §3 i §7.
 *
 * Zasada z §7: **kształt sprawdzamy ostro, zakresy wartości luźno.**
 * Odrzucenie przesyłki kodem 422 nie kasuje jej z urządzenia — przejazd
 * zostaje w kolejce i wraca przy każdej kolejnej próbie, w kółko.
 * Podejrzanie duży przechył lepiej zapisać i oznaczyć na stronie niż odbić.
 *
 * Jedyne granice liczbowe, jakie tu stawiamy, wynikają z **pojemności
 * kolumn**, nie z fizyki. Wartość, której nie da się zapisać, i tak
 * wywróciłaby zapytanie — lepiej odpowiedzieć 422 niż 500.
 */
class StoreRidesRequest extends FormRequest
{
    /** Największe wartości mieszczące się w kolumnach tabeli `rides`. */
    private const MAX_LEAN = 999.9;          // decimal(4,1)

    private const MAX_G = 99.99;             // decimal(4,2)

    private const MAX_SPEED = 9999.9;        // decimal(5,1)

    private const MAX_UNSIGNED_INT = 4294967295;

    public function authorize(): bool
    {
        // Token sprawdza middleware `account.token`.
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'size:12', 'regex:/^[0-9a-f]{12}$/'],
            'fw' => ['required', 'string', 'max:16'],
            'calibrated' => ['required', 'boolean'],

            // Pusta tablica jest poprawna — urządzenie sprawdza w ten
            // sposób łączność (kontrakt §3).
            'rides' => ['present', 'array', 'max:10'],

            'rides.*.seq' => ['required', 'integer', 'min:1', 'max:'.self::MAX_UNSIGNED_INT],
            'rides.*.duration_s' => ['required', 'integer', 'min:0', 'max:'.self::MAX_UNSIGNED_INT],

            // `present` zamiast `required`: urządzenie zawsze wysyła te
            // klucze, ale ich wartością bywa null — brak GPS-a.
            'rides.*.recorded_at' => ['present', 'nullable', 'integer', 'min:0', 'max:'.self::MAX_UNSIGNED_INT],
            'rides.*.speed_kmh' => ['present', 'nullable', 'numeric', 'between:-'.self::MAX_SPEED.','.self::MAX_SPEED],

            'rides.*.lean_left_deg' => ['required', 'numeric', 'between:-'.self::MAX_LEAN.','.self::MAX_LEAN],
            'rides.*.lean_right_deg' => ['required', 'numeric', 'between:-'.self::MAX_LEAN.','.self::MAX_LEAN],
            'rides.*.accel_g' => ['required', 'numeric', 'between:-'.self::MAX_G.','.self::MAX_G],
            'rides.*.brake_g' => ['required', 'numeric', 'between:-'.self::MAX_G.','.self::MAX_G],
        ];
    }

    /**
     * Identyfikator układu bierzemy małymi literami.
     *
     * Firmware wysyła go tak czy inaczej, ale gdyby kiedyś przyszedł
     * wielkimi, powstałby **drugi wpis w `devices`** dla tego samego
     * pudełka — indeks unikalny rozróżnia wielkość liter.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('device_id'))) {
            $this->merge(['device_id' => strtolower($this->input('device_id'))]);
        }
    }
}
