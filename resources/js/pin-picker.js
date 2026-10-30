import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const BRAZIL_CENTER = [-15.78, -47.93];

/**
 * Pino desenhado (o ícone padrão do Leaflet é uma imagem que não entra no build).
 *
 * @returns {L.DivIcon}
 */
function pinIcon() {
    const el = document.createElement('div');
    Object.assign(el.style, {
        width: '26px',
        height: '26px',
        borderRadius: '50% 50% 50% 0',
        background: '#0061FE',
        border: '3px solid #fff',
        boxShadow: '0 2px 6px rgb(30 25 25 / 0.45)',
        transform: 'translate(-50%, -100%) rotate(-45deg)',
        boxSizing: 'border-box',
        cursor: 'grab',
    });

    return L.divIcon({ html: el, className: '', iconSize: [0, 0] });
}

/**
 * Mapa do editor de endereço: clique no mapa ou arraste o pino para marcar a porta do cliente. A posição vai para
 * pinLatitude/pinLongitude do componente Livewire sem nova requisição; só vale ao salvar.
 *
 * @param {{lat: number, lng: number}|null} pin  Posição atual do cliente
 * @param {{lat: number, lng: number}|null} center  Onde abrir sem posição (centro de distribuição ou outro cliente)
 * @param {{url: string, attribution: string, maxZoom: number}} tiles
 */
export function fleetPinPicker(pin, center, tiles) {
    const leaflet = { map: null, marker: null };

    return {
        hasPin: pin !== null,

        init() {
            const start = pin ?? center;
            leaflet.map = L.map(this.$refs.canvas, {
                center: start ? [start.lat, start.lng] : BRAZIL_CENTER,
                zoom: pin ? 17 : (center ? 14 : 4),
            });
            L.tileLayer(tiles.url, { attribution: tiles.attribution, maxZoom: tiles.maxZoom }).addTo(leaflet.map);

            if (pin) {
                this.place(pin.lat, pin.lng, false);
            }

            leaflet.map.on('click', (event) => this.place(event.latlng.lat, event.latlng.lng));

            /** O modal abre com animação: recalcula o tamanho depois que ele aparece. */
            setTimeout(() => leaflet.map?.invalidateSize(), 150);
        },

        destroy() {
            leaflet.map?.remove();
            leaflet.map = null;
        },

        /**
         * @param {number} lat
         * @param {number} lng
         * @param {boolean} sync  Grava no componente (falso ao abrir, quando o valor já veio do servidor)
         */
        place(lat, lng, sync = true) {
            if (leaflet.marker) {
                leaflet.marker.setLatLng([lat, lng]);
            } else {
                leaflet.marker = L.marker([lat, lng], { icon: pinIcon(), draggable: true, autoPan: true, title: 'Local do cliente' }).addTo(leaflet.map);
                leaflet.marker.on('dragend', () => {
                    const position = leaflet.marker.getLatLng();
                    this.sync(position.lat, position.lng);
                });
            }

            this.hasPin = true;

            if (sync) {
                this.sync(lat, lng);
            }
        },

        sync(lat, lng) {
            this.$wire.$set('pinLatitude', lat.toFixed(7), false);
            this.$wire.$set('pinLongitude', lng.toFixed(7), false);
        },

        /**
         * Latitude/longitude digitadas ou coladas (aceita vírgula): move o pino e centraliza.
         */
        fromFields() {
            const lat = parseFloat(String(this.$wire.pinLatitude).replace(',', '.'));
            const lng = parseFloat(String(this.$wire.pinLongitude).replace(',', '.'));

            if (Number.isNaN(lat) || Number.isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
                return;
            }

            this.place(lat, lng, false);
            leaflet.map.setView([lat, lng], Math.max(leaflet.map.getZoom(), 16));
        },
    };
}
