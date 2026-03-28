<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="flex h-full flex-col">
    <header>
        <h2 class="text-lg font-semibold text-fleet-ink">
            {{ __('Informações do perfil') }}
        </h2>

        <p class="mt-1 text-sm text-fleet-secondary">
            {{ __('Atualize o nome e o e-mail da sua conta.') }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 flex flex-1 flex-col">
        <div class="flex-1 space-y-6">
            <div>
                <label for="name" class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Nome') }}</label>
                <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <label for="email" class="text-xs font-medium uppercase text-fleet-secondary">{{ __('E-mail') }}</label>
                <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                    <div>
                        <p class="mt-2 text-sm text-fleet-secondary">
                            {{ __('Seu endereço de e-mail ainda não foi verificado.') }}

                            <button wire:click.prevent="sendVerification" type="button" class="text-sm font-medium text-fleet-primary underline decoration-fleet-primary/30 hover:text-fleet-ink">
                                {{ __('Clique aqui para reenviar o e-mail de verificação.') }}
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-fleet-profit">
                                {{ __('Um novo link de verificação foi enviado para o seu e-mail.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-fleet-border pt-6">
            <button type="submit" class="rounded-xl bg-fleet-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                {{ __('Salvar') }}
            </button>

            <x-action-message class="text-sm text-fleet-profit" on="profile-updated">
                {{ __('Salvo.') }}
            </x-action-message>
        </div>
    </form>
</section>
