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
     * daje tu null, a nie zero. Tak samo `max_noise_db`.
     *
     * Hałas liczymy **po całym koncie**, dokładnie jak prędkość maksymalną —
     * decyzja Rafała z 6 września 2026. Szkic wdrożenia
     * (docs/api-halas-implementacja-laravel.md §5.2) odradza mieszanie serii
     * pomiarowych o różnym `noise_cal`, bo leżą na przesuniętej skali. Rekord
     * konta jest tu świadomie liczony ponad tym podziałem: to ta sama wartość
     * z przejazdu co prędkość i ma się zachowywać tak samo.
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
            ->selectRaw('MAX(max_noise_db) AS max_noise_db')
            // Najgłośniejszy z pomiarów **obciętych** przez przetwornik.
            // Gdy zrówna się z rekordem, znaczy to, że rekord padł na
            // obciętym pomiarze i prawdziwy szczyt był wyższy — wtedy
            // kafel pokazuje „≥". Inaczej rekord konta udawałby dokładny.
            ->selectRaw('MAX(CASE WHEN noise_clipped > 0 THEN max_noise_db END) AS max_noise_db_clipped')
            ->selectRaw('COUNT(*) AS rides_count')
            ->first();
    }

    /**
     * Czy rekord hałasu konta padł na pomiarze obciętym przez przetwornik.
     */
    #[Computed]
    public function noiseRecordIsClipped(): bool
    {
        $rekord = $this->records->max_noise_db;

        return $rekord !== null
            && $this->records->max_noise_db_clipped !== null
            && (float) $this->records->max_noise_db_clipped === (float) $rekord;
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
            ['halas', 'Hałas', Pomiar::halas($this->records->max_noise_db, $this->noiseRecordIsClipped)],
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
