<div class="fleet-panel space-y-3">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="fleet-section-title">{{ __('Centro de distribuição') }}</p>
            @if ($depot && ! $editing)
                <p class="mt-1 text-sm text-fleet-ink">{{ $depotAddress ?: __('Localização definida') }}</p>
                <p class="text-xs text-fleet-muted tabular-nums">{{ number_format($depot['lat'], 5, ',', '') }}, {{ number_format($depot['lng'], 5, ',', '') }} · {{ __('as rotas saem e voltam daqui') }}</p>
            @elseif (! $editing)
                <p class="mt-1 text-sm text-fleet-tertiary">{{ __('Não cadastrado: as rotas começam na primeira parada e não traçam a volta.') }}</p>
            @endif
        </div>
        @unless ($editing)
            <button type="button" wire:click="edit" class="fleet-btn--outline fleet-btn--sm shrink-0">{{ $depot ? __('Alterar') : __('Cadastrar') }}</button>
        @endunless
    </div>

    @if ($editing)
        <form wire:submit="save" class="space-y-3">
            <div>
                <label class="fleet-label" for="depot-address">{{ __('Endereço') }}</label>
                <div class="flex gap-2">
                    <input id="depot-address" type="text" wire:model="address" class="fleet-field" placeholder="{{ __('Rua, número, bairro, cidade - UF') }}" />
                    <button type="button" wire:click="locate" wire:loading.attr="disabled" wire:target="locate" class="fleet-btn--outline fleet-btn--sm shrink-0">{{ __('Localizar') }}</button>
                </div>
                @error('address') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="fleet-label" for="depot-latitude">{{ __('Latitude') }}</label>
                    <input id="depot-latitude" type="text" inputmode="decimal" wire:model="latitude" class="fleet-field tabular-nums" placeholder="-24,4960" />
                    @error('latitude') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="fleet-label" for="depot-longitude">{{ __('Longitude') }}</label>
                    <input id="depot-longitude" type="text" inputmode="decimal" wire:model="longitude" class="fleet-field tabular-nums" placeholder="-47,8470" />
                    @error('longitude') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-fleet-muted">{{ __('"Localizar" preenche a latitude e a longitude pelo endereço. Também dá para colar do Google Maps (clique com o botão direito no local).') }}</p>
            <div class="flex gap-2">
                <button type="submit" class="fleet-btn--primary fleet-btn--sm">{{ __('Salvar') }}</button>
                <button type="button" wire:click="cancel" class="fleet-btn--muted fleet-btn--sm">{{ __('Cancelar') }}</button>
            </div>
        </form>
    @endif
</div>
