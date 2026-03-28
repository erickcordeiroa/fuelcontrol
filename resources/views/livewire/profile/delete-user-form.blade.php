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
            <h2 class="text-lg font-semibold text-fleet-ink">
                {{ __('Excluir conta') }}
            </h2>

            <p class="mt-1 text-sm text-fleet-secondary">
                {{ __('Depois de excluir a conta, todos os dados serão removidos permanentemente. Antes, salve o que precisar manter.') }}
            </p>
        </header>

        <button
            type="button"
            class="w-full shrink-0 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-500 sm:w-auto"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >{{ __('Excluir conta') }}</button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-semibold text-fleet-ink">
                {{ __('Tem certeza que deseja excluir sua conta?') }}
            </h2>

            <p class="mt-1 text-sm text-fleet-secondary">
                {{ __('Esta ação não pode ser desfeita. Digite sua senha para confirmar.') }}
            </p>

            <div class="mt-6">
                <label for="password" class="sr-only text-xs font-medium uppercase text-fleet-secondary">{{ __('Senha') }}</label>

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full max-w-md rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20"
                    placeholder="{{ __('Senha') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-fleet-border px-5 py-2.5 text-sm font-semibold text-fleet-secondary hover:bg-fleet-page"
                    x-on:click="$dispatch('close-modal', 'confirm-user-deletion')"
                >
                    {{ __('Cancelar') }}
                </button>

                <button type="submit" class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-500">
                    {{ __('Excluir conta') }}
                </button>
            </div>
        </form>
    </x-modal>
</section>
