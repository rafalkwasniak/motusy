<?php

use App\Models\Ride;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Panel')] class extends Component {
    /**
     * Rekordy liczone ze wszystkich przejazdów w koncie.
     * `speed_kmh` bywa puste — MAX pomija null, więc brak GPS-a
     * daje tu null, a nie zero.
     */
    #[Computed]
    public function records(): ?object
    {
        return Auth::user()
            ->rides()
            ->selectRaw('MAX(lean_left_deg) AS lean_left_deg')
            ->selectRaw('MAX(lean_right_deg) AS lean_right_deg')
            ->selectRaw('MAX(accel_g) AS accel_g')
            ->selectRaw('MAX(brake_g) AS brake_g')
            ->selectRaw('MAX(speed_kmh) AS speed_kmh')
            ->selectRaw('COUNT(*) AS rides_count')
            ->first();
    }

    /**
     * @return Collection<int, Ride>
     */
    #[Computed]
    public function latestRides(): Collection
    {
        return Auth::user()
            ->rides()
            ->orderByDesc('seq')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function hasDevices(): bool
    {
        return Auth::user()->devices()->exists();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Panel') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">
            {{ __('Rekordy i ostatnie przejazdy z Twoich urządzeń') }}
        </flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    @if (! $this->hasDevices)
        <x-empty-state :heading="__('Konto czeka na pierwsze urządzenie')">
            {{ __('Wpisz token konta do pudełka przy konfiguracji WiFi. Przy pierwszej udanej wysyłce urządzenie dopisze się tutaj samo, razem z przejazdami, które zdążyło zapisać.') }}
        </x-empty-state>
    @else
        {{-- Rekordy --}}
        <div class="grid gap-px border border-zinc-200 bg-zinc-200 sm:grid-cols-2 lg:grid-cols-5 dark:border-neutral-700 dark:bg-neutral-700">
            @php
                $tiles = [
                    ['Przechył w lewo', $this->records->lean_left_deg, '°', 1],
                    ['Przechył w prawo', $this->records->lean_right_deg, '°', 1],
                    ['Przyspieszenie', $this->records->accel_g, 'g', 2],
                    ['Hamowanie', $this->records->brake_g, 'g', 2],
                    ['Prędkość maksymalna', $this->records->speed_kmh, 'km/h', 0],
                ];
            @endphp

            @foreach ($tiles as [$label, $value, $unit, $decimals])
                <div class="bg-white p-5 dark:bg-neutral-900">
                    <div class="font-mono text-[11px] tracking-wider text-zinc-500 uppercase">{{ __($label) }}</div>

                    <div class="mt-2 font-mono text-2xl font-bold tabular-nums">
                        @if ($value === null)
                            <span class="text-zinc-400" title="{{ __('Urządzenie nie zmierzyło prędkości') }}">———</span>
                        @else
                            {{ number_format((float) $value, $decimals, ',', ' ') }}<span class="ml-1 text-sm font-normal text-zinc-500">{{ $unit }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <flux:text size="sm" class="mt-3">
            {{ __('Liczone ze wszystkich przejazdów zapisanych na koncie.') }}
        </flux:text>

        {{-- Ostatnie przejazdy --}}
        <div class="mt-10">
            <div class="mb-4 flex items-end justify-between gap-4">
                <flux:heading size="lg">{{ __('Ostatnie przejazdy') }}</flux:heading>

                <flux:link :href="route('rides.index')" wire:navigate>
                    {{ __('Zobacz wszystkie') }}
                </flux:link>
            </div>

            @if ($this->latestRides->isEmpty())
                <x-rides-empty-state />
            @else
                <div class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-neutral-700 dark:border-neutral-700">
                    @foreach ($this->latestRides as $ride)
                        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2 py-4" wire:key="latest-{{ $ride->id }}">
                            <div>
                                <span class="font-mono text-sm text-zinc-500">#{{ $ride->seq }}</span>
                                <span class="ml-3">{{ $ride->durationForHumans() }}</span>
                                @if ($ride->recordedAt())
                                    <span class="ml-3 text-sm text-zinc-500">{{ $ride->recordedAt()->translatedFormat('j M Y, H:i') }}</span>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-x-5 gap-y-1 font-mono text-sm tabular-nums">
                                <span>{{ number_format($ride->lean_left_deg, 1, ',', ' ') }}°&nbsp;<span class="text-zinc-400">L</span></span>
                                <span>{{ number_format($ride->lean_right_deg, 1, ',', ' ') }}°&nbsp;<span class="text-zinc-400">P</span></span>
                                <span>{{ number_format($ride->accel_g, 2, ',', ' ') }}&nbsp;<span class="text-zinc-400">g</span></span>
                                <span>{{ number_format($ride->brake_g, 2, ',', ' ') }}&nbsp;<span class="text-zinc-400">g ham.</span></span>
                                <span>
                                    @if ($ride->hasSpeed())
                                        {{ number_format($ride->speed_kmh, 0, ',', ' ') }}&nbsp;<span class="text-zinc-400">km/h</span>
                                    @else
                                        <span class="text-zinc-400">———</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
