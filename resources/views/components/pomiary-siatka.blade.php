@use('App\Support\Pomiar')

@props(['pomiary'])

{{-- Rząd kafli z pomiarami. Ta sama siatka stoi na pulpicie (rekordy konta)
     i na karcie przejazdu (jedna jazda), więc odstępy, obwódki i zapis liczb
     trzymamy w jednym pliku.

     `pomiary` to lista trójek [typ ikony, podpis, wartość]. Typ `null` daje
     kafel bez ikony — tak wyglądają statystyki śladu, dla których nie ma
     piktogramu i nie ma powodu go dorysowywać. --}}
<div class="grid gap-px border border-zinc-200 bg-zinc-200 sm:grid-cols-2 lg:grid-cols-5 dark:border-neutral-700 dark:bg-neutral-700">
    @foreach ($pomiary as [$typ, $label, $value])
        <div class="bg-white p-5 dark:bg-neutral-900">
            <div class="font-mono text-[11px] tracking-wider text-zinc-500 uppercase">{{ __($label) }}</div>

            {{-- Ikona stoi przed liczbą i ma jej wysokość, więc rząd kafli
                 trzyma jedną linię niezależnie od długości podpisu. --}}
            <div class="mt-2 flex items-center gap-2">
                @if ($typ !== null)
                    <x-pomiar-ikona :typ="$typ" :rozmiar="24" class="shrink-0 text-zinc-400 dark:text-zinc-500" />
                @endif

                <span @class([
                    'font-mono text-2xl font-bold tabular-nums',
                    'text-zinc-400' => $value === Pomiar::BRAK,
                ])>{{ $value }}</span>
            </div>
        </div>
    @endforeach
</div>
