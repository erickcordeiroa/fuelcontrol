import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const BRAZIL_CENTER = [-15.78, -47.93];
const WHEEL_ZOOM_INTERVAL_MS = 150;
const HINT_VISIBLE_MS = 1500;
const IS_MAC = /Mac|iPhone|iPad/.test(navigator.platform ?? '');

/**
 * @param {string} color
 * @param {string} label
 * @param {boolean} square
 * @returns {L.DivIcon}
 */
function pinIcon(color, label, square = false) {
    const el = document.createElement('div');
    el.textContent = label;
    Object.assign(el.style, {
        minWidth: '24px',
        height: '24px',
        padding: '0 4px',
        borderRadius: square ? '6px' : '999px',
        background: color,
        color: '#fff',
        border: '2px solid #fff',
        boxShadow: '0 1px 4px rgb(0 0 0 / 0.35)',
        font: '600 11px/20px Inter, system-ui, sans-serif',
        textAlign: 'center',
        boxSizing: 'border-box',
        transform: 'translate(-50%, -50%)',
        width: 'max-content',
    });

    return L.divIcon({ html: el, className: '', iconSize: [0, 0] });
}

function driverIcon() {
    const el = document.createElement('div');
    Object.assign(el.style, {
        width: '18px',
        height: '18px',
        borderRadius: '999px',
        background: '#F97316',
        border: '3px solid #fff',
        boxShadow: '0 0 0 6px rgb(249 115 22 / 0.25), 0 1px 4px rgb(0 0 0 / 0.35)',
        boxSizing: 'border-box',
        transform: 'translate(-50%, -50%)',
    });

    return L.divIcon({ html: el, className: '', iconSize: [0, 0] });
}

/**
 * @param {string} title
 * @param {string} subtitle
 * @returns {HTMLElement}
 */
function popupContent(title, subtitle) {
    const content = document.createElement('div');
    const heading = document.createElement('strong');
    heading.textContent = title;
    content.append(heading);

    if (subtitle) {
        const detail = document.createElement('div');
        detail.textContent = subtitle;
        detail.style.marginTop = '4px';
        content.append(detail);
    }

    return content;
}

/**
 * Rolar a página por cima do mapa não mexe nele: o zoom pela roda do mouse só funciona com Ctrl (⌘ no Mac).
 * Sem a tecla, mostra um aviso rápido e deixa a página rolar normalmente.
 *
 * @param {L.Map} map
 * @param {HTMLElement} container
 */
function requireModifierForWheelZoom(map, container) {
    const hint = document.createElement('div');
    hint.textContent = IS_MAC ? 'Use ⌘ + rolagem para dar zoom no mapa' : 'Use Ctrl + rolagem para dar zoom no mapa';
    Object.assign(hint.style, {
        position: 'absolute',
        inset: '0',
        zIndex: '1100',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'rgb(15 23 42 / 0.45)',
        color: '#fff',
        font: '600 15px/1.4 Inter, system-ui, sans-serif',
        textAlign: 'center',
        padding: '16px',
        pointerEvents: 'none',
        opacity: '0',
        transition: 'opacity 150ms ease',
    });
    container.appendChild(hint);

    let hideTimer = null;
    let lastZoomAt = 0;

    container.addEventListener('wheel', (event) => {
        if (!event.ctrlKey && !event.metaKey) {
            hint.style.opacity = '1';
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                hint.style.opacity = '0';
            }, HINT_VISIBLE_MS);

            return;
        }

        // Impede o zoom da página inteira do navegador (Ctrl + rolagem).
        event.preventDefault();
        hint.style.opacity = '0';

        const now = Date.now();
        if (now - lastZoomAt < WHEEL_ZOOM_INTERVAL_MS || event.deltaY === 0) {
            return;
        }
        lastZoomAt = now;

        map.setZoomAround(map.mouseEventToContainerPoint(event), map.getZoom() + (event.deltaY < 0 ? 1 : -1));
    }, { passive: false });
}

/**
 * Mapa de pedidos/rotas com Leaflet. Payload gerado por App\Services\Routing\RouteMapPresenter.
 *
 * Objetos do Leaflet ficam fora do estado reativo do Alpine (proxies quebram a API do mapa).
 *
 * @param {object} initialPayload
 * @param {{url: string, attribution: string, maxZoom: number}} tiles
 */
export function fleetRouteMap(initialPayload, tiles) {
    const leaflet = { map: null, layers: null, fittedKey: null };

    return {
        payload: initialPayload,

        init() {
            leaflet.map = L.map(this.$refs.canvas, { center: BRAZIL_CENTER, zoom: 4, scrollWheelZoom: false });
            requireModifierForWheelZoom(leaflet.map, this.$refs.canvas);
            L.tileLayer(tiles.url, { attribution: tiles.attribution, maxZoom: tiles.maxZoom }).addTo(leaflet.map);
            leaflet.layers = L.layerGroup().addTo(leaflet.map);

            this.render();
        },

        destroy() {
            leaflet.map?.remove();
            leaflet.map = null;
        },

        update(payload) {
            this.payload = payload;
            this.render();
        },

        render() {
            const { map, layers } = leaflet;
            const payload = this.payload;

            layers.clearLayers();

            if (payload.path.length > 1) {
                L.polyline(payload.path, { color: '#002B5C', opacity: 0.75, weight: 3 }).addTo(layers);
            }

            if (payload.origin) {
                L.marker(payload.origin, { icon: pinIcon('#1E293B', 'CD', true), title: 'Centro de distribuição' })
                    .bindPopup(popupContent('Centro de distribuição', ''))
                    .addTo(layers);
            }

            payload.points.forEach((point) => {
                const marker = L.marker([point.lat, point.lng], {
                    icon: pinIcon(point.color, point.label),
                    title: point.title,
                    draggable: point.draggable,
                })
                    .bindPopup(popupContent(point.title, point.subtitle))
                    .addTo(layers);

                if (point.draggable) {
                    marker.on('dragend', () => {
                        const { lat, lng } = marker.getLatLng();
                        this.$wire.moveCustomer(point.customerId, lat, lng);
                    });
                }
            });

            if (payload.driver) {
                L.marker(payload.driver, { icon: driverIcon(), title: payload.driver.label, zIndexOffset: 1000 })
                    .bindPopup(popupContent(payload.driver.label, ''))
                    .addTo(layers);
            }

            if (payload.fitKey !== leaflet.fittedKey) {
                const coordinates = this.allCoordinates();

                if (coordinates.length > 0) {
                    map.fitBounds(L.latLngBounds(coordinates), { padding: [48, 48], maxZoom: 16 });
                    leaflet.fittedKey = payload.fitKey;
                }
            }
        },

        allCoordinates() {
            const payload = this.payload;
            const coordinates = payload.points.map(({ lat, lng }) => [lat, lng]);

            if (payload.origin) {
                coordinates.push([payload.origin.lat, payload.origin.lng]);
            }

            if (payload.driver) {
                coordinates.push([payload.driver.lat, payload.driver.lng]);
            }

            return coordinates;
        },
    };
}
