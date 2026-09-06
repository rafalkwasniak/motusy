<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\RideTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Karta jednego przejazdu — pomiary, mapa śladu i wykres przechyłu.
 */
class RideShowTest extends TestCase
{
    use RefreshDatabase;

    /** Ślad z kontraktu §7: cztery punkty, dwa segmenty, przechyły 12, −31 i 0. */
    private const SLAD = "MMBT1\ndev=70041ddc6bc8\nfw=1.0.0\neps=8\nt0=1757001234\n"
        ."p0=1957648,5133528\n\n113,-182,5,12\n26,-3,1,-31\n-\n340,84,900,0\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tracks');
    }

    private function przejazd(User $user, bool $zeSladem = true, string $slad = self::SLAD): Ride
    {
        $ride = Ride::factory()->for($user)->create([
            'device_id' => '70041ddc6bc8',
            'seq' => 62,
            'lean_left_deg' => 31.0,
            'speed_kmh' => 9.0,
        ]);

        if ($zeSladem) {
            Storage::disk('tracks')->put('slad.mmbt', $slad);

            RideTrack::factory()->for($user)->create([
                'device_id' => $ride->device_id,
                'seq' => $ride->seq,
                'ride_id' => $ride->id,
                'path' => 'slad.mmbt',
                'distance_m' => 236,
                'point_count' => 4,
                'segment_count' => 2,
            ]);
        }

        return $ride;
    }

    public function test_the_card_shows_the_measurements_of_one_ride(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);

        $this->actingAs($user)
            ->get("/rides/{$ride->id}")
            ->assertOk()
            ->assertSee('31°')
            ->assertSee('9 km/h');
    }

    /**
     * Hałas jest szóstym pomiarem przejazdu i stoi w tej samej siatce.
     */
    public function test_the_card_shows_the_noise_measurement(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);
        $ride->update(['max_noise_db' => 108.4, 'noise_at_speed_kmh' => 62]);

        $this->actingAs($user)
            ->get("/rides/{$ride->id}")
            ->assertOk()
            ->assertSee('108,4 dB')
            ->assertSee('Rekord hałasu padł przy 62 km/h.');
    }

    /**
     * Obcięty pomiar jest zaniżony, więc liczba idzie ze znakiem „≥" —
     * podanie jej wprost byłoby podaniem wartości, o której wiemy, że jest
     * za mała (docs/api-halas-implementacja-laravel.md §6).
     */
    public function test_a_clipped_noise_measurement_is_shown_as_at_least(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);
        $ride->update(['max_noise_db' => 126.4, 'noise_clipped' => 812]);

        $this->actingAs($user)
            ->get("/rides/{$ride->id}")
            ->assertOk()
            ->assertSee('≥ 126,4 dB')
            ->assertSee('prawdziwy szczyt był głośniejszy', false);
    }

    /**
     * Brak pomiaru to nie cisza. Mikrofon doszedł do urządzenia we wrześniu
     * 2026, a i później może paść — a urządzenie nie pokazuje tej wartości
     * na własnym ekranie, więc panel jest jedynym miejscem, gdzie awaria
     * wyjdzie na jaw (§5.1).
     */
    public function test_a_ride_without_noise_shows_a_dash_not_zero(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);

        $this->actingAs($user)
            ->get("/rides/{$ride->id}")
            ->assertOk()
            ->assertSee('———')
            ->assertDontSee('0,0 dB');
    }

    /**
     * Mapa dostaje linie pocięte na odcinki jednej barwy — to jest jedyny
     * powód, dla którego format niesie `lean` przy każdym punkcie.
     */
    public function test_the_map_gets_the_track_coloured_by_lean(): void
    {
        $user = User::factory()->create();

        $mapa = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $this->przejazd($user)])
            ->instance()
            ->mapa();

        // Dwa segmenty: pierwszy ma dwa odcinki (12° i −31° wpadają w różne
        // progi skali), drugi ani jednego, bo składa się z jednego punktu.
        $this->assertCount(2, $mapa['linie']);
        $this->assertSame('#f87171', $mapa['linie'][0]['kolor']);
        $this->assertSame('#dc2626', $mapa['linie'][1]['kolor']);

        // Piotrków, nie Somalia.
        $this->assertEqualsWithDelta(51.33528, $mapa['start'][0], 0.00001);
        $this->assertEqualsWithDelta(19.57648, $mapa['start'][1], 0.00001);
    }

    /**
     * Odcinek po przerwie nie ma prawa być narysowany: między segmentami
     * motocykl nie przejechał po linii prostej.
     */
    public function test_the_gap_is_not_drawn(): void
    {
        $user = User::factory()->create();

        $mapa = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $this->przejazd($user)])
            ->instance()
            ->mapa();

        foreach ($mapa['linie'] as $linia) {
            foreach ($linia['punkty'] as $punkt) {
                // Punkt zza przerwy (51.33427) nie pojawia się w żadnej linii.
                $this->assertNotEqualsWithDelta(51.33427, $punkt[0], 0.00001);
            }
        }
    }

    public function test_the_chart_scales_to_the_strongest_lean(): void
    {
        $user = User::factory()->create();

        $wykres = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $this->przejazd($user)])
            ->instance()
            ->wykres();

        // Trzy punkty z pomiarem przechyłu; punkt startowy go nie ma.
        $this->assertCount(3, $wykres['slupki']);

        // Skala zaokrągla się w górę do dziesiątek, ale nie schodzi poniżej 20°,
        // żeby spokojna jazda nie wyglądała dramatycznie.
        $this->assertSame(40, $wykres['maks']);
    }

    public function test_a_ride_without_a_track_says_so_instead_of_showing_a_map(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user, zeSladem: false);

        $this->actingAs($user)
            ->get("/rides/{$ride->id}")
            ->assertOk()
            ->assertSee(__('Ten przejazd nie ma śladu'))
            ->assertDontSee('mapa-sladu');
    }

    /**
     * Ślad bez znanego czasu rozkłada wykres po kolejności punktów, a nie
     * po osi czasu — inaczej wszystkie słupki wylądowałyby na sobie.
     */
    public function test_a_track_without_time_falls_back_to_point_order(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user, slad: str_replace('t0=1757001234', 't0=0', self::SLAD));

        $wykres = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $ride])
            ->instance()
            ->wykres();

        // Oś obejmuje **wszystkie** cztery punkty śladu, także startowy, który
        // przechyłu nie ma — bo tę samą oś dzieli wykres prędkości. Pierwszy
        // słupek wypada więc na jednej trzeciej, a nie w zerze.
        $this->assertEqualsWithDelta(1 / 3, $wykres['slupki'][0]['x'], 0.0001);
        $this->assertSame(1.0, $wykres['slupki'][2]['x']);
    }

    /**
     * Prędkości nie ma w śladzie (kontrakt §8), więc liczymy ją z odległości
     * i `dt`. Pierwszy odcinek to 113 i −182 jednostek 1e-5 stopnia w 5 sekund,
     * czyli około 217 m — a to daje mniej więcej 156 km/h.
     */
    public function test_the_speed_is_derived_from_distance_and_time(): void
    {
        $user = User::factory()->create();

        $predkosci = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $this->przejazd($user)])
            ->instance()
            ->predkosci();

        $this->assertCount(1, $predkosci['linie']);
        $this->assertEqualsWithDelta(156, $predkosci['linie'][0][0]['kmh'], 3);

        // Pomiar obowiązuje na **całym** przedziale, z którego powstał, więc
        // pierwszy schodek zaczyna się w zerze osi, a nie dopiero w punkcie,
        // w którym przedział się kończy. Bez tego rysunek zostawał pusty
        // przez cały czas postoju, choć wartość dla niego istnieje.
        $this->assertSame(0.0, $predkosci['linie'][0][0]['od']);
        $this->assertGreaterThan(0.0, $predkosci['linie'][0][0]['do']);
    }

    /**
     * Przez przerwę w śladzie nikt nie jechał, więc linia prędkości ma się
     * na niej urwać, a nie przeskoczyć na drugą stronę.
     */
    public function test_the_speed_line_breaks_on_a_gap(): void
    {
        $user = User::factory()->create();
        $slad = "MMBT1\ndev=70041ddc6bc8\nfw=1.0.0\neps=8\nt0=1757001234\n"
              ."p0=1957648,5133528\n\n50,0,5,0\n50,0,5,0\n-\n5000,0,10,0\n50,0,5,0\n50,0,5,0\n";

        $predkosci = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $this->przejazd($user, slad: $slad)])
            ->instance()
            ->predkosci();

        // Dwie osobne linie, nie jedna przeciągnięta przez przerwę.
        $this->assertCount(2, $predkosci['linie']);
    }

    /**
     * Skala musi objąć także pomiar z GPS-a, inaczej kreska odniesienia
     * wypadłaby poza rysunkiem dokładnie wtedy, gdy najbardziej się przydaje.
     */
    public function test_the_speed_scale_covers_the_gps_reading(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user);
        $ride->update(['speed_kmh' => 187.0]);

        $predkosci = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $ride->fresh()])
            ->instance()
            ->predkosci();

        $this->assertSame(187.0, $predkosci['gps']);
        $this->assertGreaterThanOrEqual(187, $predkosci['maks']);
    }

    /**
     * Wyliczenia mogą być poprawne, a rysunek i tak pusty.
     *
     * `@php` postawione **w środku atrybutu** rozbija kompilację Blade'a:
     * sąsiednie `{{ }}` zostają w wyniku dosłownie, przeglądarka dostaje
     * listę punktów, której nie umie odczytać, i po linii nie ma śladu.
     * Testy na samych metodach tego nie widzą — trzeba spojrzeć w HTML.
     */
    public function test_the_charts_render_real_coordinates(): void
    {
        $user = User::factory()->create();

        $html = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $this->przejazd($user)])
            ->html();

        $this->assertMatchesRegularExpression('/points="\d[\d.,\s]*"/', $html);

        // Strona przechyłu stoi przy krańcach osi pionowej, nie w podpisie
        // nad rysunkiem — tam czytało się jak „przechył w górę".
        $this->assertStringContainsString(__('prawo'), $html);
        $this->assertStringContainsString(__('lewo'), $html);

        // Nieskompilowany Blade w wyniku znaczy, że coś w szablonie rozbiło
        // kolejność podstawień — niezależnie od tego, gdzie.
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('raw_block', $html);
    }

    /**
     * Wykres czyta się jak mapa, z lotu ptaka: motocykl jedzie od lewej
     * krawędzi rysunku do prawej, więc jego **lewa** strona wypada u góry.
     * Odwrotnie słupek uciekał w stronę przeciwną niż zakręt na mapie obok.
     *
     * Kontrakt śladu §2 liczy przechył w prawo dodatnio, a w SVG `y` rośnie
     * w dół — więc dodatni przechył musi dać `y2` **większe** od osi zerowej.
     */
    public function test_the_lean_chart_puts_the_left_side_up(): void
    {
        $user = User::factory()->create();

        $html = Livewire::actingAs($user)
            ->test('pages::rides.show', ['ride' => $this->przejazd($user)])
            ->html();

        // Skala: 31° zaokrąglone w górę do 40°, słupek sięga 92 jednostek.
        // Przechył 12° w prawo — pod oś; 31° w lewo — nad nią.
        $this->assertStringContainsString('y2="'.round(100 + 12 / 40 * 92, 2).'"', $html);
        $this->assertStringContainsString('y2="'.round(100 - 31 / 40 * 92, 2).'"', $html);
    }

    public function test_someone_elses_ride_is_invisible(): void
    {
        $ride = $this->przejazd(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get("/rides/{$ride->id}")
            ->assertNotFound();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $ride = $this->przejazd(User::factory()->create());

        $this->get("/rides/{$ride->id}")->assertRedirect('/login');
    }

    public function test_the_history_links_every_row_to_its_card(): void
    {
        $user = User::factory()->create();
        $ride = $this->przejazd($user, zeSladem: false);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSee(route('rides.show', $ride), escape: false);
    }
}
