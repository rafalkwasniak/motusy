{{-- Obudowa strony dla kogoś, kto przyszedł z linku i konta nie ma.

     Osobny layout, a nie panel z wyciętym paskiem bocznym: `app/sidebar`
     sięga po `auth()->user()->displayName()`, więc gościowi wywaliłby się
     na pustym użytkowniku. Przy okazji ta strona jest dla obcego pierwszym
     kontaktem z Motusami, więc kończy się zaproszeniem, a nie niczym. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- Adres jest poświadczeniem samym w sobie, więc nie ma prawa trafić
             do wyszukiwarki, gdyby ktoś wkleił go na forum. --}}
        <meta name="robots" content="noindex, nofollow" />

        @include('partials.head')
    </head>

    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-neutral-950 dark:text-zinc-100">
        <header class="bg-ink text-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-6 py-4">
                <a href="{{ route('home') }}">
                    <img
                        src="{{ asset('images/motusy-logo.png') }}"
                        alt="{{ __('Motusy — Two Wheels Society') }}"
                        class="h-14 w-auto"
                    />
                </a>

                <nav class="flex items-center gap-6 font-mono text-xs tracking-[0.18em] uppercase">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-white/70 transition hover:text-white">{{ __('Mój panel') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="text-white/70 transition hover:text-white">{{ __('Zaloguj się') }}</a>
                        <a href="{{ route('register') }}" class="text-brand-400 transition hover:text-brand-300">{{ __('Załóż konto') }}</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-9">
            {{-- Plakietka zamiast milczenia: obcy ma wiedzieć, że ogląda cudzy
                 przejazd udostępniony linkiem, a nie własny panel. --}}
            <p class="mb-6 font-mono text-xs tracking-[0.18em] text-zinc-500 uppercase">
                {{ __('Przejazd udostępniony linkiem') }}
            </p>

            {{ $slot }}
        </main>

        <footer class="bg-ink text-white">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-5 border-t border-white/10 px-6 py-9 sm:flex-row">
                <p class="font-mono text-xs tracking-wider text-white/40 uppercase">
                    &copy; {{ date('Y') }} Motusy &middot; {{ __('Wszelkie prawa zastrzeżone.') }}
                </p>

                @guest
                    <a href="{{ route('register') }}" class="bg-brand-600 px-7 py-3.5 font-display text-lg font-semibold tracking-widest uppercase transition hover:bg-brand-500">
                        {{ __('Załóż konto') }}
                    </a>
                @endguest
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
