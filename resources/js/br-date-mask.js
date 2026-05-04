import { findLivewireComponentRoot, getWireProperty, resolveLivewireWire } from './livewire-resolve-wire';

/**
 * @param {string} raw
 * @returns {string} digits only, max 8 (ddmmyyyy)
 */
export function normalizeBrDateDigits(raw) {
    return String(raw ?? '')
        .replace(/\D/g, '')
        .slice(0, 8);
}

/**
 * @param {string} digitStr
 * @returns {string} dd/mm/yyyy display
 */
export function formatBrDateFromDigits(digitStr) {
    const d = normalizeBrDateDigits(digitStr);
    if (d.length === 0) {
        return '';
    }
    if (d.length <= 2) {
        return d;
    }
    if (d.length <= 4) {
        return `${d.slice(0, 2)}/${d.slice(2)}`;
    }

    return `${d.slice(0, 2)}/${d.slice(2, 4)}/${d.slice(4)}`;
}

/**
 * @param {unknown} iso Y-m-d or null
 * @returns {string} 8 digits ddmmyyyy
 */
export function isoYmdToBrDigits(iso) {
    if (iso === null || iso === undefined || iso === '') {
        return '';
    }

    const s = String(iso).trim();
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) {
        return '';
    }

    return `${m[3]}${m[2]}${m[1]}`;
}

/**
 * @param {string} digits 8 chars ddmmyyyy
 * @returns {string|null} Y-m-d
 */
export function brDigitsToIsoYmd(digits) {
    const d = normalizeBrDateDigits(digits);
    if (d.length !== 8) {
        return null;
    }

    const dd = parseInt(d.slice(0, 2), 10);
    const mm = parseInt(d.slice(2, 4), 10);
    const yyyy = parseInt(d.slice(4, 8), 10);

    if (Number.isNaN(dd) || Number.isNaN(mm) || Number.isNaN(yyyy)) {
        return null;
    }

    if (mm < 1 || mm > 12 || dd < 1 || dd > 31 || yyyy < 1 || yyyy > 9999) {
        return null;
    }

    const utc = new Date(Date.UTC(yyyy, mm - 1, dd));
    if (utc.getUTCFullYear() !== yyyy || utc.getUTCMonth() !== mm - 1 || utc.getUTCDate() !== dd) {
        return null;
    }

    return `${String(yyyy).padStart(4, '0')}-${String(mm).padStart(2, '0')}-${String(dd).padStart(2, '0')}`;
}

/**
 * @param {string} text pasted
 * @returns {string} digit buffer max 8
 */
export function parseBrDatePaste(text) {
    const t = String(text ?? '').trim();
    const iso = t.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (iso) {
        return `${iso[3]}${iso[2]}${iso[1]}`;
    }

    const br = t.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (br) {
        const dd = br[1].padStart(2, '0');
        const mm = br[2].padStart(2, '0');
        const yyyy = br[3];

        return `${dd}${mm}${yyyy}`;
    }

    return normalizeBrDateDigits(t);
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
 * Alpine x-data: data brasileira (dd/mm/aaaa) sincronizada com Livewire em Y-m-d.
 *
 * @param {string} property nome da propriedade pública Livewire (ex.: 'startDate')
 * @param {{ live?: boolean }} options live=false reduz round-trips (commit principalmente com 8 dígitos válidos + blur)
 */
export function fleetBrDateField(property, options = {}) {
    const live = options.live !== false;

    return {
        digits: '',
        live,

        format() {
            return formatBrDateFromDigits(this.digits);
        },

        resolveWire() {
            return resolveLivewireWire(this.$el);
        },

        syncDigitsFromWire() {
            const wire = this.resolveWire();
            if (wire === null) {
                return;
            }

            this.digits = isoYmdToBrDigits(getWireProperty(wire, property));
        },

        pushWireFromDigits() {
            const wire = this.resolveWire();
            if (wire === null) {
                return;
            }

            if (this.digits.length === 0) {
                setWireProperty(wire, property, '');

                return;
            }

            if (this.digits.length !== 8) {
                if (this.live) {
                    return;
                }

                return;
            }

            const iso = brDigitsToIsoYmd(this.digits);
            if (iso !== null) {
                setWireProperty(wire, property, iso);
            }
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
                    this.digits = isoYmdToBrDigits(value);
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
                this.pushWireFromDigits();

                return;
            }

            if (e.key.length === 1 && e.key >= '0' && e.key <= '9') {
                e.preventDefault();
                if (this.hasSelection(el)) {
                    this.digits = '';
                }
                if (this.digits.length >= 8) {
                    return;
                }
                this.digits += e.key;
                this.pushWireFromDigits();

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
            this.digits = parseBrDatePaste(text);
            this.pushWireFromDigits();
        },

        onBlur() {
            if (this.digits.length === 0) {
                this.pushWireFromDigits();

                return;
            }

            if (this.digits.length !== 8 || brDigitsToIsoYmd(this.digits) === null) {
                this.syncDigitsFromWire();
            }
        },
    };
}
