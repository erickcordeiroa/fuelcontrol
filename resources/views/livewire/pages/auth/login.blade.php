<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public bool $showPassword = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-8 text-center lg:text-left">
        <h1 class="font-headline text-2xl font-bold text-on-surface">{{ __('Entrar') }}</h1>
        <p class="mt-2 text-sm font-medium text-on-surface-variant">
            {{ __('Acesse sua conta para continuar.') }}
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <div>
            <x-input-label for="email" :value="__('E-mail')" />
            <x-text-input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Senha')" />
            <div class="relative">
                <x-text-input
                    wire:model="form.password"
                    id="password"
                    class="w-full pe-11"
                    :type="$showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
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
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <label for="remember" class="inline-flex cursor-pointer items-center gap-2">
                <input
                    wire:model="form.remember"
                    id="remember"
                    type="checkbox"
                    class="rounded border-outline text-primary shadow-sm focus:ring-primary/30"
                    name="remember"
                >
                <span class="text-sm text-on-surface-variant">{{ __('Lembrar-me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a
                    class="text-sm font-medium text-primary underline decoration-primary/30 underline-offset-2 hover:opacity-90"
                    href="{{ route('password.request') }}"
                    wire:navigate
                >
                    {{ __('Esqueceu a senha?') }}
                </a>
            @endif
        </div>

        <div class="pt-2">
            <x-primary-button type="submit" class="w-full sm:w-auto">
                {{ __('Entrar') }}
            </x-primary-button>
        </div>

        @if (Route::has('register'))
            <p class="text-center text-sm text-on-surface-variant">
                {{ __('Não tem conta?') }}
                <a href="{{ route('register') }}" wire:navigate class="font-semibold text-primary hover:opacity-90">{{ __('Cadastrar empresa') }}</a>
            </p>
        @endif
    </form>
</div>
