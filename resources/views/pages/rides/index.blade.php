<?php

use App\Models\Ride;
use App\Support\Pomiar;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Przejazdy')] class extends Component {
    use WithPagination;

    /** Filtr po urządzeniu; pusty = wszystkie. */
    #[Url]
    public string $device = '';

    /** Przejazd wskazany do usunięcia. */
    public ?int $deletingId = null;

    public function updatedDevice(): void
    {
        $this->resetPage();
    }

    /**
     * @return Collection<int, \App\Models\Device>
     */
    #[Computed]
    public function devices(): Collection
    {
        return Auth::user()->devices()->orderBy('name')->get();
    }

    /**
     * Kolejność wynika z `seq`, nie z czasu — urządzenie nie ma zegara
     * czasu rzeczywistego, więc data bywa pusta (docs/api-telemetria.md §1).
     *
     * @return LengthAwarePaginator<int, Ride>
     */
    #[Computed]
    public function rides(): LengthAwarePaginator
    {
        return Auth::user()
            ->rides()
            // Bez tego nazwa urządzenia kosztowałaby jedno zapytanie na wiersz.
            ->with('device')
            ->when($this->device !== '', fn ($q) => $q->where('device_id', $this->device))
            ->orderByDesc('seq')
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;

        Flux::modal('confirm-ride-deletion')->show();
    }

    public function deleteRide(): void
    {
        $ride = Auth::user()->rides()->findOrFail($this->deletingId);

        // Miękko — twarde kasowanie sprawiłoby, że przejazd wróciłby
        // przy następnej wysyłce z urządzenia.
        $ride->delete();

        $this->deletingId = null;
        unset($this->rides);

        Flux::modal('confirm-ride-deletion')->close();
        Flux::toast(variant: 'success', text: __('Przejazd usunięty.'));
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Przejazdy') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Pełna historia zapisana przez Twoje urządzenia') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    @if ($this->devices->count() > 1)
        <div class="mb-6 max-w-xs">
            <flux:select wire:model.live="device" :label="__('Urządzenie')">
                <flux:select.option value="">{{ __('Wszystkie urządzenia') }}</flux:select.option>
                @foreach ($this->devices as $d)
                    <flux:select.option value="{{ $d->device_id }}">{{ $d->displayName() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    @endif

    @if ($this->rides->isEmpty())
        <x-rides-empty-state />
    @else
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
                        <th class="px-4 py-3"><span class="sr-only">{{ __('Akcje') }}</span></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-neutral-700">
                    @foreach ($this->rides as $ride)
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $this->rides->links() }}
        </div>
    @endif

    <flux:modal name="confirm-ride-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Usunąć ten przejazd?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Zniknie z historii w portalu. Na ekranie urządzenia nic się nie zmieni.') }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteRide">{{ __('Usuń przejazd') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
