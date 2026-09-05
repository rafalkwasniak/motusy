<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\RideTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Publiczny podgląd przejazdu — link z tokenem, oglądany bez konta.
 *
 * Poświadczeniem jest sam adres: `rides.share_token` ma 128 bitów losowości,
 * a właściciel decyduje o dostępie tym, komu link wyśle.
 */
class RideSharedTest extends TestCase
{
    use RefreshDatabase;

    /** Ten sam ślad co w RideShowTest: cztery punkty, dwa segmenty. */
    private const SLAD = "MMBT1\ndev=70041ddc6bc8\nfw=1.0.0\neps=8\nt0=1757001234\n"
        ."p0=1957648,5133528\n\n113,-182,5,12\n26,-3,1,-31\n-\n340,84,900,0\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tracks');
    }

    private function przejazd(User $user, bool $zeSladem = true): Ride
    {
        $ride = Ride::factory()->for($user)->create([
            'device_id' => '70041ddc6bc8',
            'seq' => 70,
            'lean_left_deg' => 31.0,
            'speed_kmh' => 9.0,
        ]);

        if ($zeSladem) {
            Storage::disk('tracks')->put('slad.mmbt', self::SLAD);

            RideTrack::factory()->for($user)->create([
                'device_id' => $ride->device_id,
                'seq' => $ride->seq,
                'ride_id' => $ride->id,
                'path' => 'slad.mmbt',
            ]);
        }

        return $ride;
    }

    public function test_a_guest_sees_the_ride_under_the_share_link(): void
    {
        $ride = $this->przejazd(User::factory()->create());

        $this->get("/p/{$ride->share_token}")
            ->assertOk()
            ->assertSee('Przejazd #70')
            ->assertSee('31°')
            ->assertSee('9 km/h');
    }

    /**
     * Ten sam przejazd bez tokena w adresie zostaje prywatny — publiczna
     * trasa nie ma prawa otwierać furtki do `/rides/{id}`.
     */
    public function test_the_private_address_still_needs_a_login(): void
    {
        $ride = $this->przejazd(User::factory()->create());

        $this->get("/rides/{$ride->id}")->assertRedirect('/login');
    }

    public function test_a_wrong_token_is_a_missing_page(): void
    {
        $this->przejazd(User::factory()->create());

        $this->get('/p/'.str_repeat('a', 32))->assertNotFound();
    }

    /**
     * Odbiorca linku widzi dokładnie to samo co właściciel, z GPX-em
     * włącznie — taka była decyzja, gdy udostępnianie powstawało.
     */
    public function test_a_guest_downloads_the_track_under_the_share_link(): void
    {
        $ride = $this->przejazd(User::factory()->create());

        $this->get("/p/{$ride->share_token}/track.gpx")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/gpx+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="przejazd-70.gpx"');
    }

    /**
     * Gość ogląda kartę w obudowie bez panelu — pasek boczny sięga po
     * `auth()->user()->displayName()` i na pustym użytkowniku by się wywalił.
     */
    public function test_the_public_page_has_no_panel_chrome(): void
    {
        $ride = $this->przejazd(User::factory()->create());

        $this->get("/p/{$ride->share_token}")
            ->assertOk()
            ->assertSee('Przejazd udostępniony linkiem')
            ->assertSee('noindex, nofollow', false)
            ->assertDontSee('Wszystkie przejazdy')
            ->assertDontSee('Moje urządzenia');
    }

    /**
     * Przycisk kopiowania jest narzędziem właściciela: gość ma link
     * w pasku adresu, a token nie ma po co wracać do niego drugi raz.
     */
    public function test_only_the_owner_gets_the_copy_button(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);

        $this->actingAs($user)
            ->get("/rides/{$ride->id}")
            ->assertOk()
            ->assertSee('Kopiuj link')
            ->assertSee($ride->share_token, false);

        $this->get("/p/{$ride->share_token}")
            ->assertOk()
            ->assertDontSee('Kopiuj link');
    }

    /**
     * Przejazd bez śladu też da się wysłać linkiem — pomiary są treścią
     * samą w sobie, a przycisk kopiowania nie może znikać razem z GPX-em.
     */
    public function test_a_ride_without_a_track_is_shareable_too(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user, zeSladem: false);

        $this->actingAs($user)
            ->get("/rides/{$ride->id}")
            ->assertOk()
            ->assertSee('Kopiuj link');

        $this->get("/p/{$ride->share_token}")
            ->assertOk()
            ->assertSee('Przejazd #70');
    }

    public function test_every_ride_gets_its_own_token(): void
    {
        $user = User::factory()->create();

        $pierwszy = Ride::factory()->for($user)->create(['seq' => 1]);
        $drugi = Ride::factory()->for($user)->create(['seq' => 2]);

        $this->assertSame(32, strlen($pierwszy->share_token));
        $this->assertNotSame($pierwszy->share_token, $drugi->share_token);
    }

    /**
     * Token nie jest polem przesyłki: pudełko nie ma prawa go ustawić ani
     * nadpisać przez `updateOrCreate` z kontraktu telemetrii §7.
     */
    public function test_the_token_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $ride = Ride::factory()->for($user)->create(['seq' => 3]);
        $wlasny = $ride->share_token;

        $ride->fill(['share_token' => 'podstawiony'])->save();

        $this->assertSame($wlasny, $ride->fresh()->share_token);
    }
}
