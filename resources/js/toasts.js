/** Quanto tempo cada tipo fica na tela; erro não está aqui porque fica até a pessoa fechar. */
const VISIBLE_MS = { success: 8000, warning: 15000 };

let nextId = 1;

/**
 * Notificações laterais (canto superior direito). Sucesso some em 8 s e aviso em 15 s (pausa com o mouse em cima);
 * erro fica até a pessoa fechar. Recebe o evento "toast" disparado pelo Livewire
 * ($this->dispatch('toast', ...)) e as mensagens de sessão da página (status, warning, error).
 *
 * @param {Array<{type: string, message: string, action?: {label: string, url: string}|null}>} initial
 */
export function fleetToasts(initial = []) {
    return {
        items: [],

        init() {
            initial.forEach((toast) => this.add(toast));
        },

        /**
         * @param {{type?: string, message: string, action?: {label: string, url: string}|null}} toast
         */
        add(toast) {
            if (!toast?.message || this.items.some((item) => item.message === toast.message)) {
                return;
            }

            const item = { id: nextId++, type: toast.type ?? 'success', message: toast.message, action: toast.action ?? null, timer: null };
            this.items.push(item);

            this.schedule(item);
        },

        schedule(item) {
            clearTimeout(item.timer);

            if (VISIBLE_MS[item.type]) {
                item.timer = setTimeout(() => this.remove(item.id), VISIBLE_MS[item.type]);
            }
        },

        pause(item) {
            clearTimeout(item.timer);
        },

        resume(item) {
            this.schedule(item);
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    };
}
