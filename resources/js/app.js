import './bootstrap';
import Chart from 'chart.js/auto';
import { allowDigitsOnlyKeydown, fleetBrlMoneyField, formatBrlFromDigits } from './brl-money-mask';
import { fleetBrPhoneField, formatBrPhoneFromDigits } from './br-phone-mask';
import { fleetBrPlateField, formatBrPlateFromChars } from './br-plate-mask';
import { fleetKmField, formatKmFromDigits } from './br-km-mask';

window.fleetFormatBrlDigits = formatBrlFromDigits;
window.fleetAllowDigitsOnlyKeydown = allowDigitsOnlyKeydown;
window.fleetBrlMoneyField = fleetBrlMoneyField;
window.fleetBrPhoneField = fleetBrPhoneField;
window.fleetFormatBrPhoneFromDigits = formatBrPhoneFromDigits;
window.fleetBrPlateField = fleetBrPlateField;
window.fleetFormatBrPlateFromChars = formatBrPlateFromChars;
window.fleetKmField = fleetKmField;
window.fleetFormatKmFromDigits = formatKmFromDigits;

window.fleetCharts = {
    instances: {},

    destroy(id) {
        if (this.instances[id]) {
            this.instances[id].destroy();
            delete this.instances[id];
        }
    },

    line(id, labels, fuelCost, otherExpenses) {
        const canvas = document.getElementById(id);
        if (!canvas) {
            return;
        }

        this.destroy(id);

        this.instances[id] = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Combustível (R$)',
                        data: fuelCost,
                        borderColor: '#002B5C',
                        backgroundColor: 'rgba(0, 43, 92, 0.08)',
                        tension: 0.35,
                        fill: true,
                    },
                    {
                        label: 'Outras despesas (R$)',
                        data: otherExpenses,
                        borderColor: '#64748B',
                        backgroundColor: 'rgba(100, 116, 139, 0.08)',
                        tension: 0.35,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                    },
                },
            },
        });
    },

    horizontalBars(id, rows) {
        const canvas = document.getElementById(id);
        if (!canvas) {
            return;
        }

        this.destroy(id);

        if (!rows || rows.length === 0) {
            return;
        }

        const labels = rows.map((r) => r.label);
        const data = rows.map((r) => r.km_per_liter);
        const colors = data.map((v) => {
            if (v >= 3) {
                return '#10b981';
            }
            if (v >= 2) {
                return '#002B5C';
            }

            return '#F97316';
        });

        this.instances[id] = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'km/L',
                        data,
                        backgroundColor: colors,
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                    },
                },
            },
        });
    },
};
