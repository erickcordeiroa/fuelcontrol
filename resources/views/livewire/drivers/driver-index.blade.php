<div class="w-full space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="fleet-page-title">{{ __('Motoristas') }}</h1>
            <p class="fleet-page-lead">{{ __('CNH, celular e acesso ao app do motorista.') }}</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="fleet-btn--primary">
            <x-icons.plus class="h-5 w-5 shrink-0" />
            {{ __('Novo motorista') }}
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
        <table class="min-w-full divide-y divide-fleet-border text-fleet-body">
            <thead class="fleet-table-head">
                <tr>
                    <th class="px-4 py-3">{{ __('Nome') }}</th>
                    <th class="px-4 py-3">{{ __('CNH') }}</th>
                    <th class="px-4 py-3">{{ __('Celular') }}</th>
                    <th class="px-4 py-3">{{ __('App') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fleet-border">
                @forelse ($drivers as $driver)
                    <tr wire:key="d-{{ $driver->id }}">
                        <td class="px-4 py-3 font-medium text-fleet-ink">{{ $driver->name }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $driver->license_number }}</td>
                        <td class="px-4 py-3 text-fleet-secondary tabular-nums">{{ $driver->formattedPhone() ?? '—' }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $driver->linked_user_id ? __('Com acesso') : __('Sem senha') }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center justify-end gap-1">
                                <button
                                    type="button"
                                    wire:click="openEditModal({{ $driver->id }})"
                                    class="fleet-icon-btn"
                                    aria-label="{{ __('Editar') }}"
                                    title="{{ __('Editar') }}"
                                >
                                    <x-icons.pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    wire:click="openDeleteModal({{ $driver->id }})"
                                    class="fleet-icon-btn fleet-icon-btn--danger"
                                    aria-label="{{ __('Excluir') }}"
                                    title="{{ __('Excluir') }}"
                                >
                                    <x-icons.trash class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-fleet-muted">{{ __('Nenhum motorista cadastrado.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $drivers->links() }}
        </div>
    </div>

    @if ($showDeleteModal)
        <x-fleet.delete-confirm-modal
            :title="__('Remover motorista?')"
            :description="__('Esta ação não pode ser desfeita. O motorista será excluído permanentemente.')"
        />
    @endif

    @if ($showModal)
        @teleport('body')
            <div
                class="fixed inset-0 z-[60]"
                role="dialog"
                aria-modal="true"
                x-data
                x-on:keydown.escape.window="$wire.closeModal()"
            >
                <div class="absolute inset-0 bg-fleet-ink/50 backdrop-blur-[1px]" wire:click="closeModal"></div>
                <div
                    class="relative z-10 flex min-h-full items-start justify-center overflow-y-auto px-4 pb-10 pt-32 sm:px-4 sm:pb-10 sm:pt-36 lg:pt-40"
                >
                    <div
                        class="w-full max-w-2xl rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-fleet-card"
                        onclick="event.stopPropagation()"
                        wire:key="driver-modal-{{ $editingId ?? 'new' }}"
                    >
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <h2 class="fleet-modal-title">
                                {{ $editingId ? __('Editar motorista') : __('Novo motorista') }}
                            </h2>
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="fleet-icon-btn fleet-icon-btn--ghost"
                                aria-label="{{ __('Fechar') }}"
                            >
                                <x-icons.x-mark class="h-5 w-5" />
                            </button>
                        </div>

                        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="fleet-label" for="driver-name">{{ __('Nome') }}</label>
                                <input id="driver-name" type="text" wire:model="name" class="fleet-field" />
                                @error('name') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="driver-license">{{ __('CNH') }}</label>
                                <input id="driver-license" type="text" wire:model="license_number" class="fleet-field" />
                                @error('license_number') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="driver-phone">{{ __('Telefone') }}</label>
                                <input
                                    id="driver-phone"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    x-data="fleetBrPhoneField('phone')"
                                    x-bind:value="format()"
                                    x-on:keydown="onKeydown($event)"
                                    x-on:beforeinput="onBeforeInput($event)"
                                    x-on:paste="onPaste($event)"
                                    placeholder="(00) 00000-0000"
                                    class="fleet-field"
                                />
                                @error('phone') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="fleet-label" for="driver-app-password">{{ __('Senha do app') }}</label>
                                <input id="driver-app-password" type="password" wire:model="app_password" autocomplete="new-password" class="fleet-field" />
                                <p class="mt-1 text-xs text-fleet-muted">
                                    @if ($hasAppAccess)
                                        {{ __('Acesso ao app ativo. Preencha só para trocar a senha; o motorista precisará entrar de novo no celular.') }}
                                    @else
                                        {{ __('O motorista entra no app com o celular acima e esta senha (mínimo de 6 caracteres).') }}
                                    @endif
                                </p>
                                @error('app_password') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex flex-wrap gap-2 sm:col-span-2">
                                <button type="submit" class="fleet-btn--primary fleet-btn--lg">{{ __('Salvar') }}</button>
                                <button type="button" wire:click="closeModal" class="fleet-btn--muted fleet-btn--lg">{{ __('Cancelar') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
