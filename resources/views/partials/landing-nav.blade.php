<nav class="flex items-center justify-end gap-1 sm:gap-2" aria-label="{{ __('Acesso') }}">
    @auth
        <a href="{{ route('reports') }}" class="fleet-btn--primary px-3 sm:px-4">{{ __('Acessar o sistema') }}</a>
    @else
        <a href="{{ route('login') }}" class="fleet-btn px-3 text-fleet-ink hover:bg-fleet-page sm:px-4">{{ __('Entrar') }}</a>

        @if (Route::has('register'))
            <a href="{{ route('register') }}" class="fleet-btn--outline whitespace-nowrap px-3 sm:px-4">{{ __('Cadastrar empresa') }}</a>
        @endif
    @endauth
</nav>
