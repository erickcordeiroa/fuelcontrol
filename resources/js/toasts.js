const SUCCESS_VISIBLE_MS = 8000;

let nextId = 1;

/**
 * Notificações laterais (canto superior direito). Sucesso some sozinho depois de alguns segundos (pausa com o mouse
 * em cima); aviso e erro ficam até a pessoa fechar. Recebe o evento "toast" disparado pelo Livewire
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

            if (item.type === 'success') {
                this.schedule(item);
            }
        },

        schedule(item) {
            clearTimeout(item.timer);
            item.timer = setTimeout(() => this.remove(item.id), SUCCESS_VISIBLE_MS);
        },

        pause(item) {
            clearTimeout(item.timer);
        },

        resume(item) {
            if (item.type === 'success') {
                this.schedule(item);
            }
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    };
}
