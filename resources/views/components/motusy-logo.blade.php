@props([
    'class' => 'h-14',
])

{{--
    Jeden plik na wszystkie tła. Logo ma własną ciemną plakietkę (#2B2B2A)
    z białym rysunkiem, więc czyta się i na jasnym, i na ciemnym — na ciemnym
    plakietka po prostu wtapia się w tło. Wariant „biały" był błędem:
    zamalowanie wszystkich nieprzezroczystych pikseli robiło z logo plamę.
--}}
<img
    src="{{ asset('images/motusy-logo.png') }}"
    alt="{{ __('Motusy — Two Wheels Society') }}"
    class="{{ $class }} w-auto"
/>
