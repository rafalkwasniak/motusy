@use('App\Support\Przechyl')

{{-- Legenda barw przechyłu. Ta sama skala obowiązuje linię na mapie
     i słupki wykresu, więc legenda stoi przy obu — czytana raz, przy
     jednym rysunku, nie pomaga przy drugim, jeśli dzieli je pół ekranu.

     Barwa mówi o **sile** przechyłu, nie o kierunku: kierunek widać
     z kształtu trasy na mapie i ze zwrotu słupka na wykresie. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-4']) }}>
    @foreach (Przechyl::legenda() as [$kolor, $podpis])
        <span class="flex items-center gap-2 font-mono text-[11px] tracking-wider text-zinc-500 uppercase">
            <span style="display:inline-block; width:18px; height:4px; background: {{ $kolor }};"></span>
            {{ $podpis }}
        </span>
    @endforeach
</div>
