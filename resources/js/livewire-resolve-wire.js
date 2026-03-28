/**
 * Find the Livewire component root element from a descendant node.
 * Supports Alpine x-teleport / Livewire @teleport: teleported nodes keep _x_teleportBack to the in-tree template.
 *
 * @param {Node|null|undefined} startEl
 * @returns {Element|null}
 */
export function findLivewireComponentRoot(startEl) {
    let el = startEl;

    while (el) {
        if (el instanceof Element && el.hasAttribute('wire:id')) {
            return el;
        }

        if (el._x_teleportBack) {
            el = el._x_teleportBack;

            continue;
        }

        el = el.parentElement;
    }

    return null;
}

/**
 * @param {Node|null|undefined} startEl
 * @returns {object|null} Livewire $wire proxy or legacy component API
 */
export function resolveLivewireWire(startEl) {
    const root = findLivewireComponentRoot(startEl);
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
}

/**
 * Read a Livewire public property, including dot paths (e.g. fuel_offerings.<uuid>.price_per_liter).
 * Using wire[path] on Livewire 3's $wire Proxy treats unknown keys as action methods — use $get instead.
 *
 * @param {object|null|undefined} wire
 * @param {string} path
 * @returns {unknown}
 */
export function getWireProperty(wire, path) {
    if (wire == null || path == null || path === '') {
        return undefined;
    }

    const key = String(path);

    if (typeof wire.$get === 'function') {
        return wire.$get(key);
    }

    if (!key.includes('.')) {
        return wire[key];
    }

    return undefined;
}
