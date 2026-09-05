{{-- Karta przejazdu ma dwa wcielenia: prywatne w panelu i publiczne pod
     linkiem z tokenem. Treść jest identyczna, różni je wyłącznie obudowa —
     więc zamiast dublować komponent rozstrzygamy ją tutaj, w jednym miejscu.

     Layout składa się tylko przy pełnym renderze strony, nigdy przy
     odświeżeniu Livewire'em, więc pytanie o nazwę trasy jest tu wiarygodne. --}}
@if (request()->routeIs('rides.shared'))
    <x-layouts::publiczny :title="$title ?? null">
        {{ $slot }}
    </x-layouts::publiczny>
@else
    <x-layouts::app :title="$title ?? null">
        {{ $slot }}
    </x-layouts::app>
@endif
