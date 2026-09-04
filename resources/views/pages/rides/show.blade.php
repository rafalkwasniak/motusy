<?php

use App\Models\Ride;
use App\Services\TrackParser;
use App\Services\TrackStats;
use App\Support\Pomiar;
use App\Support\Przechyl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Przejazd')] class extends Component {
    public Ride $ride;

    public function mount(Ride $ride): void
    {
        // Przejazdy i ślady są prywatne (docs/api-slad-trasy.md §1), więc
        // cudzy ma wyglądać na nieistniejący — 404, nie 403.
        abort_unless($ride->user_id === Auth::id(), 404);

        $this->ride = $ride;
    }

    /**
     * Ślad rozłożony na punkty. Plik czytamy i parsujemy **raz na żądanie**,
     * bo korzysta z niego i mapa, i wykres przechyłu.
     *
     * @return list<list<array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}>>
     */
    #[Computed]
    public function segmenty(): array
    {
        $track = $this->ride->track;

        return $track === null
            ? []
            : app(TrackParser::class)->parse($track->contents())['segments'];
    }

    /**
     * Dane dla mapy: linie pocięte na odcinki jednego koloru.
     *
     * Kolorowanie kątem jest jedynym powodem, dla którego format niesie
     * `lean` przy każdym punkcie — bez tego wystarczyłby sam GPX.
     *
     * @return array{linie: list<array{kolor: string, punkty: list<array{float, float}>}>, start: array{float, float}, koniec: array{float, float}, granice: list<array{float, float}>}|null
     */
    #[Computed]
    public function mapa(): ?array
    {
        $track = $this->ride->track;

        if ($track === null || $this->segmenty === []) {
            return null;
        }

        $linie = [];

        foreach ($this->segmenty as $segment) {
            $poprzedni = null;
            $biezaca = null;

            foreach ($segment as $punkt) {
                if ($poprzedni !== null) {
                    // Przechył punktu opisuje odcinek **kończący się** w nim
                    // (kontrakt §2), więc barwa idzie z punktu docelowego.
                    $kolor = Przechyl::kolor($punkt['lean']);

                    if ($biezaca === null || $biezaca['kolor'] !== $kolor) {
                        if ($biezaca !== null) {
                            $linie[] = $biezaca;
                        }

                        // Odcinki jednej barwy scalamy w jedną linię —
                        // przy trzech tysiącach punktów osobna linia na
                        // każdy odcinek zatkałaby przeglądarkę.
                        $biezaca = ['kolor' => $kolor, 'punkty' => [[$poprzedni['lat'], $poprzedni['lon']]]];
                    }

                    $biezaca['punkty'][] = [$punkt['lat'], $punkt['lon']];
                }

                $poprzedni = $punkt;
            }

            if ($biezaca !== null) {
                $linie[] = $biezaca;
            }
        }

        $pierwszySegment = $this->segmenty[0];
        $ostatniSegment = $this->segmenty[count($this->segmenty) - 1];
        $pierwszy = $pierwszySegment[0];
        $ostatni = $ostatniSegment[count($ostatniSegment) - 1];

        return [
            'linie' => $linie,
            'start' => [$pierwszy['lat'], $pierwszy['lon']],
            'koniec' => [$ostatni['lat'], $ostatni['lon']],
            // Prostokąt otaczający jest policzony przy przyjęciu śladu,
            // więc mapa nie musi go szukać po punktach.
            'granice' => [
                [(float) $track->min_lat, (float) $track->min_lon],
                [(float) $track->max_lat, (float) $track->max_lon],
            ],
        ];
    }

    /**
     * Oś pozioma — wspólna dla obu rysunków.
     *
     * Wspólna, bo stoją jeden pod drugim: osobne osie znaczyłyby, że ten sam
     * moment przejazdu wypada w każdym z nich gdzie indziej, a wtedy zestawienie
     * przechyłu z prędkością nic nie mówi.
     *
     * Idzie po czasie, gdy urządzenie go znało — postój zostawia wtedy
     * uczciwą przerwę zamiast udawać równe tempo. Bez czasu zostaje kolejność
     * punktów i taki też jest podpis, żeby nikt nie brał jej za oś czasu.
     *
     * @return array{poCzasie: bool, start: int, rozpietosc: int, od: string, do: string}|null
     */
    #[Computed]
    public function os(): ?array
    {
        $punkty = $this->segmenty === [] ? [] : array_merge(...$this->segmenty);
        $ostatni = count($punkty) - 1;

        // Jeden punkt to nie przebieg — nie ma czego rozkładać na osi.
        if ($ostatni < 1) {
            return null;
        }

        $poCzasie = $punkty[0]['at'] !== null
            && $punkty[$ostatni]['at'] !== null
            && $punkty[$ostatni]['at'] > $punkty[0]['at'];

        return [
            'poCzasie' => $poCzasie,
            'start' => $poCzasie ? $punkty[0]['at'] : 0,
            'rozpietosc' => $poCzasie ? $punkty[$ostatni]['at'] - $punkty[0]['at'] : $ostatni,
            'od' => $this->podpisOsi($punkty[0], 1, $poCzasie),
            'do' => $this->podpisOsi($punkty[$ostatni], $ostatni + 1, $poCzasie),
        ];
    }

    /**
     * Słupki wykresu przechyłu: pozycja 0…1 wzdłuż przejazdu, kąt i barwa.
     *
     * @return array{slupki: list<array{x: float, lean: int, kolor: string}>, maks: int}|null
     */
    #[Computed]
    public function wykres(): ?array
    {
        if ($this->os === null) {
            return null;
        }

        $slupki = [];
        $numer = 0;

        foreach ($this->segmenty as $segment) {
            foreach ($segment as $punkt) {
                // Punkt startowy nie ma przechyłu — przed nim nie ma odcinka.
                if ($punkt['lean'] !== null) {
                    $slupki[] = [
                        'x' => $this->pozycja($punkt, $numer),
                        'lean' => (int) $punkt['lean'],
                        'kolor' => Przechyl::kolor($punkt['lean']),
                    ];
                }

                $numer++;
            }
        }

        if ($slupki === []) {
            return null;
        }

        $slupki = $this->przerzedz($slupki);

        // Skala zaokrągla się w górę do dziesiątek i nie schodzi poniżej 20°,
        // żeby spokojna jazda nie wyglądała dramatycznie.
        $maks = max(20, (int) (ceil(max(array_map(fn (array $s): int => abs($s['lean']), $slupki)) / 10) * 10));

        return ['slupki' => $slupki, 'maks' => $maks];
    }

    /**
     * Przebieg prędkości — **wyliczony**, nie zmierzony.
     *
     * Ślad nie niesie prędkości w punkcie: kontrakt §8 mówi wprost, że
     * urządzenie jej nie wysyła, a piąte pole dałoby się dołożyć, gdyby było
     * potrzebne. Do czasu, aż się pojawi, bierzemy odległość między punktami
     * podzieloną przez `dt`. To średnia z odcinka, a nie odczyt: na zakrętach
     * zaniżona (cięciwa zamiast łuku), a między rzadkimi punktami wygładzona.
     *
     * Dlatego rysujemy obok kreskę z prędkością maksymalną z GPS-a — ta jest
     * zmierzona i pokazuje, jak daleko od niej jest wyliczenie.
     *
     * Każdy pomiar dostaje **oba końce przedziału**, z którego pochodzi, więc
     * rysunek jest schodkiem, a nie łamaną. Łamana kłamała: wartość liczoną
     * z siedemdziesięciu czterech sekund stawiała w jednym punkcie na końcu
     * tego czasu i zostawiała pusty rysunek na całej jego długości.
     *
     * @return array{linie: list<list<array{od: float, do: float, kmh: float}>>, maks: int, gps: float|null}|null
     */
    #[Computed]
    public function predkosci(): ?array
    {
        if ($this->os === null) {
            return null;
        }

        $linie = [];
        $numer = 0;
        $szczyt = 0.0;

        foreach ($this->segmenty as $segment) {
            $linia = [];

            foreach ($segment as $i => $punkt) {
                // Pierwszy punkt segmentu nie ma z czym się porównać, a `dt`
                // zerowe dałoby dzielenie przez zero.
                if ($i > 0 && $punkt['dt'] > 0) {
                    $kmh = round(TrackStats::metry($segment[$i - 1], $punkt) / $punkt['dt'] * 3.6, 1);

                    $linia[] = [
                        'od' => $this->pozycja($segment[$i - 1], $numer - 1),
                        'do' => $this->pozycja($punkt, $numer),
                        'kmh' => $kmh,
                    ];

                    $szczyt = max($szczyt, $kmh);
                }

                $numer++;
            }

            // Przerwa w śladzie przerywa linię: przez tunel czy postój nikt nie
            // jechał, więc łączenie segmentów rysowałoby przebieg, którego nie było.
            if ($linia !== []) {
                $linie[] = $linia;
            }
        }

        if ($linie === []) {
            return null;
        }

        $gps = $this->ride->speed_kmh;

        return [
            'linie' => $linie,
            // Skala obejmuje także pomiar z GPS-a, inaczej kreska odniesienia
            // wypadłaby poza rysunkiem dokładnie wtedy, gdy jest najciekawsza.
            'maks' => max(20, (int) (ceil(max($szczyt, $gps ?? 0) / 10) * 10)),
            'gps' => $gps,
        ];
    }

    /**
     * Pozycja punktu na osi poziomej, w zakresie 0…1.
     *
     * @param  array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}  $punkt
     * @param  int  $numer  kolejność punktu w całym śladzie, licząc od zera
     */
    private function pozycja(array $punkt, int $numer): float
    {
        $os = $this->os;

        return round(
            $os['poCzasie']
                ? ($punkt['at'] - $os['start']) / $os['rozpietosc']
                : $numer / $os['rozpietosc'],
            5,
        );
    }

    /**
     * Podpis końca osi: godzina, gdy urządzenie znało czas, a inaczej numer
     * punktu — żeby było widać, że oś nie jest wtedy osią czasu.
     *
     * @param  array{lon: float, lat: float, at: int|null, dt: int, lean: int|null}  $punkt
     */
    private function podpisOsi(array $punkt, int $numer, bool $poCzasie): string
    {
        return $poCzasie
            ? Carbon::createFromTimestamp($punkt['at'], config('app.display_timezone'))->format('H:i:s')
            : __('punkt :nr', ['nr' => $numer]);
    }

    /**
     * Sześciogodzinny przejazd to ~3600 punktów, czyli tyle samo elementów
     * w SVG. Zbijamy je do sześciuset kubełków, zostawiając w każdym pomiar
     * **najmocniejszy** — bo to on jest treścią wykresu.
     *
     * @param  list<array{x: float, lean: int, kolor: string}>  $slupki
     * @return list<array{x: float, lean: int, kolor: string}>
     */
    private function przerzedz(array $slupki): array
    {
        $limit = 600;

        if (count($slupki) <= $limit) {
            return $slupki;
        }

        $kubelki = [];

        foreach ($slupki as $slupek) {
            $klucz = (int) floor($slupek['x'] * ($limit - 1));

            if (! isset($kubelki[$klucz]) || abs($slupek['lean']) > abs($kubelki[$klucz]['lean'])) {
                $kubelki[$klucz] = $slupek;
            }
        }

        ksort($kubelki);

        return array_values($kubelki);
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:link :href="route('rides.index')" wire:navigate class="text-sm">
            {{ __('← Wszystkie przejazdy') }}
        </flux:link>

        <flux:heading size="xl" level="1" class="mt-2">
            {{ __('Przejazd #:seq', ['seq' => $ride->seq]) }}
        </flux:heading>

        <flux:subheading size="lg" class="mb-6">
            {{ $ride->deviceName() }} · {{ $ride->durationForHumans() }}
            @if ($ride->recordedAt())
                · {{ $ride->recordedAt()->translatedFormat('j F Y, H:i') }}
            @endif
        </flux:subheading>

        <flux:separator variant="subtle" />
    </div>

    <x-pomiary-siatka :pomiary="[
        ['lewo', 'Przechył w lewo', Pomiar::stopnie($ride->lean_left_deg)],
        ['prawo', 'Przechył w prawo', Pomiar::stopnie($ride->lean_right_deg)],
        ['przyspieszenie', 'Przyspieszenie', Pomiar::przeciazenie($ride->accel_g)],
        ['hamowanie', 'Hamowanie', Pomiar::przeciazenie($ride->brake_g)],
        ['predkosc', 'Prędkość maksymalna', Pomiar::predkosc($ride->speed_kmh)],
    ]" />

    @if ($ride->track === null)
        <div class="mt-10">
            <x-empty-state :heading="__('Ten przejazd nie ma śladu')">
                {{ __('Zapis trasy jest w urządzeniu osobną opcją i domyślnie jest wyłączony. Przejazdy sprzed wersji firmware\'u ze śladem nie mają go i mieć nie będą.') }}
            </x-empty-state>
        </div>
    @else
        <div class="mt-10">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                <flux:heading size="lg">{{ __('Ślad trasy') }}</flux:heading>

                <flux:button
                    :href="route('rides.track.gpx', $ride)"
                    size="sm"
                    variant="filled"
                    icon="arrow-down-tray"
                >
                    {{ __('Pobierz GPX') }}
                </flux:button>
            </div>

            {{-- Mapa nie może dostać wysokości klasą Tailwinda: nowa klasa
                 wymagałaby przebudowy frontu, której na tym hoście nie
                 wykonujemy (CLAUDE.md §3). Atrybut `style` działa od razu.

                 Podkład odbarwiamy filtrem, zamiast brać gotowe kafelki
                 monochromatyczne: CARTO i Stamen wymagają dziś klucza API,
                 a OSM jest bez klucza. Filtr obejmuje wyłącznie warstwę
                 kafelków — linia trasy leży w innej warstwie i zachowuje
                 swoje barwy. W trybie ciemnym dokładamy inwersję. --}}
            <style>
                #mapa-sladu .leaflet-tile-pane { filter: grayscale(1) contrast(0.9) brightness(1.05); }
                {{-- Inwersja niepełna: przy `invert(1)` biel podkładu staje się
                     czernią bez dna i numery domów giną. 0.92 zostawia ciemną
                     szarość, w której rysunek mapy nadal widać. --}}
                .dark #mapa-sladu .leaflet-tile-pane { filter: grayscale(1) invert(0.92) contrast(0.95); }
                #mapa-sladu .leaflet-control-attribution { font-size: 10px; }

                {{-- Znacznik końca jest wypełniony atramentem, czyli w trybie
                     ciemnym znika w tle. Własność CSS bije atrybut `fill`
                     w SVG, więc wystarczy jedna reguła zamiast przerysowywania
                     mapy przy zmianie motywu. --}}
                .dark #mapa-sladu .znacznik-koniec { fill: #e4e4e7; }
            </style>

            <div class="border border-zinc-200 dark:border-neutral-700">
                <div id="mapa-sladu" style="height: 460px; width: 100%; background: transparent;"></div>
            </div>

            <x-przechyl-legenda class="mt-3" />

            @if ($this->wykres || $this->predkosci)
                <div class="mt-8">
                    <flux:heading size="lg">{{ __('Przebieg przejazdu') }}</flux:heading>

                    <flux:text size="sm" class="mt-1 mb-4">
                        {{ __('Oba rysunki mają wspólną oś poziomą, więc ten sam moment jazdy wypada w nich w tym samym miejscu.') }}
                    </flux:text>

                    <div class="border border-zinc-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        @if ($this->wykres)
                            <div class="font-mono text-[11px] tracking-wider text-zinc-500 uppercase">
                                {{ __('Przechył — barwa według siły') }}
                            </div>

                            <div class="mt-2 flex gap-3">
                                {{-- Oś pionowa siedzi w HTML-u, nie w SVG: rysunek
                                     jest rozciągany w poziomie (`preserveAspectRatio`
                                     wyłączone), więc każda litera w środku byłaby
                                     rozjechana razem z nim. --}}
                                <div
                                    class="flex w-14 shrink-0 flex-col justify-between text-right font-mono text-[11px] text-zinc-500 dark:text-zinc-400"
                                    style="height: 180px"
                                    aria-hidden="true"
                                >
                                    {{-- Strona stoi na osi, a nie w podpisie nad
                                         rysunkiem: „w górę w prawo" czytało się
                                         tak, jakby motocykl przechylał się w górę.
                                         Napis przy krańcu osi nie zostawia na to
                                         miejsca — u góry jest prawa strona, u dołu
                                         lewa, i widać to bez tłumaczenia. --}}
                                    <div>
                                        <div>{{ __('prawo') }}</div>
                                        <div>{{ $this->wykres['maks'] }}°</div>
                                    </div>

                                    <span>0°</span>

                                    <div>
                                        <div>{{ $this->wykres['maks'] }}°</div>
                                        <div>{{ __('lewo') }}</div>
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    {{-- Rysunek techniczny, nie wykres z biblioteki:
                                         słupek od osi zerowej na każdy pomiar.
                                         `non-scaling-stroke` trzyma grubość kreski
                                         przy rozciąganiu w poziomie. --}}
                                    <svg
                                        viewBox="0 0 1000 200"
                                        preserveAspectRatio="none"
                                        class="w-full"
                                        style="height: 180px"
                                        role="img"
                                        aria-label="{{ __('Przechył wzdłuż przejazdu, od :min° do :max°', ['min' => -$this->wykres['maks'], 'max' => $this->wykres['maks']]) }}"
                                    >
                                        {{-- Linie pomocnicze na połowie skali, jak
                                             podziałka na rysunku pudełka. --}}
                                        @foreach ([46, 154] as $y)
                                            <line x1="0" y1="{{ $y }}" x2="1000" y2="{{ $y }}" stroke="currentColor"
                                                  stroke-width="1" opacity="0.15" vector-effect="non-scaling-stroke" />
                                        @endforeach

                                        <line x1="0" y1="100" x2="1000" y2="100" stroke="currentColor" stroke-width="1"
                                              opacity="0.45" vector-effect="non-scaling-stroke" />

                                        {{-- Przy sześciu punktach włos byłby
                                             niewidoczny, przy sześciuset zlałby się
                                             w plamę — grubość idzie z ich liczby. --}}
                                        @php $grubosc = max(1.5, min(14, 700 / max(count($this->wykres['slupki']), 1))); @endphp

                                        {{-- Pas rysunku jest węższy niż płótno:
                                             słupek pierwszy i ostatni stoją dokładnie
                                             na krańcach osi, więc przy pełnej
                                             szerokości połowa kreski wypadałaby
                                             poza SVG i zostałaby obcięta. --}}
                                        @foreach ($this->wykres['slupki'] as $slupek)
                                            @php $x = round(10 + $slupek['x'] * 980, 2); @endphp

                                            <line
                                                x1="{{ $x }}"
                                                y1="100"
                                                x2="{{ $x }}"
                                                y2="{{ round(100 - $slupek['lean'] / $this->wykres['maks'] * 92, 2) }}"
                                                stroke="{{ $slupek['kolor'] }}"
                                                stroke-width="{{ round($grubosc, 2) }}"
                                                vector-effect="non-scaling-stroke"
                                            />
                                        @endforeach
                                    </svg>
                                </div>
                            </div>

                            {{-- Legenda stoi przy rysunku, którego dotyczy.
                                 Ta sama wisi pod mapą, ale dzieli je pół ekranu,
                                 a barwy słupków bez niej nie znaczą nic. --}}
                            <div class="mt-2 flex gap-3">
                                <div class="w-14 shrink-0"></div>
                                <x-przechyl-legenda class="min-w-0 flex-1" />
                            </div>
                        @endif

                        @if ($this->predkosci)
                            <div class="mt-6 font-mono text-[11px] tracking-wider text-zinc-500 uppercase">
                                {{ __('Prędkość — wyliczona z odległości między punktami') }}
                            </div>

                            <div class="mt-2 flex gap-3">
                                <div
                                    class="flex w-14 shrink-0 flex-col justify-between text-right font-mono text-[11px] text-zinc-500 dark:text-zinc-400"
                                    style="height: 140px"
                                    aria-hidden="true"
                                >
                                    <span>{{ $this->predkosci['maks'] }} km/h</span>
                                    <span>0 km/h</span>
                                </div>

                                @php
                                    // Współrzędne składamy tutaj, a nie w atrybucie
                                    // `points`: `@php` postawione w środku atrybutu
                                    // rozbija kompilację Blade'a — sąsiednie `{{ }}`
                                    // zostają wtedy w wyniku dosłownie i przeglądarka
                                    // dostaje listę punktów, której nie umie odczytać.
                                    $skala = $this->predkosci['maks'];
                                    $sciezki = [];

                                    foreach ($this->predkosci['linie'] as $linia) {
                                        $punkty = [];

                                        foreach ($linia as $krok) {
                                            $y = round(190 - $krok['kmh'] / $skala * 180, 2);

                                            // Dwa wierzchołki na pomiar — początek
                                            // i koniec przedziału — dają schodek.
                                            $punkty[] = round(10 + $krok['od'] * 980, 2).",{$y}";
                                            $punkty[] = round(10 + $krok['do'] * 980, 2).",{$y}";
                                        }

                                        $sciezki[] = implode(' ', $punkty);
                                    }
                                @endphp

                                <div class="min-w-0 flex-1">
                                    <svg
                                        viewBox="0 0 1000 200"
                                        preserveAspectRatio="none"
                                        class="w-full"
                                        style="height: 140px"
                                        role="img"
                                        aria-label="{{ __('Prędkość wzdłuż przejazdu, do :maks km/h', ['maks' => $this->predkosci['maks']]) }}"
                                    >
                                        <line x1="0" y1="190" x2="1000" y2="190" stroke="currentColor" stroke-width="1"
                                              opacity="0.45" vector-effect="non-scaling-stroke" />

                                        <line x1="0" y1="100" x2="1000" y2="100" stroke="currentColor" stroke-width="1"
                                              opacity="0.15" vector-effect="non-scaling-stroke" />

                                        {{-- Prędkość maksymalna prosto z GPS-a, dla
                                             porównania z wyliczoną. Rozjazd między
                                             linią a kreską znaczy, że jedno z dwóch
                                             kłamie — i warto to widzieć. --}}
                                        @if ($this->predkosci['gps'] !== null)
                                            <line
                                                x1="0" x2="1000"
                                                y1="{{ round(190 - $this->predkosci['gps'] / $this->predkosci['maks'] * 180, 2) }}"
                                                y2="{{ round(190 - $this->predkosci['gps'] / $this->predkosci['maks'] * 180, 2) }}"
                                                stroke="#dc2626" stroke-width="1.5" stroke-dasharray="6 5"
                                                vector-effect="non-scaling-stroke"
                                            />
                                        @endif

                                        {{-- Schodek, nie łamana: każdy pomiar to
                                             średnia z całego przedziału między
                                             punktami, więc obowiązuje na całej
                                             jego długości, a nie w jednym miejscu.

                                             Osobna linia na segment — przez przerwę
                                             w śladzie nikt nie jechał, więc łączenie
                                             segmentów rysowałoby przebieg, którego
                                             nie było. --}}
                                        @foreach ($sciezki as $punkty)
                                            <polyline
                                                fill="none"
                                                stroke="#52525b"
                                                stroke-width="2"
                                                stroke-linejoin="miter"
                                                vector-effect="non-scaling-stroke"
                                                points="{{ $punkty }}"
                                            />
                                        @endforeach
                                    </svg>
                                </div>
                            </div>

                            @if ($this->predkosci['gps'] !== null)
                                <div class="mt-2 flex gap-3">
                                    <div class="w-14 shrink-0"></div>
                                    <div class="min-w-0 flex-1 font-mono text-[11px] tracking-wider text-zinc-500 uppercase">
                                        <span style="display:inline-block; width:18px; height:0; border-top:2px dashed #dc2626; vertical-align:middle;"></span>
                                        {{ __('prędkość maksymalna z GPS-a: :v', ['v' => Pomiar::predkosc($ride->speed_kmh)]) }}
                                    </div>
                                </div>
                            @endif
                        @endif

                        {{-- Oś pozioma jest wspólna, więc podpisana raz, pod
                             ostatnim rysunkiem. Pusty kafelek trzyma ją w pionie
                             pod polem rysunku, a nie pod osią pionową. --}}
                        <div class="mt-2 flex gap-3">
                            <div class="w-14 shrink-0"></div>
                            <div class="flex min-w-0 flex-1 justify-between font-mono text-[11px] tracking-wider text-zinc-500 uppercase">
                                <span>{{ $this->os['od'] }}</span>
                                <span>{{ $this->os['do'] }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($this->predkosci)
                        <flux:text size="sm" class="mt-3">
                            {{ __('Prędkości nie ma w śladzie: urządzenie jej nie wysyła (kontrakt §8). Ta linia to odległość między punktami podzielona przez czas, czyli średnia z odcinka — na zakrętach zaniżona, a między rzadkimi punktami wygładzona.') }}
                        </flux:text>
                    @endif
                </div>
            @endif

            <div class="mt-8">
                <x-pomiary-siatka :pomiary="[
                    [null, 'Dystans', Pomiar::dystans($ride->track->distance_m)],
                    [null, 'Punktów', number_format($ride->track->point_count, 0, ',', ' ')],
                    [null, 'Odcinków', number_format($ride->track->segment_count, 0, ',', ' ')],
                    [null, 'Korytarz zapisu', $ride->track->corridor_m . ' m'],
                    [null, 'Firmware', $ride->track->fw],
                ]" />

                <flux:text size="sm" class="mt-3">
                    {{ __('Dystans liczony z odcinków między punktami, bez przerw w śladzie. Na zakrętach jest zaniżony o około procent — to cięciwa, nie łuk.') }}
                </flux:text>
            </div>
        </div>

        @script
        <script>
            // Leaflet leży w public/vendor i wchodzi zwykłym <script>, obok
            // Vite — dzięki temu mapa nie wymaga przebudowy frontu. Ładujemy
            // go raz na sesję przeglądarki: przy `wire:navigate` head zostaje,
            // więc drugie wejście na kartę zastanie bibliotekę gotową.
            window.motusyLeaflet ??= new Promise((gotowe, blad) => {
                if (window.L) return gotowe();

                const style = document.createElement('link');
                style.rel = 'stylesheet';
                style.href = '/vendor/leaflet/leaflet.css';
                document.head.appendChild(style);

                const skrypt = document.createElement('script');
                skrypt.src = '/vendor/leaflet/leaflet.js';
                skrypt.onload = gotowe;
                skrypt.onerror = blad;
                document.head.appendChild(skrypt);
            });

            const dane = @json($this->mapa);

            window.motusyLeaflet.then(() => {
                const element = document.getElementById('mapa-sladu');

                if (! element || ! dane || element.dataset.gotowa) {
                    return;
                }

                element.dataset.gotowa = '1';

                const mapa = L.map(element, {
                    // Kółko myszy przewija stronę, nie mapę — inaczej
                    // przewijanie karty zatrzymuje się na mapie.
                    scrollWheelZoom: false,
                    attributionControl: true,
                });

                // Kafelki bez klucza API. Odbarwia je filtr CSS obok — także
                // w trybie ciemnym, więc przełącznik motywu nie wymaga tu
                // ani jednej linijki JavaScriptu.
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(mapa);

                dane.linie.forEach((linia) => {
                    L.polyline(linia.punkty, { color: linia.kolor, weight: 4, opacity: 0.95 }).addTo(mapa);
                });

                const znacznik = (punkt, wypelnienie, klasa, opis) => L.circleMarker(punkt, {
                    radius: 6,
                    color: '#2b2b2a',
                    weight: 2,
                    fillColor: wypelnienie,
                    fillOpacity: 1,
                    className: klasa,
                }).addTo(mapa).bindTooltip(opis);

                // Start pusty, koniec wypełniony — jak na rysunku technicznym.
                znacznik(dane.start, '#ffffff', 'znacznik-start', @js(__('Start')));
                znacznik(dane.koniec, '#2b2b2a', 'znacznik-koniec', @js(__('Koniec')));

                mapa.fitBounds(dane.granice, { padding: [24, 24], maxZoom: 17 });
            });
        </script>
        @endscript
    @endif
</div>
