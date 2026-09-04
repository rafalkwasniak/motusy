<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Ride;
use App\Models\RideTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * `POST /api/v1/devices/{deviceId}/rides/{seq}/track` — docs/api-slad-trasy.md §2.
 */
class StoreTrackTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_ID = '70041ddc6bc8';

    private const SEQ = 51;

    /** Ślad z kontraktu §7: cztery punkty w dwóch segmentach. */
    private const SLAD = "MMBT1\ndev=70041ddc6bc8\nfw=1.0.0\neps=8\nt0=1757001234\n"
        ."p0=1957648,5133528\n\n113,-182,5,12\n26,-3,1,-31\n-\n340,84,900,0\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tracks');
    }

    private function wyslij(
        ?User $user,
        string $body = self::SLAD,
        int $seq = self::SEQ,
        string $deviceId = self::DEVICE_ID,
        string $contentType = 'text/plain; charset=us-ascii',
    ): TestResponse {
        return $this->call(
            'POST',
            "/api/v1/devices/{$deviceId}/rides/{$seq}/track",
            [], [], [],
            [
                'CONTENT_TYPE' => $contentType,
                'HTTP_AUTHORIZATION' => 'Bearer '.($user?->api_token ?? 'zly-token'),
                'HTTP_USER_AGENT' => 'MotusyMotoBox/1.0.0',
            ],
            $body,
        );
    }

    private function przejazd(User $user, int $seq = self::SEQ): Ride
    {
        return Ride::factory()->create([
            'user_id' => $user->id,
            'device_id' => self::DEVICE_ID,
            'seq' => $seq,
        ]);
    }

    // — droga szczęśliwa —

    public function test_it_stores_a_track_and_counts_its_statistics(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user)
            ->assertOk()
            ->assertExactJson(['stored' => true]);

        $track = RideTrack::sole();

        $this->assertSame(4, $track->point_count);
        $this->assertSame(2, $track->segment_count);
        $this->assertSame(8, $track->corridor_m);
        $this->assertSame('MMBT1', $track->format);
        $this->assertSame('1.0.0', $track->fw);
        $this->assertSame(1757001234, $track->started_at);
        $this->assertSame(1757002140, $track->ended_at);
        $this->assertSame(-31, $track->max_lean_deg);

        // Przejazd jeszcze nie doszedł — i to jest stan normalny.
        $this->assertNull($track->ride_id);

        Storage::disk('tracks')->assertExists($track->path);
        $this->assertSame(self::SLAD, Storage::disk('tracks')->get($track->path));
    }

    /**
     * Dystans liczy się wyłącznie wewnątrz segmentów. Doliczenie przerwy
     * dorzuciłoby tu ~250 m przejechanych „po linii prostej".
     */
    public function test_the_distance_skips_the_gap(): void
    {
        $this->wyslij(User::factory()->create())->assertOk();

        $this->assertEqualsWithDelta(236, RideTrack::sole()->distance_m, 3);
    }

    public function test_the_track_belongs_to_the_owner_of_the_token(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user)->assertOk();

        $this->assertSame($user->id, RideTrack::sole()->user_id);
    }

    /**
     * Powtórka to sukces, nie konflikt — inaczej `updateOrCreate` uderzyłby
     * w indeks unikalny i oddał 500, po którym urządzenie ponawia bez końca.
     */
    public function test_sending_the_same_track_twice_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user)->assertOk();
        $this->wyslij($user)->assertOk();

        $this->assertSame(1, RideTrack::count());
    }

    /**
     * Ślad bywa szybszy niż wynik przejazdu, bo to dwa niezależne żądania
     * i o kolejności decyduje moment złapania sieci (kontrakt §2).
     */
    public function test_a_track_that_arrived_first_is_attached_when_the_ride_comes_in(): void
    {
        $user = User::factory()->create();

        $this->wyslij($user)->assertOk();
        $this->assertNull(RideTrack::sole()->ride_id);

        $this->postJson('/api/v1/rides', [
            'device_id' => self::DEVICE_ID,
            'fw' => '1.0.0',
            'calibrated' => true,
            'rides' => [[
                'seq' => self::SEQ,
                'recorded_at' => null,
                'duration_s' => 1832,
                'lean_left_deg' => 42.0,
                'lean_right_deg' => 38.0,
                'accel_g' => 0.75,
                'brake_g' => 0.50,
                'speed_kmh' => null,
            ]],
        ], ['Authorization' => 'Bearer '.$user->api_token])->assertOk();

        $this->assertSame(Ride::sole()->id, RideTrack::sole()->ride_id);
    }

    public function test_a_track_that_arrived_second_is_attached_right_away(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);

        $this->wyslij($user)->assertOk();

        $this->assertSame($ride->id, RideTrack::sole()->ride_id);
    }

    // — przejazd skasowany w panelu —

    /**
     * Skasowany przejazd nie wraca tylnymi drzwiami. Odpowiadamy 200, żeby
     * urządzenie przestało dosyłać ślad, ale nie zapisujemy nic.
     */
    public function test_a_track_of_a_deleted_ride_is_accepted_but_not_stored(): void
    {
        $user = User::factory()->create();
        $this->przejazd($user)->delete();

        $this->wyslij($user)
            ->assertOk()
            ->assertExactJson(['stored' => true]);

        $this->assertSame(0, RideTrack::count());
        $this->assertSame([], Storage::disk('tracks')->allFiles());
    }

    public function test_deleting_a_ride_that_already_has_a_track_takes_the_track_with_it(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);

        $this->wyslij($user)->assertOk();

        $ride->track->delete();
        $ride->delete();

        // Kolejna wysyłka tego samego śladu nie ma go przywrócić.
        $this->wyslij($user)->assertOk();

        $this->assertSame(0, RideTrack::count());
        $this->assertSame(1, RideTrack::withTrashed()->count());
    }

    // — cudze i błędne —

    /**
     * `device_id` jest publiczny (kontrakt telemetrii §2), więc sam z siebie
     * niczego nie dowodzi. Bez tej kontroli obcy token wszedłby w cudzą
     * numerację i nadpisał cudzy ślad.
     */
    public function test_someone_elses_device_is_refused(): void
    {
        $obcy = User::factory()->create();
        Device::factory()->create([
            'user_id' => User::factory()->create()->id,
            'device_id' => self::DEVICE_ID,
        ]);

        $this->wyslij($obcy)->assertForbidden();

        $this->assertSame(0, RideTrack::count());
    }

    public function test_a_bad_token_is_refused(): void
    {
        $this->wyslij(null)->assertUnauthorized();

        $this->assertSame(0, RideTrack::count());
    }

    public function test_an_unknown_format_version_is_rejected_as_permanent(): void
    {
        $this->wyslij(User::factory()->create(), "MMBT9\n\n")
            ->assertStatus(422);
    }

    public function test_an_empty_body_is_rejected(): void
    {
        $this->wyslij(User::factory()->create(), '')
            ->assertStatus(422);
    }

    public function test_a_header_device_that_disagrees_with_the_address_is_rejected(): void
    {
        $this->wyslij(User::factory()->create(), str_replace('70041ddc6bc8', 'aaaabbbbcccc', self::SLAD))
            ->assertStatus(422);
    }

    /**
     * 413 kasuje plik z urządzenia — i słusznie, bo ponowienie nic nie da.
     */
    public function test_a_track_above_one_megabyte_is_rejected(): void
    {
        $ogon = str_repeat("1,1,1,0\n", 150_000);

        $this->wyslij(User::factory()->create(), self::SLAD.$ogon)
            ->assertStatus(413);
    }

    /**
     * 415 urządzenie traktuje jak awarię i ponawia, więc czepiamy się
     * wyłącznie typu jawnie innego niż tekstowy.
     */
    public function test_a_non_text_content_type_is_refused(): void
    {
        $this->wyslij(User::factory()->create(), contentType: 'application/json')
            ->assertStatus(415);
    }

    public function test_a_missing_content_type_is_accepted(): void
    {
        $this->wyslij(User::factory()->create(), contentType: '')
            ->assertOk();
    }

    /**
     * Numer spoza zakresu kolumny wywróciłby zapis, a 500 kazałoby pudełku
     * ponawiać coś, co nigdy nie przejdzie.
     */
    public function test_a_sequence_number_outside_the_column_range_is_rejected(): void
    {
        $this->wyslij(User::factory()->create(), seq: 4_294_967_296)
            ->assertStatus(422);
    }

    /**
     * Ślad nie ma prawa zablokować wyniku przejazdu (kontrakt §1) — a wynik
     * potwierdza się osobnym endpointem, którego to żądanie nie dotyka.
     */
    public function test_a_broken_track_leaves_the_ride_untouched(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);

        $this->wyslij($user, "MMBT9\n\n")->assertStatus(422);

        $this->assertNotNull($ride->fresh());
    }
}
