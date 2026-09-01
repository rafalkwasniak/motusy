<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StoreRidesTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_ID = 'a1b2c3d4e5f6';

    /**
     * @param  array<int, array<string, mixed>>  $rides
     * @param  array<string, mixed>  $zmiany
     */
    private function wyslij(User $user, array $rides, array $zmiany = []): TestResponse
    {
        return $this->postJson('/api/v1/rides', array_replace([
            'device_id' => self::DEVICE_ID,
            'fw' => '1.0.0',
            'calibrated' => true,
            'rides' => $rides,
        ], $zmiany), [
            'Authorization' => 'Bearer '.$user->api_token,
            'User-Agent' => 'MotusyMotoBox/1.0.0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function przejazd(int $seq): array
    {
        return [
            'seq' => $seq,
            'recorded_at' => null,
            'duration_s' => 1832,
            'lean_left_deg' => 42.0,
            'lean_right_deg' => 38.0,
            'accel_g' => 0.75,
            'brake_g' => 0.50,
            'speed_kmh' => null,
        ];
    }

    // — cztery sprawdzenia z kontraktu §8 —

    /**
     * 1. Ten sam curl puszczony dwa razy daje `accepted_through: 1`
     *    i **jeden** wiersz w bazie.
     */
    public function test_sending_the_same_ride_twice_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(1)])
            ->assertOk()
            ->assertExactJson(['accepted_through' => 1]);

        $this->wyslij($user, [$this->przejazd(1)])
            ->assertOk()
            ->assertExactJson(['accepted_through' => 1]);

        $this->assertSame(1, Ride::withTrashed()->count());
    }

    /**
     * 2. Przesyłka z pustą tablicą odpowiada bieżącym numerem, nie błędem.
     */
    public function test_an_empty_batch_answers_with_the_current_number(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [])->assertOk()->assertExactJson(['accepted_through' => 0]);

        $this->wyslij($user, [$this->przejazd(1), $this->przejazd(2)]);

        $this->wyslij($user, [])->assertOk()->assertExactJson(['accepted_through' => 2]);
    }

    /**
     * 3. Przesyłka zaczynająca się od numeru wyższego niż stan bazy
     *    **nie podnosi** potwierdzenia — inaczej urządzenie skasowałoby
     *    przejazdy, których nigdy nie dostaliśmy.
     */
    public function test_a_gap_does_not_raise_the_confirmation(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(1)]);

        $this->wyslij($user, [$this->przejazd(5), $this->przejazd(6)])
            ->assertOk()
            ->assertExactJson(['accepted_through' => 1]);

        $this->assertSame(1, Ride::count());
    }

    /**
     * 4. Zły token daje 401 — po nim urządzenie przestaje próbować.
     */
    public function test_a_bad_token_is_rejected(): void
    {
        User::factory()->create();

        $this->postJson('/api/v1/rides', [
            'device_id' => self::DEVICE_ID,
            'fw' => '1.0.0',
            'calibrated' => true,
            'rides' => [$this->przejazd(1)],
        ], ['Authorization' => 'Bearer XFRS-34ST-YTS8'])->assertUnauthorized();

        $this->assertSame(0, Ride::count());
    }

    // — reszta zachowania z kontraktu —

    public function test_an_unknown_device_is_attached_to_the_token_owner(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(1)])->assertOk();

        $device = Device::firstWhere('device_id', self::DEVICE_ID);

        $this->assertNotNull($device);
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('motobox', $device->type);
    }

    public function test_each_shipment_refreshes_the_device_diagnostics(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [], ['fw' => '1.0.0', 'calibrated' => false])->assertOk();

        $device = Device::firstWhere('device_id', self::DEVICE_ID);
        $this->assertSame('1.0.0', $device->fw);
        $this->assertFalse($device->calibrated);
        $this->assertNotNull($device->last_seen_at);

        $this->wyslij($user, [], ['fw' => '1.1.0', 'calibrated' => true])->assertOk();

        $device->refresh();
        $this->assertSame('1.1.0', $device->fw);
        $this->assertTrue($device->calibrated);
    }

    public function test_a_batch_is_stored_in_order_and_confirmed_as_a_whole(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(1), $this->przejazd(2), $this->przejazd(3)])
            ->assertExactJson(['accepted_through' => 3]);

        $this->assertSame([1, 2, 3], Ride::orderBy('seq')->pluck('seq')->all());
    }

    /**
     * Urządzenie wysyła rosnąco, ale serwer nie powinien na tym polegać.
     */
    public function test_an_unsorted_batch_is_still_handled(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(3), $this->przejazd(1), $this->przejazd(2)])
            ->assertExactJson(['accepted_through' => 3]);
    }

    /**
     * Przejazd skasowany w panelu nie wraca przy kolejnej wysyłce, ale
     * liczy się jako przyjęty — inaczej urządzenie dosyłałoby go bez końca.
     */
    public function test_a_deleted_ride_is_neither_restored_nor_blocking(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(1), $this->przejazd(2)]);

        Ride::where('seq', 2)->first()->delete();

        $this->wyslij($user, [$this->przejazd(2), $this->przejazd(3)])
            ->assertOk()
            ->assertExactJson(['accepted_through' => 3]);

        $this->assertSoftDeleted('rides', ['device_id' => self::DEVICE_ID, 'seq' => 2]);
        $this->assertSame([1, 3], Ride::orderBy('seq')->pluck('seq')->all());
    }

    /**
     * Powtórka nadpisuje dane — urządzenie mogło doliczyć wartości
     * po ostatniej nieudanej wysyłce.
     */
    public function test_resending_a_known_ride_overwrites_its_values(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(1)]);

        $this->wyslij($user, [[...$this->przejazd(1), 'lean_left_deg' => 51.3, 'speed_kmh' => 187.0]])
            ->assertExactJson(['accepted_through' => 1]);

        $ride = Ride::firstWhere('seq', 1);
        $this->assertSame(51.3, $ride->lean_left_deg);
        $this->assertSame(187.0, $ride->speed_kmh);
    }

    public function test_a_device_belonging_to_another_account_is_refused(): void
    {
        $wlasciciel = User::factory()->create();
        $obcy = User::factory()->create();

        $this->wyslij($wlasciciel, [$this->przejazd(1)])->assertOk();

        $this->wyslij($obcy, [$this->przejazd(2)])->assertForbidden();

        $this->assertSame(1, Ride::count());
        $this->assertSame($wlasciciel->id, Device::firstWhere('device_id', self::DEVICE_ID)->user_id);
    }

    /**
     * Identyfikator wielkimi literami nie może założyć drugiego wpisu
     * dla tego samego pudełka.
     */
    public function test_an_uppercase_identifier_lands_on_the_same_device(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(1)])->assertOk();
        $this->wyslij($user, [$this->przejazd(2)], ['device_id' => 'A1B2C3D4E5F6'])
            ->assertOk()
            ->assertExactJson(['accepted_through' => 2]);

        $this->assertSame(1, Device::count());
    }

    public function test_a_malformed_payload_is_rejected_without_touching_the_database(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [[...$this->przejazd(1), 'accel_g' => 'szybko']])
            ->assertStatus(422);

        $this->assertSame(0, Ride::count());
        $this->assertSame(0, Device::count());
    }

    /**
     * Urządzenie nie ma parsera JSON i szuka w odpowiedzi dosłownie ciągu
     * `accepted_through`. Nie może paść w komunikacie błędu.
     */
    public function test_error_responses_never_mention_accepted_through(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [[...$this->przejazd(1), 'accel_g' => 'szybko']])
            ->assertDontSee('accepted_through');

        $this->postJson('/api/v1/rides', [], ['Authorization' => 'Bearer XFRS-34ST-YTS8'])
            ->assertDontSee('accepted_through');
    }

    public function test_rides_are_saved_for_the_token_owner(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user, [$this->przejazd(1)])->assertOk();

        $this->assertSame($user->id, Ride::first()->user_id);
    }
}
