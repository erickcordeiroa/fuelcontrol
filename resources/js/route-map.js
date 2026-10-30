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
        boxShadow: '0 1px 3px rgb(30 25 25 / 0.35)',
        font: '600 11px/20px "Inter Variable", system-ui, sans-serif',
        textAlign: 'center',
        boxSizing: 'border-box',
        transform: 'translate(-50%, -50%)',
        width: 'max-content',
    });

    return L.divIcon({ html: el, className: '', iconSize: [0, 0] });
}

/**
 * Motorista: ponto amarelo com a borda na cor da rota e o nome sempre visível (tela de acompanhamento na parede).
 * Parado há muito tempo: borda tracejada e "parado" junto do nome. Rota concluída: só o ponto, sem o nome,
 * para não cobrir quem ainda está na rua (todos voltam ao centro de distribuição).
 *
 * @param {{label: string, color: string, idle: boolean, finished: boolean}} driver
 */
function driverIcon(driver) {
    const wrapper = document.createElement('div');
    Object.assign(wrapper.style, {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: '4px',
        transform: 'translate(-50%, -100%)',
        width: 'max-content',
    });

    const name = document.createElement('div');
    name.textContent = driver.idle ? `${driver.label} · parado` : driver.label;
    Object.assign(name.style, {
        padding: '2px 8px',
        borderRadius: '999px',
        background: driver.color,
        color: '#fff',
        font: '600 12px/18px "Inter Variable", system-ui, sans-serif',
        boxShadow: '0 1px 3px rgb(30 25 25 / 0.35)',
        whiteSpace: 'nowrap',
    });

    const dot = document.createElement('div');
    Object.assign(dot.style, {
        width: '20px',
        height: '20px',
        borderRadius: '999px',
        background: '#EECC5D',
        border: `3px ${driver.idle ? 'dashed' : 'solid'} ${driver.color}`,
        boxShadow: '0 0 0 5px rgb(238 204 93 / 0.35), 0 1px 3px rgb(30 25 25 / 0.35)',
        boxSizing: 'border-box',
        marginBottom: '-10px',
    });

    if (driver.finished) {
        Object.assign(dot.style, { width: '14px', height: '14px', borderWidth: '2px', boxShadow: '0 1px 3px rgb(30 25 25 / 0.35)', marginBottom: '-7px' });
        wrapper.append(dot);
    } else {
        wrapper.append(name, dot);
    }

    return L.divIcon({ html: wrapper, className: '', iconSize: [0, 0] });
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
        background: 'rgb(30 25 25 / 0.6)',
        color: '#fff',
        font: '500 15px/1.4 "Inter Variable", system-ui, sans-serif',
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
        isFullscreen: false,

        init() {
            leaflet.map = L.map(this.$refs.canvas, { center: BRAZIL_CENTER, zoom: 4, scrollWheelZoom: false });
            requireModifierForWheelZoom(leaflet.map, this.$refs.canvas);
            L.tileLayer(tiles.url, { attribution: tiles.attribution, maxZoom: tiles.maxZoom }).addTo(leaflet.map);
            leaflet.layers = L.layerGroup().addTo(leaflet.map);

            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = document.fullscreenElement === this.$root;
                setTimeout(() => {
                    leaflet.map?.invalidateSize();
                    this.fitToContent();
                }, 50);
            });

            this.render();
        },

        destroy() {
            leaflet.map?.remove();
            leaflet.map = null;
        },

        /**
         * Tela cheia do mapa para deixar o acompanhamento aberto numa TV ou monitor.
         */
        toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen();

                return;
            }

            this.$root.requestFullscreen?.().catch(() => {});
        },

        update(payload) {
            this.payload = payload;
            this.render();
        },

        render() {
            const { layers } = leaflet;
            const payload = this.payload;

            layers.clearLayers();

            payload.paths.forEach((path) => {
                if (path.points.length > 1) {
                    L.polyline(path.points, path.dashed
                        ? { color: path.color, opacity: 0.7, weight: 3, dashArray: '2 8', lineCap: 'round' }
                        : { color: path.color, opacity: 0.85, weight: 4 }).addTo(layers);
                }
            });

            if (payload.origin) {
                L.marker(payload.origin, { icon: pinIcon('#1E1919', 'CD', true), title: 'Centro de distribuição' })
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

            payload.drivers.forEach((driver) => {
                L.marker([driver.lat, driver.lng], { icon: driverIcon(driver), title: driver.label, zIndexOffset: driver.finished ? 500 : (driver.idle ? 2000 : 1000) })
                    .bindPopup(popupContent(driver.label, driver.subtitle))
                    .addTo(layers);
            });

            if (payload.fitKey !== leaflet.fittedKey) {
                this.fitToContent();
            }
        },

        /**
         * Enquadra pedidos, centro de distribuição e motoristas; também ao entrar e sair da tela cheia.
         */
        fitToContent() {
            const coordinates = this.allCoordinates();

            if (leaflet.map && coordinates.length > 0) {
                leaflet.map.fitBounds(L.latLngBounds(coordinates), { padding: [48, 48], maxZoom: 16 });
                leaflet.fittedKey = this.payload.fitKey;
            }
        },

        allCoordinates() {
            const payload = this.payload;
            const coordinates = payload.points.map(({ lat, lng }) => [lat, lng]);

            if (payload.origin) {
                coordinates.push([payload.origin.lat, payload.origin.lng]);
            }

            payload.drivers.forEach(({ lat, lng }) => coordinates.push([lat, lng]));

            return coordinates;
        },
    };
}
