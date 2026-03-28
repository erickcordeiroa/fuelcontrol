@props([
    'title' => '',
    'description' => '',
])

@teleport('body')
    <div
        class="fixed inset-0 z-[70]"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="fleet-delete-confirm-title"
        x-data
        x-on:keydown.escape.window="$wire.closeDeleteModal()"
    >
        <div class="absolute inset-0 bg-fleet-ink/60 backdrop-blur-sm" wire:click="closeDeleteModal"></div>
        <div class="relative z-10 flex min-h-full items-center justify-center p-4">
            <div
                class="w-full max-w-md rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-fleet-card"
                onclick="event.stopPropagation()"
            >
                <div class="flex gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-fleet-danger/10 text-fleet-danger" aria-hidden="true">
                        <x-icons.trash class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 id="fleet-delete-confirm-title" class="fleet-modal-title">
                            {{ $title }}
                        </h3>
                        @if (filled($description))
                            <p class="mt-2 text-sm font-medium text-fleet-secondary">
                                {{ $description }}
                            </p>
                        @endif
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="closeDeleteModal" class="fleet-btn--muted fleet-btn--lg">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="button" wire:click="confirmPendingDelete" class="fleet-btn--danger fleet-btn--lg">
                        {{ __('Excluir') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endteleport
