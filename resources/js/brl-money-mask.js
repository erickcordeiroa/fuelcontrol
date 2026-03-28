/**
 * Block typing of non-digit characters. Allows navigation, editing keys, and Ctrl/Cmd shortcuts (e.g. paste).
 *
 * @param {KeyboardEvent} event
 */
export function allowDigitsOnlyKeydown(event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    const { key } = event;

    if (key.length !== 1) {
        return;
    }

    if (key >= '0' && key <= '9') {
        return;
    }

    event.preventDefault();
}

const MAX_MINOR = 999999999999;

/**
 * @param {object|null|undefined} wire
 */
function setWireProperty(wire, property, value) {
    if (wire == null) {
        return;
    }

    if (typeof wire.$set === 'function') {
        wire.$set(property, value);

        return;
    }

    if (typeof wire.set === 'function') {
        wire.set(property, value);
    }
}

/**
 * Alpine x-data factory: right-aligned digit buffer (calculator-style) so cursor position cannot corrupt the value.
 * Syncs formatted string to Livewire via $wire.set(property, ...).
 *
 * @param {string} property Livewire public property name (e.g. 'toll')
 * @param {number} fractionDigits 2 for R$/liters, 4 for price per liter (R$/L)
 */
export function fleetBrlMoneyField(property, fractionDigits) {
    return {
        minor: 0,

        fractionDigits,

        parseMinor(s) {
            return parseInt(String(s ?? '').replace(/\D/g, '') || '0', 10);
        },

        format() {
            return formatBrlFromDigits(String(this.minor), this.fractionDigits);
        },

        resolveWire() {
            const root = this.$el?.closest('[wire\\:id]');
            if (! root) {
                return null;
            }

            const component = root.__livewire;
            if (component?.$wire) {
                return component.$wire;
            }

            const id = root.getAttribute('wire:id');
            if (! id || typeof window.Livewire?.find !== 'function') {
                return null;
            }

            return window.Livewire.find(id);
        },

        syncMinorFromWire() {
            const wire = this.resolveWire();
            if (wire === null) {
                return;
            }

            this.minor = this.parseMinor(wire[property]);
        },

        init() {
            this.$nextTick(() => {
                const wire = this.resolveWire();
                if (wire === null) {
                    return;
                }

                const componentId = this.$el?.closest('[wire\\:id]')?.getAttribute('wire:id');

                this.syncMinorFromWire();

                // Livewire's wire proxy does not expose .watch / .$watch as callable functions from plain JS.
                // Use Alpine's watcher on the reactive $wire-backed value (this.$watch or Alpine.watch).
                const watchGetter = () => {
                    const w = this.resolveWire();

                    return w === null ? undefined : w[property];
                };
                const watchCallback = (value) => {
                    this.minor = this.parseMinor(value);
                };

                if (typeof this.$watch === 'function') {
                    this._offWirePropertyWatch = this.$watch(watchGetter, watchCallback);
                } else if (typeof window.Alpine?.watch === 'function') {
                    this._offWirePropertyWatch = window.Alpine.watch(watchGetter, watchCallback);
                }

                if (typeof window.Livewire?.hook === 'function' && componentId) {
                    this._livewireCommitOff = window.Livewire.hook('commit', ({ component, succeed }) => {
                        if (String(component?.id ?? '') !== String(componentId ?? '')) {
                            return;
                        }

                        succeed(() => {
                            this.syncMinorFromWire();
                            queueMicrotask(() => {
                                this.syncMinorFromWire();
                            });
                        });
                    });
                }
            });
        },

        destroy() {
            if (typeof this._offWirePropertyWatch === 'function') {
                this._offWirePropertyWatch();
                this._offWirePropertyWatch = null;
            }

            if (typeof this._livewireCommitOff === 'function') {
                this._livewireCommitOff();
                this._livewireCommitOff = null;
            }
        },

        hasSelection(el) {
            return el.selectionStart !== el.selectionEnd;
        },

        onKeydown(e) {
            const wire = this.resolveWire();
            if (wire === null) {
                return;
            }

            if (e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }

            const el = e.target;
            if (e.key === 'Backspace' || e.key === 'Delete') {
                e.preventDefault();
                if (this.hasSelection(el)) {
                    this.minor = 0;
                } else {
                    this.minor = Math.floor(this.minor / 10);
                }
                setWireProperty(wire, property, this.format());

                return;
            }

            if (e.key.length === 1 && e.key >= '0' && e.key <= '9') {
                e.preventDefault();
                if (this.hasSelection(el)) {
                    this.minor = 0;
                }
                const digit = parseInt(e.key, 10);
                const next = this.minor * 10 + digit;
                this.minor = Math.min(next, MAX_MINOR);
                setWireProperty(wire, property, this.format());

                return;
            }

            // Letters, space, comma, etc. (single-character keys only).
            if (e.key.length === 1) {
                e.preventDefault();
            }
        },

        /**
         * Blocks insertion paths that skip keydown (mobile, some browsers).
         */
        onBeforeInput(e) {
            if (e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }

            if (e.inputType === 'insertFromPaste') {
                return;
            }

            if (e.inputType !== 'insertText' || e.data == null || e.data === '') {
                return;
            }

            for (let i = 0; i < e.data.length; i += 1) {
                const ch = e.data[i];
                if (ch < '0' || ch > '9') {
                    e.preventDefault();

                    return;
                }
            }
        },

        onPaste(e) {
            const wire = this.resolveWire();
            if (wire === null) {
                return;
            }

            e.preventDefault();
            const text = e.clipboardData?.getData('text') ?? '';
            this.minor = this.parseMinor(text);
            setWireProperty(wire, property, this.format());
        },
    };
}

/**
 * Brazilian currency-style formatting from digit input (right-aligned minor units).
 *
 * @param {string} raw
 * @param {number} fractionDigits 2 for R$ amounts, 4 for price per liter
 * @returns {string}
 */
export function formatBrlFromDigits(raw, fractionDigits) {
    const digits = String(raw ?? '').replace(/\D/g, '');

    if (digits === '') {
        return `0,${'0'.repeat(fractionDigits)}`;
    }

    const n = parseInt(digits, 10);
    if (Number.isNaN(n)) {
        return `0,${'0'.repeat(fractionDigits)}`;
    }

    const divisor = 10 ** fractionDigits;
    const intPart = Math.floor(n / divisor);
    const fracPart = String(n % divisor).padStart(fractionDigits, '0');
    const intStr = String(intPart).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${intStr},${fracPart}`;
}
