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
    <div class="mb-6 text-center">
        <h1 class="fleet-modal-title text-center">{{ __('Cadastrar empresa') }}</h1>
        <p class="mt-2 text-sm font-medium text-fleet-secondary">{{ __('Crie a conta da sua empresa para gerenciar frota, diário de bordo e relatórios.') }}</p>
    </div>

    <form wire:submit="register">
        <div>
            <x-input-label for="company_name" :value="__('Nome da empresa')" />
            <x-text-input wire:model="company_name" id="company_name" type="text" name="company_name" required autofocus autocomplete="organization" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="name" :value="__('Seu nome (responsável)')" />
            <x-text-input wire:model="name" id="name" type="text" name="name" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" :value="__('E-mail corporativo')" />
            <x-text-input wire:model="email" id="email" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Senha')" />

            <x-text-input wire:model="password" id="password" type="password" name="password" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirmar senha')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6 flex items-center justify-end">
            <a class="rounded-md text-sm text-fleet-secondary underline hover:text-fleet-ink focus:outline-none focus:ring-2 focus:ring-fleet-primary/30 focus:ring-offset-2" href="{{ route('login') }}" wire:navigate>
                {{ __('Já tem conta? Entrar') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Cadastrar empresa') }}
            </span>
        </button>

        <p class="text-center text-sm font-medium text-on-surface-variant">
            <a class="font-bold text-primary decoration-2 underline-offset-4 hover:underline" href="{{ route('login') }}" wire:navigate>{{ __('Já tem conta? Entrar') }}</a>
        </p>
    </form>
</div>
