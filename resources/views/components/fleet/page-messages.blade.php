@props(['status' => null, 'error' => null])

{{-- Mensagens que ficam até o usuário fechar (App\Livewire\Concerns\KeepsMessages). --}}
@if ($status)
    <div class="flex items-start justify-between gap-3 rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit" role="status">
        <span>{{ $status }}</span>
        <button type="button" wire:click="dismissMessages" class="shrink-0 font-semibold underline-offset-2 hover:underline">{{ __('Fechar') }}</button>
    </div>
@endif
@if ($error)
    <div class="flex items-start justify-between gap-3 rounded-xl border border-fleet-danger/30 bg-fleet-danger/10 px-4 py-3 text-sm text-fleet-danger" role="alert">
        <span>{{ $error }}</span>
        <button type="button" wire:click="dismissMessages" class="shrink-0 font-semibold underline-offset-2 hover:underline">{{ __('Fechar') }}</button>
    </div>
@endif
