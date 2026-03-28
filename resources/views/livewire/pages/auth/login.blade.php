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
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-8 flex items-center gap-2">
        <a href="/" wire:navigate class="flex items-center gap-2">
            <x-application-logo class="h-10 w-10 shrink-0 fill-current text-primary" />
            <span class="font-headline text-2xl font-extrabold tracking-tighter text-on-surface">{{ config('app.name') }}</span>
        </a>
    </div>

    <form wire:submit="login" class="space-y-6">
        <div class="space-y-5">
            <div class="group">
                <x-input-label for="email" :value="__('Email')" class="mb-2 font-headline font-semibold !text-on-surface-variant" />
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-outline-variant transition-colors group-focus-within:text-primary">mail</span>
                    <input
                        wire:model="form.email"
                        id="email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full rounded-xl border-0 bg-surface-container-low py-4 pl-12 pr-4 text-on-surface placeholder:text-outline-variant transition-all duration-300 focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                </div>
                <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
            </div>

            <div class="group">
                <div class="mb-2 flex items-center justify-between">
                    <x-input-label for="password" :value="__('Password')" class="mb-0 font-headline font-semibold !text-on-surface-variant" />
                    @if (Route::has('password.request'))
                        <a class="text-xs font-semibold text-primary transition-colors hover:text-primary-dim" href="{{ route('password.request') }}" wire:navigate>
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif
                </div>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-outline-variant transition-colors group-focus-within:text-primary">lock</span>
                    <input
                        wire:model="form.password"
                        id="password"
                        name="password"
                        type="{{ $showPassword ? 'text' : 'password' }}"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl border-0 bg-surface-container-low py-4 pl-12 pr-12 text-on-surface placeholder:text-outline-variant transition-all duration-300 focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                    <button
                        type="button"
                        wire:click="$toggle('showPassword')"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-outline-variant transition-colors hover:text-on-surface"
                    >
                        <span class="material-symbols-outlined">{{ $showPassword ? 'visibility_off' : 'visibility' }}</span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input
                wire:model="form.remember"
                id="remember"
                name="remember"
                type="checkbox"
                class="h-5 w-5 rounded border-outline-variant bg-surface-container-low text-primary focus:ring-primary"
            />
            <label class="select-none text-sm font-medium text-on-surface-variant" for="remember">{{ __('Remember me') }}</label>
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full rounded-xl bg-gradient-to-r from-primary to-primary-dim py-4 font-headline font-bold text-on-primary shadow-lg shadow-primary/20 transition-all duration-300 hover:shadow-primary/40 active:scale-[0.98] disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
            <span wire:loading wire:target="login" class="inline-flex items-center justify-center gap-2">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-on-primary/30 border-t-on-primary"></span>
                {{ __('Log in') }}
            </span>
        </button>

        @if (Route::has('register'))
            <p class="text-center text-sm font-medium text-on-surface-variant">
                {{ __('Não tem conta?') }}
                <a href="{{ route('register') }}" wire:navigate class="font-bold text-primary decoration-2 underline-offset-4 hover:underline">{{ __('Cadastrar empresa') }}</a>
            </p>
        @endif
    </form>
</div>
