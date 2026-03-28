<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section class="flex h-full flex-col">
    <header>
        <h2 class="text-lg font-semibold text-fleet-ink">
            {{ __('Alterar senha') }}
        </h2>

        <p class="mt-1 text-sm text-fleet-secondary">
            {{ __('Use uma senha longa e aleatória para manter a conta segura.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 flex flex-1 flex-col">
        <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6">
            <div class="sm:col-span-2">
                <label for="update_password_current_password" class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Senha atual') }}</label>
                <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20" autocomplete="current-password" />
                <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
            </div>

            <div>
                <label for="update_password_password" class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Nova senha') }}</label>
                <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="mt-1 block w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="update_password_password_confirmation" class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Confirmar nova senha') }}</label>
                <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-fleet-border pt-6">
            <button type="submit" class="rounded-xl bg-fleet-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                {{ __('Salvar') }}
            </button>

            <x-action-message class="text-sm text-fleet-profit" on="password-updated">
                {{ __('Salvo.') }}
            </x-action-message>
        </div>
    </form>
</section>
