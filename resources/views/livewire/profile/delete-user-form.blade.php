<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-8">
        <header class="min-w-0 flex-1">
            <h2 class="fleet-modal-title">
                {{ __('Excluir conta') }}
            </h2>

            <p class="mt-2 text-sm font-medium text-fleet-secondary">
                {{ __('Depois de excluir a conta, todos os dados serão removidos permanentemente. Antes, salve o que precisar manter.') }}
            </p>
        </header>

        <button
            type="button"
            class="fleet-btn--danger fleet-btn--lg w-full shrink-0 sm:w-auto"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >{{ __('Excluir conta') }}</button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="fleet-modal-title">
                {{ __('Tem certeza que deseja excluir sua conta?') }}
            </h2>

            <p class="mt-2 text-sm font-medium text-fleet-secondary">
                {{ __('Esta ação não pode ser desfeita. Digite sua senha para confirmar.') }}
            </p>

            <div class="mt-6">
                <label for="password" class="sr-only text-xs font-medium uppercase text-fleet-secondary">{{ __('Senha') }}</label>

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="max-w-md"
                    placeholder="{{ __('Senha') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="fleet-btn--muted fleet-btn--lg"
                    x-on:click="$dispatch('close-modal', 'confirm-user-deletion')"
                >
                    {{ __('Cancelar') }}
                </button>

                <button type="submit" class="fleet-btn--danger fleet-btn--lg">
                    {{ __('Excluir conta') }}
                </button>
            </div>
        </form>
    </x-modal>
</section>
