{{--
    Notificações laterais do sistema (resources/js/toasts.js). Mensagens de sessão viram notificação ao abrir a página;
    componentes Livewire usam App\Livewire\Concerns\SendsToasts.
--}}
@php
    $initialToasts = collect([
        ['type' => 'success', 'message' => session('status')],
        ['type' => 'warning', 'message' => session('warning')],
        ['type' => 'error', 'message' => session('error')],
    ])->filter(fn (array $toast): bool => filled($toast['message']))->values()->all();
@endphp

<div
    x-data="fleetToasts(@js($initialToasts))"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-4 top-4 z-[2000] flex flex-col items-end gap-3 sm:inset-x-auto sm:right-6 sm:top-6 sm:w-[26rem]"
    aria-live="polite"
>
    <template x-for="toast in items" :key="toast.id">
        <div
            x-transition.opacity.duration.200ms
            x-on:mouseenter="pause(toast)"
            x-on:mouseleave="resume(toast)"
            x-bind:role="toast.type === 'success' ? 'status' : 'alert'"
            class="pointer-events-auto flex w-full items-start gap-3 rounded-2xl border bg-fleet-card px-4 py-3 text-sm shadow-fleet-card"
            x-bind:class="{
                'border-fleet-success/40': toast.type === 'success',
                'border-fleet-tertiary/60': toast.type === 'warning',
                'border-fleet-danger/50': toast.type === 'error',
            }"
        >
            <span
                class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                x-bind:class="{
                    'bg-fleet-success': toast.type === 'success',
                    'bg-fleet-tertiary': toast.type === 'warning',
                    'bg-fleet-danger': toast.type === 'error',
                }"
                aria-hidden="true"
            ></span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-fleet-ink" x-text="toast.type === 'success' ? @js(__('Pronto')) : (toast.type === 'warning' ? @js(__('Atenção')) : @js(__('Não deu certo')))"></p>
                <p class="mt-0.5 text-fleet-secondary" x-text="toast.message"></p>
                <template x-if="toast.action">
                    <a x-bind:href="toast.action.url" wire:navigate x-on:click="remove(toast.id)" class="mt-1.5 inline-block font-semibold text-fleet-primary-strong underline-offset-4 hover:underline" x-text="toast.action.label"></a>
                </template>
            </div>
            <button type="button" x-on:click="remove(toast.id)" class="-mr-1 shrink-0 rounded-lg p-1 text-fleet-muted hover:bg-fleet-page hover:text-fleet-ink" aria-label="{{ __('Fechar') }}">
                <x-icons.x-mark class="h-4 w-4" />
            </button>
        </div>
    </template>
</div>
