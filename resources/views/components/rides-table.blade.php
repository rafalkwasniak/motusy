@use('App\Support\Pomiar')

@props(['rides', 'deletable' => false])

{{-- Ta sama tabela stoi na pulpicie i w pełnej historii, więc kolumny,
     kolejność i zapis pomiarów trzymamy w jednym pliku. Kasowanie włącza się
     flagą: pulpit tylko podgląda, a modal i metody siedzą w historii. --}}
<div class="overflow-x-auto border border-zinc-200 dark:border-neutral-700">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 font-mono text-[11px] tracking-wider text-zinc-500 uppercase dark:border-neutral-700 dark:bg-neutral-900">
            <tr>
                <th class="px-4 py-3 font-medium">{{ __('Nr') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Urządzenie') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Czas') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Lewo') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Prawo') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Przysp.') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Ham.') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Maks.') }}</th>
                @if ($deletable)
                    <th class="px-4 py-3"><span class="sr-only">{{ __('Akcje') }}</span></th>
                @endif
            </tr>
        </thead>

        <tbody class="divide-y divide-zinc-200 dark:divide-neutral-700">
            @foreach ($rides as $ride)
                <tr wire:key="ride-{{ $ride->id }}">
                    <td class="px-4 py-3 font-mono text-zinc-500">#{{ $ride->seq }}</td>

                    <td class="px-4 py-3">{{ $ride->deviceName() }}</td>

                    <td class="px-4 py-3">
                        <div>{{ $ride->durationForHumans() }}</div>
                        @if ($ride->recordedAt())
                            <div class="text-xs text-zinc-500">{{ $ride->recordedAt()->translatedFormat('j M Y, H:i') }}</div>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-right font-mono tabular-nums">{{ Pomiar::stopnie($ride->lean_left_deg) }}</td>
                    <td class="px-4 py-3 text-right font-mono tabular-nums">{{ Pomiar::stopnie($ride->lean_right_deg) }}</td>
                    <td class="px-4 py-3 text-right font-mono tabular-nums">{{ Pomiar::przeciazenie($ride->accel_g) }}</td>
                    <td class="px-4 py-3 text-right font-mono tabular-nums">{{ Pomiar::przeciazenie($ride->brake_g) }}</td>

                    <td class="px-4 py-3 text-right font-mono tabular-nums">
                        @if ($ride->hasSpeed())
                            {{ Pomiar::predkosc($ride->speed_kmh) }}
                        @else
                            <span class="text-zinc-400" title="{{ __('Urządzenie nie zmierzyło prędkości') }}">{{ Pomiar::BRAK }}</span>
                        @endif
                    </td>

                    @if ($deletable)
                        <td class="px-4 py-3 text-right">
                            <flux:button
                                size="xs"
                                variant="subtle"
                                icon="trash"
                                wire:click="confirmDelete({{ $ride->id }})"
                            >
                                <span class="sr-only">{{ __('Usuń przejazd') }}</span>
                            </flux:button>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
