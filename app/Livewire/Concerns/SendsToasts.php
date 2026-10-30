<?php

namespace App\Livewire\Concerns;

/**
 * Notificação lateral (resources/views/components/fleet/toasts.blade.php). Sucesso some em 8 s e aviso em 15 s;
 * erro fica até a pessoa fechar. Antes de um redirect, use session()->flash('status'|'warning'|'error').
 */
trait SendsToasts
{
    protected function toast(string $message, string $type = 'success', ?string $actionLabel = null, ?string $actionUrl = null): void
    {
        $this->dispatch('toast',
            type: $type,
            message: $message,
            action: $actionLabel !== null && $actionUrl !== null ? ['label' => $actionLabel, 'url' => $actionUrl] : null,
        );
    }

    protected function toastWarning(string $message, ?string $actionLabel = null, ?string $actionUrl = null): void
    {
        $this->toast($message, 'warning', $actionLabel, $actionUrl);
    }

    protected function toastError(string $message): void
    {
        $this->toast($message, 'error');
    }
}
