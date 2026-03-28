import { findLivewireComponentRoot, getWireProperty, resolveLivewireWire } from './livewire-resolve-wire';

const MAX_KM_DIGITS = 12;

/**
 * @param {string} raw
 * @returns {string} digits only, capped
 */
export function normalizeKmDigits(raw) {
    const d = String(raw ?? '').replace(/\D/g, '');

    return d.slice(0, MAX_KM_DIGITS);
}

/**
 * @param {string} digitStr
 * @returns {string}
 */
export function formatKmFromDigits(digitStr) {
    const d = normalizeKmDigits(digitStr);
    if (d === '') {
        return '';
    }

    const n = parseInt(d, 10);
    if (Number.isNaN(n)) {
        return '';
    }

    return n.toLocaleString('pt-BR');
}

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
 * Odometer-style integer: digits typed left-to-right, display with pt-BR thousands separators.
 *
 * @param {string} property Livewire public property (e.g. 'km_start')
 */
export function fleetKmField(property) {
    return {
        digits: '',

        format() {
            return formatKmFromDigits(this.digits);
        },

        resolveWire() {
            return resolveLivewireWire(this.$el);
        },

        wireIntToDigits(value) {
            if (value === null || value === undefined || value === '') {
                return '';
            }

            const n = parseInt(String(value), 10);
            if (Number.isNaN(n) || n < 0) {
                return '';
            }

            return String(n).slice(0, MAX_KM_DIGITS);
        },

        digitsToWireValue() {
            if (this.digits === '') {
                return null;
            }

            const n = parseInt(this.digits, 10);
            if (Number.isNaN(n)) {
                return null;
            }

            return n;
        },

        syncDigitsFromWire() {
            const wire = this.resolveWire();
            if (wire === null) {
                return;
            }

            this.digits = this.wireIntToDigits(getWireProperty(wire, property));
        },

        init() {
            this.$nextTick(() => {
                const wire = this.resolveWire();
                if (wire === null) {
                    return;
                }

                const componentId = findLivewireComponentRoot(this.$el)?.getAttribute('wire:id');

                this.syncDigitsFromWire();

                const watchGetter = () => {
                    const w = this.resolveWire();

                    return w === null ? undefined : getWireProperty(w, property);
                };
                const watchCallback = (value) => {
                    this.digits = this.wireIntToDigits(value);
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
                            this.syncDigitsFromWire();
                            queueMicrotask(() => {
                                this.syncDigitsFromWire();
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
                    this.digits = '';
                } else {
                    this.digits = this.digits.slice(0, -1);
                }
                setWireProperty(wire, property, this.digitsToWireValue());

                return;
            }

            if (e.key.length === 1 && e.key >= '0' && e.key <= '9') {
                e.preventDefault();
                if (this.hasSelection(el)) {
                    this.digits = '';
                }
                if (this.digits.length >= MAX_KM_DIGITS) {
                    return;
                }
                this.digits += e.key;
                setWireProperty(wire, property, this.digitsToWireValue());

                return;
            }

            if (e.key.length === 1) {
                e.preventDefault();
            }
        },

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
            this.digits = normalizeKmDigits(text);
            setWireProperty(wire, property, this.digitsToWireValue());
        },
    };
}
