<x-app-layout>
    <div class="w-full space-y-8">
        <div>
            <h1 class="text-2xl font-bold text-fleet-ink">{{ __('Perfil') }}</h1>
            <p class="mt-1 text-sm text-fleet-secondary">{{ __('Gerencie seus dados e a segurança da conta.') }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 lg:items-stretch">
            <div class="flex min-h-0 flex-col rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
                <livewire:profile.update-profile-information-form />
            </div>
            <div class="flex min-h-0 flex-col rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
                <livewire:profile.update-password-form />
            </div>
        </div>

        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
