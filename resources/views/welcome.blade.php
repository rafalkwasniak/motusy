<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Rejestrator dynamiki jazdy motocyklem')])
    </head>

    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-neutral-950 dark:text-zinc-100">

        {{-- Nagłówek: ciemna plansza, logo w pełnej okazałości --}}
        <header class="bg-ink text-white">
            <div class="mx-auto max-w-6xl px-6">
                <nav class="flex items-center justify-end gap-6 border-b border-white/10 py-3 font-mono text-xs tracking-[0.18em] uppercase">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-white/70 transition hover:text-white">{{ __('Mój panel') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="text-white/70 transition hover:text-white">{{ __('Zaloguj się') }}</a>
                        <a href="{{ route('register') }}" class="text-brand-400 transition hover:text-brand-300">{{ __('Załóż konto') }}</a>
                    @endauth
                </nav>

                <div class="grid gap-10 py-14 lg:grid-cols-[1.1fr_1fr] lg:items-center lg:py-20">
                    <div>
                        <img
                            src="{{ asset('images/motusy-logo.png') }}"
                            alt="{{ __('Motusy — Two Wheels Society') }}"
                            class="h-28 w-auto sm:h-36 lg:h-44"
                        />

                        <div class="mt-10 flex items-center gap-4">
                            <span class="h-1 w-14 bg-brand-500"></span>
                            <span class="font-mono text-xs tracking-[0.28em] text-white/50 uppercase">{{ __('Moto Box') }}</span>
                        </div>

                        <h1 class="mt-5 font-display text-5xl leading-[0.95] font-bold tracking-wide uppercase sm:text-6xl lg:text-7xl">
                            {{ __('Twoja jazda') }}<br>
                            <span class="text-brand-500">{{ __('w liczbach') }}</span>
                        </h1>

                        <p class="mt-7 max-w-xl leading-relaxed text-white/70">
                            {{ __('Pudełko wielkości pilota, przykręcone do motocykla. Zapisuje, jak nisko położyłeś się w zakręcie, ile wyciągnąłeś z przyspieszenia, jak ostro hamowałeś i ile pokazał licznik na maksa. Bez telefonu, bez aplikacji — startuje razem ze stacyjką.') }}
                        </p>

                        <div class="mt-9 flex flex-wrap items-center gap-3">
                            <a href="{{ route('register') }}" class="bg-brand-600 px-7 py-3.5 font-display text-lg font-semibold tracking-widest uppercase transition hover:bg-brand-500">
                                {{ __('Załóż konto') }}
                            </a>
                            <a href="#urzadzenie" class="border border-white/25 px-7 py-3.5 font-display text-lg font-semibold tracking-widest uppercase transition hover:border-white/60">
                                {{ __('Zobacz urządzenie') }}
                            </a>
                        </div>
                    </div>

                    {{-- Ekran urządzenia --}}
                    <div class="lg:justify-self-end">
                        <div class="w-full max-w-sm border border-white/15 bg-black/25 p-6">
                            <div class="flex items-center justify-between border-b border-white/10 pb-3 font-mono text-[11px] tracking-[0.18em] uppercase">
                                <span class="text-white/50">{{ __('Ostatnia jazda') }}</span>
                                <span class="flex items-center gap-2 text-brand-400">
                                    <span class="inline-block size-1.5 bg-brand-500"></span>
                                    {{ __('rejestracja') }}
                                </span>
                            </div>

                            <dl class="divide-y divide-white/10">
                                @foreach ([
                                    ['Przechył w lewo', '42.0', '°'],
                                    ['Przechył w prawo', '38.4', '°'],
                                    ['Przyspieszenie', '0.75', 'g'],
                                    ['Hamowanie', '0.82', 'g'],
                                    ['Prędkość maksymalna', '187', 'km/h'],
                                ] as $row)
                                    <div class="flex items-baseline justify-between gap-4 py-3">
                                        <dt class="font-mono text-[11px] tracking-wider text-white/45 uppercase">{{ __($row[0]) }}</dt>
                                        <dd class="font-mono text-2xl font-bold text-white tabular-nums">
                                            {{ $row[1] }}<span class="ml-1 text-sm font-normal text-white/50">{{ $row[2] }}</span>
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main>
            {{-- Co mierzy --}}
            <section class="mx-auto max-w-6xl px-6 py-16 sm:py-24">
                <div class="flex items-center gap-4">
                    <span class="h-1 w-14 bg-brand-600"></span>
                    <span class="font-mono text-xs tracking-[0.28em] text-zinc-500 uppercase">{{ __('Pomiar') }}</span>
                </div>

                <h2 class="mt-5 max-w-2xl font-display text-4xl font-bold tracking-wide uppercase sm:text-5xl">
                    {{ __('Pięć liczb, które coś znaczą') }}
                </h2>
                <p class="mt-5 max-w-2xl leading-relaxed text-zinc-600 dark:text-zinc-400">
                    {{ __('Czujnik ruchu liczy przechył i przeciążenia setki razy na sekundę. Zapisywane są wartości skrajne — te, które faktycznie pamiętasz po zjeździe z drogi.') }}
                </p>

                <div class="mt-12 grid gap-px border border-zinc-300 bg-zinc-300 sm:grid-cols-2 lg:grid-cols-3 dark:border-neutral-700 dark:bg-neutral-700">
                    @foreach ([
                        ['01', 'Maksymalny przechył w lewo', 'W stopniach, liczony od pionu ustawionego przy kalibracji.'],
                        ['02', 'Maksymalny przechył w prawo', 'Osobno dla każdej strony — prawie nikt nie kładzie się symetrycznie.'],
                        ['03', 'Maksymalne przyspieszenie', 'W jednostkach g, w osi jazdy. Im wyższa liczba, tym mocniejsze wyrwanie.'],
                        ['04', 'Maksymalne hamowanie', 'Też w g. Pokazuje, jak blisko granicy przyczepności był przedni hamulec.'],
                        ['05', 'Prędkość maksymalna', 'Najwyższa prędkość osiągnięta w trakcie przejazdu, w km/h.'],
                    ] as $card)
                        <div class="bg-white p-6 dark:bg-neutral-950">
                            <span class="font-mono text-sm font-bold text-brand-600 dark:text-brand-500">{{ $card[0] }}</span>
                            <h3 class="mt-3 font-display text-xl font-semibold tracking-wide uppercase">{{ __($card[1]) }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ __($card[2]) }}</p>
                        </div>
                    @endforeach

                    <div class="bg-zinc-100 p-6 dark:bg-neutral-900">
                        <span class="font-mono text-sm font-bold text-zinc-400">&mdash;</span>
                        <h3 class="mt-3 font-display text-xl font-semibold tracking-wide uppercase">{{ __('Dwa zestawy naraz') }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                            {{ __('Ostatnia jazda zeruje się przy każdym przekręceniu kluczyka. Rekord ogólny trzyma się do skutku — kasuje go tylko świadome zerowanie przyciskiem.') }}
                        </p>
                    </div>
                </div>
            </section>

            {{-- Urządzenie --}}
            <section id="urzadzenie" class="border-y border-zinc-300 bg-zinc-50 dark:border-neutral-800 dark:bg-neutral-900/40">
                <div class="mx-auto grid max-w-6xl gap-12 px-6 py-16 sm:py-24 lg:grid-cols-[1fr_1.15fr] lg:items-center">
                    <div>
                        <div class="flex items-center gap-4">
                            <span class="h-1 w-14 bg-brand-600"></span>
                            <span class="font-mono text-xs tracking-[0.28em] text-zinc-500 uppercase">{{ __('Urządzenie') }}</span>
                        </div>

                        <h2 class="mt-5 font-display text-4xl font-bold tracking-wide uppercase sm:text-5xl">
                            {{ __('Jeden przycisk, jeden kabel') }}
                        </h2>

                        <p class="mt-5 leading-relaxed text-zinc-600 dark:text-zinc-400">
                            {{ __('Całość mieści się w dłoni. Zasilanie idzie z instalacji motocykla przez przetwornicę na USB-C, więc pudełko budzi się razem z zapłonem. Własny akumulator pilnuje wyników i alarmu, gdy zgaśniesz silnik.') }}
                        </p>

                        <dl class="mt-8 grid gap-px border border-zinc-300 bg-zinc-300 sm:grid-cols-2 dark:border-neutral-700 dark:bg-neutral-700">
                            @foreach ([
                                ['Krótkie naciśnięcie', 'włącza i wyłącza alarm'],
                                ['Przytrzymanie 3 s', 'zeruje wszystkie wyniki'],
                                ['Przytrzymanie 10 s', 'uruchamia kalibrację'],
                                ['Zanik zasilania', 'przejście na akumulator'],
                            ] as $row)
                                <div class="bg-zinc-50 p-4 dark:bg-neutral-900">
                                    <dt class="font-mono text-[11px] tracking-wider text-zinc-500 uppercase">{{ __($row[0]) }}</dt>
                                    <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ __($row[1]) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div class="border border-zinc-300 bg-white p-6 sm:p-10 dark:border-neutral-700 dark:bg-neutral-950">
                        <x-moto-box-drawing class="text-zinc-900 dark:text-zinc-200" />
                    </div>
                </div>
            </section>

            {{-- Jak działa --}}
            <section class="mx-auto max-w-6xl px-6 py-16 sm:py-24">
                <div class="flex items-center gap-4">
                    <span class="h-1 w-14 bg-brand-600"></span>
                    <span class="font-mono text-xs tracking-[0.28em] text-zinc-500 uppercase">{{ __('Obsługa') }}</span>
                </div>

                <h2 class="mt-5 font-display text-4xl font-bold tracking-wide uppercase sm:text-5xl">
                    {{ __('Nic nie musisz robić') }}
                </h2>

                <ol class="mt-12 grid gap-px bg-zinc-300 md:grid-cols-3 dark:bg-neutral-700">
                    @foreach ([
                        ['Przekręcasz kluczyk', 'Urządzenie wstaje, pokazuje logo i zaczyna nową jazdę. Wyniki poprzedniej zostają wyzerowane.'],
                        ['Jedziesz', 'Przez całą drogę zapisywane są wartości skrajne. Ekran pokazuje je na bieżąco.'],
                        ['Gasisz silnik', 'Wyniki zostają na ekranie, a pudełko przechodzi na własny akumulator.'],
                    ] as $i => $step)
                        <li class="bg-white p-7 dark:bg-neutral-950">
                            <span class="font-mono text-4xl font-bold text-brand-600 dark:text-brand-500">{{ sprintf('%02d', $i + 1) }}</span>
                            <h3 class="mt-4 font-display text-xl font-semibold tracking-wide uppercase">{{ __($step[0]) }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ __($step[1]) }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Alarm --}}
            <section class="border-y border-zinc-300 bg-zinc-50 dark:border-neutral-800 dark:bg-neutral-900/40">
                <div class="mx-auto grid max-w-6xl gap-12 px-6 py-16 sm:py-24 lg:grid-cols-2 lg:items-start">
                    <div>
                        <div class="flex items-center gap-4">
                            <span class="h-1 w-14 bg-brand-600"></span>
                            <span class="font-mono text-xs tracking-[0.28em] text-zinc-500 uppercase">{{ __('Przy okazji') }}</span>
                        </div>

                        <h2 class="mt-5 font-display text-4xl font-bold tracking-wide uppercase sm:text-5xl">
                            {{ __('Pilnuje, gdy odchodzisz') }}
                        </h2>

                        <p class="mt-5 leading-relaxed text-zinc-600 dark:text-zinc-400">
                            {{ __('Trzy minuty po zgaszeniu silnika pudełko uzbraja się samo. Od tej chwili reaguje na poruszenie motocykla — najpierw krótkim sygnałem, a przy dalszym ruchu coraz głośniej.') }}
                        </p>
                        <p class="mt-4 leading-relaxed text-zinc-600 dark:text-zinc-400">
                            {{ __('Rozbrojenie też jest automatyczne: wystarczy przekręcić kluczyk. Nie ma pilota, kodu ani aplikacji, o której trzeba pamiętać.') }}
                        </p>
                    </div>

                    <ul class="grid gap-px border border-zinc-300 bg-zinc-300 dark:border-neutral-700 dark:bg-neutral-700">
                        @foreach ([
                            ['3 minuty', 'Tyle masz na zdjęcie kasku i odejście, zanim alarm zacznie pracować.'],
                            ['Ruch, nie drgania', 'Pojedyncze szarpnięcie od wiatru czy przejeżdżającej ciężarówki nie budzi syreny.'],
                            ['Trzy stopnie głośności', 'Sygnał rośnie z każdą próbą — od krótkiego pisku po dźwięk ciągły.'],
                        ] as $item)
                            <li class="bg-zinc-50 p-6 dark:bg-neutral-900">
                                <h3 class="font-display text-lg font-semibold tracking-wide uppercase">{{ __($item[0]) }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ __($item[1]) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Konto --}}
            <section class="mx-auto max-w-6xl px-6 py-16 sm:py-24">
                <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                    <div>
                        <div class="flex items-center gap-4">
                            <span class="h-1 w-14 bg-brand-600"></span>
                            <span class="font-mono text-xs tracking-[0.28em] text-zinc-500 uppercase">{{ __('Portal') }}</span>
                        </div>

                        <h2 class="mt-5 font-display text-4xl font-bold tracking-wide uppercase sm:text-5xl">
                            {{ __('Konto, w którym wszystko zostaje') }}
                        </h2>

                        <p class="mt-5 leading-relaxed text-zinc-600 dark:text-zinc-400">
                            {{ __('Ekran urządzenia pokazuje ostatnią jazdę i rekord. Konto na motusy.top pamięta całą resztę — każdy przejazd po kolei, z czasem trwania i kompletem wartości.') }}
                        </p>
                        <p class="mt-4 leading-relaxed text-zinc-600 dark:text-zinc-400">
                            {{ __('Jedno konto obsługuje wiele urządzeń. Każdemu nadajesz własną nazwę, więc od razu wiadomo, który motocykl jest który.') }}
                        </p>
                    </div>

                    <ul class="divide-y divide-zinc-300 border-y border-zinc-300 dark:divide-neutral-700 dark:border-neutral-700">
                        @foreach ([
                            'Historia wszystkich przejazdów, najnowsze na górze.',
                            'Nazwy urządzeń nadawane przez Ciebie.',
                            'Usuwanie przejazdów, których nie chcesz trzymać.',
                            'Dane wędrują do portalu po WiFi, prosto z pudełka.',
                        ] as $i => $point)
                            <li class="flex items-baseline gap-4 py-4">
                                <span class="font-mono text-xs text-brand-600 dark:text-brand-500">{{ sprintf('%02d', $i + 1) }}</span>
                                <span class="text-zinc-700 dark:text-zinc-300">{{ __($point) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Zachęta --}}
            <section class="bg-ink text-white">
                <div class="mx-auto max-w-3xl px-6 py-16 text-center sm:py-24">
                    <h2 class="font-display text-4xl font-bold tracking-wide text-balance uppercase sm:text-5xl">
                        {{ __('Załóż konto i podłącz pudełko') }}
                    </h2>
                    <p class="mt-5 text-white/70">
                        {{ __('Rejestracja zajmuje chwilę. Konto jest potrzebne, żeby urządzenie miało dokąd wysyłać przejazdy.') }}
                    </p>
                    <div class="mt-9 flex flex-wrap justify-center gap-3">
                        <a href="{{ route('register') }}" class="bg-brand-600 px-7 py-3.5 font-display text-lg font-semibold tracking-widest uppercase transition hover:bg-brand-500">
                            {{ __('Załóż konto') }}
                        </a>
                        <a href="{{ route('login') }}" class="border border-white/25 px-7 py-3.5 font-display text-lg font-semibold tracking-widest uppercase transition hover:border-white/60">
                            {{ __('Mam już konto') }}
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-ink text-white">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-5 border-t border-white/10 px-6 py-9 sm:flex-row">
                <img src="{{ asset('images/motusy-logo.png') }}" alt="{{ __('Motusy — Two Wheels Society') }}" class="h-14 w-auto" />
                <p class="font-mono text-xs tracking-wider text-white/40 uppercase">
                    &copy; {{ date('Y') }} Motusy &middot; {{ __('Wszelkie prawa zastrzeżone.') }}
                </p>
            </div>
        </footer>
    </body>
</html>
