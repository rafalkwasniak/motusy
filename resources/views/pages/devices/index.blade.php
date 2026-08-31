<?php

use App\Models\Device;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Moje urządzenia')] class extends Component {
    /** Urządzenie aktualnie edytowane; null = nic nie jest otwarte. */
    public ?int $editingId = null;

    public string $name = '';

    /**
     * @return Collection<int, Device>
     */
    #[Computed]
    public function devices(): Collection
    {
        return Auth::user()
            ->devices()
            ->withCount('rides')
            ->orderByRaw('last_seen_at IS NULL, last_seen_at DESC')
            ->get();
    }

    public function edit(int $id): void
    {
        $device = Auth::user()->devices()->findOrFail($id);

        $this->editingId = $device->id;
        $this->name = (string) $device->name;
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name');
        $this->resetValidation();
    }

    public function save(): void
    {
        $device = Auth::user()->devices()->findOrFail($this->editingId);

        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        // Puste pole znaczy „wróć do fabrycznego identyfikatora”,
        // a nie „zapisz pusty ciąg”.
        $device->name = filled($validated['name']) ? $validated['name'] : null;
        $device->save();

        unset($this->devices);
        $this->cancel();

        Flux::toast(variant: 'success', text: __('Nazwa urządzenia zapisana.'));
    }

    /**
     * Wydaje nowy token i unieważnia poprzedni.
     *
     * Pudełka wpisane starym tokenem dostaną 401 i przestaną próbować,
     * więc trzeba je skonfigurować od nowa — stąd potwierdzenie.
     */
    public function regenerateToken(): void
    {
        Auth::user()->regenerateApiToken();

        Flux::modal('confirm-token-regeneration')->close();
        Flux::toast(variant: 'success', text: __('Nowy token wydany. Przepisz go do urządzeń.'));
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Moje urządzenia') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Nazwij pudełka, żeby wiedzieć, które jest które') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    {{-- Token konta — jeden na konto, wspólny dla wszystkich pudełek
         (kontrakt telemetrii §2). Widoczny zawsze, bo przy każdej ponownej
         konfiguracji WiFi trzeba go mieć pod ręką. --}}
    <div class="mb-6 border border-zinc-200 bg-zinc-50 p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <flux:heading size="lg">{{ __('Token konta') }}</flux:heading>

        <flux:text class="mt-2">
            {{ __('Wpisz go w konfiguracji WiFi pudełka. Jeden token obsługuje wszystkie urządzenia na tym koncie.') }}
        </flux:text>

        <div class="mt-4 flex flex-wrap items-center gap-3" x-data="{ skopiowano: false }">
            {{-- `user-select` wpisany wprost, bo klasy `select-all` nie ma
                 w zbudowanym arkuszu, a build odpala Rafał ręcznie. --}}
            <code
                class="border border-zinc-200 bg-white px-4 py-3 font-mono text-lg tracking-widest dark:border-neutral-700 dark:bg-neutral-950"
                style="user-select: all"
            >{{ auth()->user()->api_token }}</code>

            <flux:button
                size="sm"
                variant="filled"
                icon="clipboard"
                x-on:click="navigator.clipboard.writeText(@js(auth()->user()->api_token)); skopiowano = true; setTimeout(() => skopiowano = false, 2000)"
            >
                <span x-text="skopiowano ? @js(__('Skopiowano')) : @js(__('Kopiuj'))"></span>
            </flux:button>

            <flux:modal.trigger name="confirm-token-regeneration">
                <flux:button size="sm" variant="subtle">{{ __('Wydaj nowy') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:modal name="confirm-token-regeneration" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Wydać nowy token?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Dotychczasowy przestanie działać. Każde pudełko, które go ma wpisane, zatrzyma wysyłanie, dopóki nie przepiszesz mu nowego. Zapisane przejazdy zostają nietknięte.') }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="regenerateToken">{{ __('Wydaj nowy') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    @if ($this->devices->isEmpty())
        <x-empty-state :heading="__('Nie ma tu jeszcze żadnego urządzenia')">
            {{ __('Pudełko dopisze się samo, gdy pierwszy raz wyśle przejazd na to konto. Nie ma osobnego parowania.') }}
        </x-empty-state>
    @else
        <div class="my-6 divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-neutral-700 dark:border-neutral-700">
            @foreach ($this->devices as $device)
                <div class="py-5" wire:key="device-{{ $device->id }}">
                    @if ($editingId === $device->id)
                        <form wire:submit="save" class="space-y-4">
                            <flux:input
                                wire:model="name"
                                :label="__('Nazwa urządzenia')"
                                :placeholder="$device->device_id"
                                type="text"
                                autofocus
                            />

                            <flux:text size="sm">
                                {{ __('Zostaw puste, żeby wrócić do fabrycznego identyfikatora.') }}
                            </flux:text>

                            <div class="flex gap-2">
                                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                                <flux:button variant="filled" type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <flux:heading class="truncate">{{ $device->displayName() }}</flux:heading>

                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 font-mono text-xs text-zinc-500">
                                    <span>{{ $device->device_id }}</span>

                                    @if ($device->fw)
                                        <span>{{ __('firmware') }} {{ $device->fw }}</span>
                                    @endif

                                    <span>{{ trans_choice('{0}bez przejazdów|{1}:count przejazd|[2,4]:count przejazdy|[5,*]:count przejazdów', $device->rides_count, ['count' => $device->rides_count]) }}</span>
                                </div>

                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    @if ($device->calibrated === false)
                                        <flux:badge size="sm" color="red">{{ __('bez kalibracji') }}</flux:badge>
                                    @endif

                                    @if ($device->last_seen_at)
                                        <flux:text size="sm">
                                            {{ __('ostatnia wysyłka') }}: {{ $device->last_seen_at->diffForHumans() }}
                                        </flux:text>
                                    @endif
                                </div>
                            </div>

                            <flux:button size="sm" variant="filled" wire:click="edit({{ $device->id }})">
                                {{ __('Zmień nazwę') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
