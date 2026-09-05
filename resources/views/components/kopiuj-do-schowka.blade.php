@props([
    'tekst',
    'etykieta' => null,
    'ikona' => 'clipboard',
])

{{-- Kopiowanie robi przeglądarka, nie serwer: kopiowana treść jest już
     w wyrenderowanym HTML-u, więc runda do Livewire'a nic by nie wniosła,
     a `navigator.clipboard` wywołany poza gestem użytkownika bywa blokowany.

     Kopiowana treść jedzie przez `x-data` na zwykłym `<div>`, a nie wprost
     w `x-on:click` przycisku, i to nie jest ozdobnik: Blade kompiluje `@js()`
     w atrybutach zwykłego HTML-a, ale **nie** w atrybutach znacznika
     komponentu — tam wartość zostaje dosłownym napisem `@js(...)`, który
     Alpine próbuje wykonać jako JavaScript i wywala się na składni.

     Etykieta wraca do stanu wyjściowego po dwóch sekundach — przycisk ma
     potwierdzić kliknięcie, a nie zostać na zawsze w trybie „zrobione". --}}
<div x-data="{ skopiowano: false, tekst: @js($tekst) }">
    <flux:button
        size="sm"
        variant="filled"
        :icon="$ikona"
        x-on:click="navigator.clipboard.writeText(tekst); skopiowano = true; setTimeout(() => skopiowano = false, 2000)"
        {{ $attributes }}
    >
        <span x-text="skopiowano ? @js(__('Skopiowano')) : @js($etykieta ?? __('Kopiuj'))">{{ $etykieta ?? __('Kopiuj') }}</span>
    </flux:button>
</div>
