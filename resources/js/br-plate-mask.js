import { findLivewireComponentRoot, getWireProperty, resolveLivewireWire } from './livewire-resolve-wire';

/**
 * Brazilian vehicle plate mask: old AAA-9999 or Mercosul AAA-9A99 (hyphen after 3 letters).
 *
 * @param {string} chars current buffer (A-Z0-9 only, max 7)
 * @param {string} ch single next character
 * @returns {string|null} uppercased char to append, or null if invalid
 */
export function canAppendPlateChar(chars, ch) {
    const c = String(ch).toUpperCase();
    const pos = chars.length;

    if (pos >= 7) {
        return null;
    }

    if (pos < 3) {
        return /[A-Z]/.test(c) ? c : null;
    }

    if (pos === 3) {
        return /[0-9]/.test(c) ? c : null;
    }

    if (pos === 4) {
        if (/[0-9]/.test(c) || /[A-Z]/.test(c)) {
            return c;
        }

        return null;
    }

    if (pos === 5 || pos === 6) {
        return /[0-9]/.test(c) ? c : null;
    }

    return null;
}

/**
 * @param {string} raw
 * @returns {string} up to 7 valid plate characters (no hyphen)
 */
export function normalizeBrPlateChars(raw) {
    const u = String(raw ?? '')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '');
    let out = '';

    for (let i = 0; i < u.length; i += 1) {
        const next = canAppendPlateChar(out, u[i]);
        if (next === null) {
            break;
        }
        out += next;
    }

    return out;
}

/**
 * @param {string} chars
 * @returns {string} display with hyphen after 3rd letter
 */
export function formatBrPlateFromChars(chars) {
    const c = String(chars ?? '');
    if (c.length === 0) {
        return '';
    }
    if (c.length <= 3) {
        return c;
    }

    return `${c.slice(0, 3)}-${c.slice(3)}`;
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
 * Alpine x-data factory: plate buffer synced to Livewire (formatted with hyphen).
 *
 * @param {string} property Livewire public property (e.g. 'plate')
 */
export function fleetBrPlateField(property) {
    return {
        chars: '',

        format() {
            return formatBrPlateFromChars(this.chars);
        },

        resolveWire() {
            return resolveLivewireWire(this.$el);
        },

        syncCharsFromWire() {
            const wire = this.resolveWire();
            if (wire === null) {
                return;
            }

            this.chars = normalizeBrPlateChars(getWireProperty(wire, property));
        },

        init() {
            this.$nextTick(() => {
                const wire = this.resolveWire();
                if (wire === null) {
                    return;
                }

                const componentId = findLivewireComponentRoot(this.$el)?.getAttribute('wire:id');

                this.syncCharsFromWire();

                const watchGetter = () => {
                    const w = this.resolveWire();

                    return w === null ? undefined : getWireProperty(w, property);
                };
                const watchCallback = (value) => {
                    this.chars = normalizeBrPlateChars(value);
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
                            this.syncCharsFromWire();
                            queueMicrotask(() => {
                                this.syncCharsFromWire();
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
                    this.chars = '';
                } else {
                    this.chars = this.chars.slice(0, -1);
                }
                setWireProperty(wire, property, this.format());

                return;
            }

            if (e.key.length === 1 && ((e.key >= '0' && e.key <= '9') || (e.key >= 'a' && e.key <= 'z') || (e.key >= 'A' && e.key <= 'Z'))) {
                e.preventDefault();
                if (this.hasSelection(el)) {
                    this.chars = '';
                }
                const next = canAppendPlateChar(this.chars, e.key);
                if (next === null) {
                    return;
                }
                this.chars += next;
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
                if (!/[0-9A-Za-z]/.test(ch)) {
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
            this.chars = normalizeBrPlateChars(text);
            setWireProperty(wire, property, this.format());
        },
    };
}
