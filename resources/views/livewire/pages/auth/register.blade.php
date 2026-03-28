<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $company_name = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $showPassword = false;

    /**
     * Cadastro de empresa: cria o usuário administrador (dono do tenant).
     */
    public function register(): void
    {
        $validated = $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['role'] = UserRole::Admin;

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8 text-center lg:text-left">
        <h1 class="font-headline text-2xl font-bold text-on-surface">{{ __('Cadastrar empresa') }}</h1>
        <p class="mt-2 text-sm font-medium text-on-surface-variant">
            {{ __('Crie a conta da sua empresa para gerenciar frota, diário de bordo e relatórios.') }}
        </p>
    </div>

    <form wire:submit="register" class="space-y-5">
        <div>
            <x-input-label for="company_name" :value="__('Nome da empresa')" />
            <x-text-input wire:model="company_name" id="company_name" type="text" name="company_name" required autofocus autocomplete="organization" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="name" :value="__('Seu nome (responsável)')" />
            <x-text-input wire:model="name" id="name" type="text" name="name" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('E-mail corporativo')" />
            <x-text-input wire:model="email" id="email" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Senha')" />
            <div class="relative">
                <x-text-input
                    wire:model="password"
                    id="password"
                    class="w-full pe-11"
                    :type="$showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="new-password"
                />
                <button
                    type="button"
                    wire:click="$toggle('showPassword')"
                    class="absolute inset-y-0 end-0 flex items-center pe-3 text-on-surface-variant hover:text-on-surface"
                    tabindex="-1"
                >
                    <span class="material-symbols-outlined text-xl" aria-hidden="true">{{ $showPassword ? 'visibility_off' : 'visibility' }}</span>
                    <span class="sr-only">{{ $showPassword ? __('Ocultar senha') : __('Mostrar senha') }}</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirmar senha')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center sm:justify-between">
            <a
                class="order-2 text-center text-sm font-medium text-on-surface-variant underline decoration-outline underline-offset-2 hover:text-on-surface sm:order-1 sm:text-left"
                href="{{ route('login') }}"
                wire:navigate
            >
                {{ __('Já tem conta? Entrar') }}
            </a>

            <x-primary-button type="submit" class="order-1 w-full sm:order-2 sm:w-auto">
                {{ __('Cadastrar empresa') }}
            </x-primary-button>
        </div>
    </form>
</div>
