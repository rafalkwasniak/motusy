<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\RideTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pobranie śladu jako GPX — docs/api-slad-trasy.md §6.
 */
class TrackGpxTest extends TestCase
{
    use RefreshDatabase;

    private const SLAD = "MMBT1\ndev=70041ddc6bc8\nfw=1.0.0\neps=8\nt0=1757001234\n"
        ."p0=1957648,5133528\n\n113,-182,5,12\n26,-3,1,-31\n-\n340,84,900,0\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tracks');
    }

    private function przejazdZeSladem(User $user, string $slad = self::SLAD): Ride
    {
        $ride = Ride::factory()->create([
            'user_id' => $user->id,
            'device_id' => '70041ddc6bc8',
            'seq' => 51,
        ]);

        Storage::disk('tracks')->put('slad.mmbt', $slad);

        RideTrack::factory()->create([
            'user_id' => $user->id,
            'device_id' => $ride->device_id,
            'seq' => $ride->seq,
            'ride_id' => $ride->id,
            'path' => 'slad.mmbt',
        ]);

        return $ride;
    }

    public function test_the_owner_downloads_the_track_as_gpx(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazdZeSladem($user);

        $response = $this->actingAs($user)->get("/rides/{$ride->id}/track.gpx");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/gpx+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="przejazd-51.gpx"');

        $gpx = $response->getContent();

        $this->assertStringContainsString('<trkpt lat="51.33528" lon="19.57648">', $gpx);
        $this->assertStringContainsString('<time>2025-09-04T15:53:54Z</time>', $gpx);
    }

    /**
     * Segment = `<trkseg>`. To jest dokładnie ta informacja, którą niesie
     * linia `-`: między segmentami motocykl nie przejechał po linii prostej,
     * więc mapa nie ma prawa jej narysować.
     */
    public function test_each_segment_becomes_its_own_trkseg(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazdZeSladem($user);

        $gpx = $this->actingAs($user)->get("/rides/{$ride->id}/track.gpx")->getContent();

        $this->assertSame(2, substr_count($gpx, '<trkseg>'));
        $this->assertSame(1, substr_count($gpx, '<trk>'));
        $this->assertSame(4, substr_count($gpx, '<trkpt'));
    }

    /**
     * Data 1970 w pliku GPX myli programy do map bardziej niż brak czasu.
     */
    public function test_points_without_a_known_time_have_no_time_tag(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazdZeSladem($user, str_replace('t0=1757001234', 't0=0', self::SLAD));

        $gpx = $this->actingAs($user)->get("/rides/{$ride->id}/track.gpx")->getContent();

        $this->assertStringNotContainsString('<time>', $gpx);
        $this->assertSame(4, substr_count($gpx, '<trkpt'));
    }

    /**
     * Ślady są prywatne (kontrakt §1), więc cudzy przejazd ma wyglądać
     * na nieistniejący — 404, nie 403.
     */
    public function test_someone_elses_track_is_invisible(): void
    {
        $ride = $this->przejazdZeSladem(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get("/rides/{$ride->id}/track.gpx")
            ->assertNotFound();
    }

    public function test_a_ride_without_a_track_gives_a_404(): void
    {
        $user = User::factory()->create();
        $ride = Ride::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/rides/{$ride->id}/track.gpx")
            ->assertNotFound();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $ride = $this->przejazdZeSladem(User::factory()->create());

        $this->get("/rides/{$ride->id}/track.gpx")->assertRedirect('/login');
    }
}
