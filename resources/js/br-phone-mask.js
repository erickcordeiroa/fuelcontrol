/**
 * Brazilian phone: up to 11 digits (DDD + number). Mobile uses 9 as first digit after DDD (9XXXX-XXXX); landline uses 8 digits (XXXX-XXXX).
 *
 * @param {string} raw
 * @returns {string} digits only, max 11
 */
export function normalizeBrPhoneDigits(raw) {
    let d = String(raw ?? '').replace(/\D/g, '');

    if (d.startsWith('55') && d.length > 11) {
        d = d.slice(-11);
    }

    return d.slice(0, 11);
}

/**
 * @param {string} raw digit string (no formatting)
 * @returns {string}
 */
export function formatBrPhoneFromDigits(raw) {
    const d = normalizeBrPhoneDigits(raw);

    if (d.length === 0) {
        return '';
    }

    if (d.length <= 2) {
        return `(${d}`;
    }

    const ddd = d.slice(0, 2);
    const rest = d.slice(2);

    if (rest.length === 0) {
        return `(${ddd}) `;
    }

    const isMobile = rest[0] === '9';

    if (isMobile) {
        if (rest.length <= 5) {
            return `(${ddd}) ${rest}`;
        }

        return `(${ddd}) ${rest.slice(0, 5)}-${rest.slice(5)}`;
    }

    if (rest.length <= 4) {
        return `(${ddd}) ${rest}`;
    }

    return `(${ddd}) ${rest.slice(0, 4)}-${rest.slice(4)}`;
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
 * Alpine x-data factory: digit buffer + Brazilian phone mask, synced to Livewire.
 *
 * @param {string} property Livewire public property name (e.g. 'phone')
 */
export function fleetBrPhoneField(property) {
    return {
        digits: '',

        format() {
            return formatBrPhoneFromDigits(this.digits);
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

        syncDigitsFromWire() {
            const wire = this.resolveWire();
            if (wire === null) {
                return;
            }

            this.digits = normalizeBrPhoneDigits(wire[property]);
        },

        init() {
            this.$nextTick(() => {
                const wire = this.resolveWire();
                if (wire === null) {
                    return;
                }

                const componentId = this.$el?.closest('[wire\\:id]')?.getAttribute('wire:id');

                this.syncDigitsFromWire();

                const watchGetter = () => {
                    const w = this.resolveWire();

                    return w === null ? undefined : w[property];
                };
                const watchCallback = (value) => {
                    this.digits = normalizeBrPhoneDigits(value);
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
                setWireProperty(wire, property, this.format());

                return;
            }

            if (e.key.length === 1 && e.key >= '0' && e.key <= '9') {
                e.preventDefault();
                if (this.hasSelection(el)) {
                    this.digits = '';
                }
                if (this.digits.length >= 11) {
                    return;
                }
                this.digits += e.key;
                setWireProperty(wire, property, this.format());

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
            this.digits = normalizeBrPhoneDigits(text);
            setWireProperty(wire, property, this.format());
        },
    };
}
