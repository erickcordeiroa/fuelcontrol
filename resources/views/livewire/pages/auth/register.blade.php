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
    <div class="flex items-center gap-2">
        <a href="/" wire:navigate class="flex items-center gap-2">
            <x-application-logo class="h-10 w-10 shrink-0 fill-current text-primary" />
            <span class="font-headline text-2xl font-extrabold tracking-tighter text-on-surface">{{ config('app.name') }}</span>
        </a>
    </div>

    <div class="space-y-3">
        <h1 class="font-headline text-4xl font-extrabold leading-tight tracking-tight text-on-surface">
            {{ __('Cadastrar empresa') }}
        </h1>
        <p class="font-medium text-on-surface-variant">{{ __('Crie a conta da sua empresa para gerenciar frota, diário de bordo e relatórios.') }}</p>
    </div>

    <form wire:submit="register" class="space-y-6">
        <div class="space-y-5">
            <div class="group">
                <x-input-label for="company_name" :value="__('Nome da empresa')" class="mb-2 font-headline font-semibold !text-on-surface-variant" />
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-outline-variant transition-colors group-focus-within:text-primary">apartment</span>
                    <input
                        wire:model="company_name"
                        id="company_name"
                        name="company_name"
                        type="text"
                        required
                        autofocus
                        autocomplete="organization"
                        class="w-full rounded-xl border-0 bg-surface-container-low py-4 pl-12 pr-4 text-on-surface placeholder:text-outline-variant transition-all duration-300 focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                </div>
                <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
            </div>

            <div class="group">
                <x-input-label for="name" :value="__('Seu nome (responsável)')" class="mb-2 font-headline font-semibold !text-on-surface-variant" />
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-outline-variant transition-colors group-focus-within:text-primary">person</span>
                    <input
                        wire:model="name"
                        id="name"
                        name="name"
                        type="text"
                        required
                        autocomplete="name"
                        class="w-full rounded-xl border-0 bg-surface-container-low py-4 pl-12 pr-4 text-on-surface placeholder:text-outline-variant transition-all duration-300 focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                </div>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="group">
                <x-input-label for="email" :value="__('E-mail corporativo')" class="mb-2 font-headline font-semibold !text-on-surface-variant" />
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-outline-variant transition-colors group-focus-within:text-primary">mail</span>
                    <input
                        wire:model="email"
                        id="email"
                        name="email"
                        type="email"
                        required
                        autocomplete="username"
                        class="w-full rounded-xl border-0 bg-surface-container-low py-4 pl-12 pr-4 text-on-surface placeholder:text-outline-variant transition-all duration-300 focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="group">
                <x-input-label for="password" :value="__('Senha')" class="mb-2 font-headline font-semibold !text-on-surface-variant" />
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-outline-variant transition-colors group-focus-within:text-primary">lock</span>
                    <input
                        wire:model="password"
                        id="password"
                        name="password"
                        type="{{ $showPassword ? 'text' : 'password' }}"
                        required
                        autocomplete="new-password"
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
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="group">
                <x-input-label for="password_confirmation" :value="__('Confirmar senha')" class="mb-2 font-headline font-semibold !text-on-surface-variant" />
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-outline-variant transition-colors group-focus-within:text-primary">lock</span>
                    <input
                        wire:model="password_confirmation"
                        id="password_confirmation"
                        name="password_confirmation"
                        type="{{ $showPassword ? 'text' : 'password' }}"
                        required
                        autocomplete="new-password"
                        class="w-full rounded-xl border-0 bg-surface-container-low py-4 pl-12 pr-4 text-on-surface placeholder:text-outline-variant transition-all duration-300 focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full rounded-xl bg-gradient-to-r from-primary to-primary-dim py-4 font-headline font-bold text-on-primary shadow-lg shadow-primary/20 transition-all duration-300 hover:shadow-primary/40 active:scale-[0.98] disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="register">{{ __('Cadastrar empresa') }}</span>
            <span wire:loading wire:target="register" class="inline-flex items-center justify-center gap-2">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-on-primary/30 border-t-on-primary"></span>
                {{ __('Cadastrar empresa') }}
            </span>
        </button>

        <p class="text-center text-sm font-medium text-on-surface-variant">
            <a class="font-bold text-primary decoration-2 underline-offset-4 hover:underline" href="{{ route('login') }}" wire:navigate>{{ __('Já tem conta? Entrar') }}</a>
        </p>
    </form>
</div>
