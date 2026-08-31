{{-- Ta sama treść pokazuje się na pulpicie i w historii przejazdów,
     więc trzymamy ją w jednym miejscu. Wygląd bierze z <x-empty-state>. --}}
<x-empty-state :heading="__('Nie ma tu jeszcze żadnego przejazdu')">
    {{ __('Przejazdy pojawią się tu same, gdy pudełko połączy się z WiFi i wyśle to, co zapisało. Urządzenie dopisze się do konta przy pierwszej wysyłce — nie ma osobnego parowania.') }}
</x-empty-state>
