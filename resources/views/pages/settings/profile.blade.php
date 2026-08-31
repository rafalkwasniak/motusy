<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Jedna strona na całe konto: nick, dane osobowe, e-mail, hasło i usunięcie
 * konta. Wcześniej to samo było rozbite na trzy zakładki, choć w każdej
 * siedziało po jednym formularzu.
 */
new #[Title('Ustawienia konta')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules;

    public string $nickname = '';

    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->nickname = (string) Auth::user()->nickname;
        $this->name = (string) Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        // Puste imię i nazwisko trzymamy jako null, nie jako pusty ciąg —
        // pole jest nieobowiązkowe, więc „nie podano" ma być jednoznaczne.
        $validated['name'] = filled($validated['name']) ? $validated['name'] : null;

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Dane konta zapisane.'));
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Ustawienia konta') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Twoje dane, hasło i dostęp do konta') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    {{-- space-y-6, nie 8/10/12: tylko ten odstęp jest w zbudowanym arkuszu,
         a build frontu odpala Rafał ręcznie i nie ma po co go wymuszać. --}}
    <div class="max-w-lg space-y-6">

        {{-- Dane --}}
        <section>
            <flux:heading size="lg">{{ __('Dane konta') }}</flux:heading>

            <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
                <flux:input
                    wire:model="nickname"
                    :label="__('Nickname')"
                    type="text"
                    required
                    autocomplete="nickname"
                    :description="__('Tak jesteś podpisany w portalu. To jedyna nazwa, którą widzą inni.')"
                />

                <flux:input
                    wire:model="name"
                    :label="__('Full name')"
                    type="text"
                    autocomplete="name"
                    :description="__('Nieobowiązkowe i widoczne tylko dla Ciebie.')"
                />

                <div>
                    <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <div>
                            <flux:text class="mt-4">
                                {{ __('Your email address is unverified.') }}

                                <flux:link class="cursor-pointer text-sm" wire:click.prevent="resendVerificationNotification">
                                    {{ __('Click here to re-send the verification email.') }}
                                </flux:link>
                            </flux:text>

                            @if (session('status') === 'verification-link-sent')
                                {{-- Starter kit miał tu `!dark:text-green-400` — w Tailwindzie 4
                                     to niepoprawna kolejność (ważność idzie po wariancie),
                                     więc ta klasa nigdy się nie wygenerowała. Zostaje sam
                                     zielony, czytelny w obu motywach. --}}
                                <flux:text class="mt-2 font-medium !text-green-600">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </flux:text>
                            @endif
                        </div>
                    @endif
                </div>

                <flux:button variant="primary" type="submit" data-test="update-profile-button">
                    {{ __('Save') }}
                </flux:button>
            </form>
        </section>

        <flux:separator variant="subtle" />

        {{-- Hasło --}}
        <section>
            <flux:heading size="lg">{{ __('Update password') }}</flux:heading>
            <flux:subheading>{{ __('Osiem znaków, w tym wielka litera i cyfra.') }}</flux:subheading>

            <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
                <flux:input
                    wire:model="current_password"
                    :label="__('Current password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />
                <flux:input
                    wire:model="password"
                    :label="__('New password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />
                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <flux:button variant="primary" type="submit" data-test="update-password-button">
                    {{ __('Save') }}
                </flux:button>
            </form>
        </section>

        @if ($this->showDeleteUser)
            <flux:separator variant="subtle" />

            <livewire:pages::settings.delete-user-form />
        @endif
    </div>
</div>
