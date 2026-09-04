<?php

use App\Models\Ride;
use App\Support\Pomiar;
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
            // Tabela pokazuje nazwę urządzenia i ikonę śladu — bez tego
            // po jednym zapytaniu na wiersz.
            ->with(['device', 'track'])
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
        <x-pomiary-siatka :pomiary="[
            ['lewo', 'Przechył w lewo', Pomiar::stopnie($this->records->lean_left_deg)],
            ['prawo', 'Przechył w prawo', Pomiar::stopnie($this->records->lean_right_deg)],
            ['przyspieszenie', 'Przyspieszenie', Pomiar::przeciazenie($this->records->accel_g)],
            ['hamowanie', 'Hamowanie', Pomiar::przeciazenie($this->records->brake_g)],
            ['predkosc', 'Prędkość maksymalna', Pomiar::predkosc($this->records->speed_kmh)],
        ]" />

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
                <x-rides-table :rides="$this->latestRides" />
            @endif
        </div>
    @endif
</div>
