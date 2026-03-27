<nav class="flex flex-wrap items-center justify-end gap-2">
    @auth
        <a
            href="{{ url('/dashboard') }}"
            class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
        >
            {{ __('Painel') }}
        </a>
    @else
        <a
            href="{{ route('login') }}"
            class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
        >
            {{ __('Entrar') }}
        </a>

        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-slate-900/15 transition hover:bg-slate-800"
            >
                {{ __('Cadastrar empresa') }}
            </a>
        @endif
    @endauth
</nav>
