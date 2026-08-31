{{--
    Rysunek techniczny Motusy Moto Box — widok z przodu, w konwencji
    instrukcji: biała kartka, czarne obrysy, opisy na odnośnikach.
    Rysowany wektorowo zamiast obrabiania fotografii, żeby był ostry
    w każdej skali i żeby dało się go przemalować pod motyw.

    Kolory biorą się z `currentColor` i klas na elemencie nadrzędnym.
--}}
<svg
    viewBox="-200 0 940 620"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-label="{{ __('Rysunek techniczny urządzenia Motusy Moto Box') }}"
    {{ $attributes->merge(['class' => 'w-full h-auto']) }}
>
    <g stroke="currentColor" stroke-linecap="square">

        {{-- Obudowa --}}
        <rect x="60" y="46" width="248" height="500" rx="34" stroke-width="3" />
        <rect x="72" y="58" width="224" height="476" rx="24" stroke-width="1" opacity=".35" />

        {{-- Ekran --}}
        <rect x="96" y="88" width="176" height="252" rx="6" stroke-width="2.5" />

        {{-- Przycisk użytkownika --}}
        <rect x="134" y="392" width="100" height="30" rx="15" stroke-width="2.5" />
        <line x1="150" y1="407" x2="218" y2="407" stroke-width="1" opacity=".4" />

        {{-- Gniazdo USB-C w dolnej krawędzi --}}
        <rect x="152" y="524" width="64" height="22" rx="11" stroke-width="2.5" />

        {{-- Wentylacja / żłobienia na prawym boku --}}
        <g stroke-width="1.5" opacity=".55">
            <line x1="292" y1="180" x2="292" y2="236" />
            <line x1="300" y1="180" x2="300" y2="236" />
            <line x1="292" y1="268" x2="292" y2="324" />
            <line x1="300" y1="268" x2="300" y2="324" />
        </g>

        {{-- Odnośniki opisowe --}}
        <g stroke-width="1.5" opacity=".75">
            <path d="M272 120 H 380" />
            <circle cx="272" cy="120" r="3.5" fill="currentColor" stroke="none" />

            <path d="M234 407 H 300 V 400 H 380" />
            <circle cx="234" cy="407" r="3.5" fill="currentColor" stroke="none" />

            <path d="M216 535 H 340 V 500 H 380" />
            <circle cx="216" cy="535" r="3.5" fill="currentColor" stroke="none" />

            {{-- Czujnik siedzi w środku, więc odcinek pod obudową jest kreskowany. --}}
            <path d="M184 362 H 60" stroke-dasharray="5 5" />
            <path d="M60 362 H -30" />
            <circle cx="184" cy="362" r="3.5" fill="currentColor" stroke="none" />
        </g>

        {{-- Wymiar wysokości --}}
        <g stroke-width="1" opacity=".45">
            <line x1="40" y1="46" x2="40" y2="546" />
            <line x1="34" y1="46" x2="46" y2="46" />
            <line x1="34" y1="546" x2="46" y2="546" />
        </g>
    </g>

    {{-- Treść ekranu --}}
    <g fill="currentColor" font-family="'JetBrains Mono', ui-monospace, monospace">
        <text x="110" y="112" font-size="11" letter-spacing="1.5" opacity=".65">OSTATNIA JAZDA</text>
        <line x1="110" y1="122" x2="258" y2="122" stroke="currentColor" stroke-width="1" opacity=".3" />

        @foreach ([
            ['LEWO', '42.0°'],
            ['PRAWO', '38.4°'],
            ['PRZYSP.', '0.75 g'],
            ['HAM.', '0.82 g'],
            ['MAX SPEED', '187 km/h'],
        ] as $i => $row)
            <text x="110" y="{{ 152 + $i * 36 }}" font-size="11" letter-spacing=".5" opacity=".6">{{ $row[0] }}</text>
            <text x="258" y="{{ 152 + $i * 36 }}" font-size="16" font-weight="700" text-anchor="end">{{ $row[1] }}</text>
        @endforeach
    </g>

    {{-- Podpisy --}}
    <g fill="currentColor" font-family="'Barlow Condensed', sans-serif" font-size="19" letter-spacing="1.2">
        <text x="390" y="115">{{ mb_strtoupper(__('Ekran')) }}</text>
        <text x="390" y="395">{{ mb_strtoupper(__('Przycisk')) }}</text>
        <text x="390" y="495">{{ mb_strtoupper(__('Gniazdo USB-C')) }}</text>
    </g>

    <g fill="currentColor" font-family="'Barlow Condensed', sans-serif" font-size="19" letter-spacing="1.2" text-anchor="end">
        <text x="-40" y="357">{{ mb_strtoupper(__('Czujnik IMU')) }}</text>
    </g>

    <g fill="currentColor" font-family="'JetBrains Mono', ui-monospace, monospace" font-size="10" opacity=".5">
        <text x="390" y="135">{{ __('wyniki odświeżane w trakcie jazdy') }}</text>
        <text x="390" y="415">{{ __('alarm · reset 3 s · kalibracja 10 s') }}</text>
        <text x="390" y="515">{{ __('zasilanie ze stacyjki, 12 V → 5 V') }}</text>
        <text x="-40" y="377" text-anchor="end">{{ __('BMI270') }}</text>
    </g>
</svg>
