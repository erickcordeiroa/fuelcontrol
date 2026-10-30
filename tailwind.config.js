import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        /**
         * Raios do sistema (DESIGN.md): 8px em campos, 12–16px em cartões, pílula nos botões.
         * Os nomes usados nas views (lg/xl/2xl) seguem essa escala.
         */
        borderRadius: {
            none: '0',
            sm: '0.375rem',
            DEFAULT: '0.5rem',
            md: '0.5rem',
            lg: '0.5rem',
            xl: '0.75rem',
            '2xl': '1rem',
            '3xl': '1.25rem',
            pill: '100px',
            full: '9999px',
        },
        extend: {
            colors: {
                fleet: {
                    page: '#F7F5F2',
                    sidebar: '#EEE9E2',
                    card: '#FFFFFF',
                    ink: '#1E1919',
                    secondary: '#524A3E',
                    muted: '#6B6560',
                    border: '#D3CEC9',
                    hairline: '#D3CEC9',
                    'hairline-strong': '#A69171',
                    rule: '#1E1919',
                    /** "navy" ficou como apelido da tinta para telas que já usam a classe. */
                    navy: '#1E1919',
                    'navy-darker': '#000000',
                    primary: '#0061FE',
                    'primary-strong': '#0044AF',
                    'primary-deep': '#0044AF',
                    cyan: '#0061FE',
                    surface: '#EEE9E2',
                    electric: '#DEEBFF',
                    info: '#DEEBFF',
                    tertiary: '#7A5A00',
                    warning: '#EECC5D',
                    success: '#175641',
                    profit: '#175641',
                    danger: '#B42318',
                    dark: '#000000',
                    'dark-elevated': '#292C31',
                    'on-dark': '#F7F5F2',
                    'on-dark-mute': '#8E8B87',
                },
                /** Telas de login e cadastro: mesmos papéis do sistema. */
                background: '#F7F5F2',
                'on-background': '#1E1919',
                surface: '#F7F5F2',
                'surface-bright': '#FFFFFF',
                'surface-container-lowest': '#F7F5F2',
                'surface-container-low': '#F7F5F2',
                'surface-container': '#EEE9E2',
                'surface-container-high': '#EEE9E2',
                'surface-container-highest': '#D3CEC9',
                'surface-dim': '#D3CEC9',
                'surface-variant': '#EEE9E2',
                'on-surface': '#1E1919',
                'on-surface-variant': '#524A3E',
                outline: '#A69171',
                'outline-variant': '#D3CEC9',
                primary: '#0061FE',
                'on-primary': '#F7F5F2',
                secondary: '#1E1919',
                'on-secondary': '#F7F5F2',
                tertiary: '#0044AF',
                'on-tertiary': '#F7F5F2',
                error: '#B42318',
                'on-error': '#F7F5F2',
                'inverse-surface': '#000000',
                'inverse-on-surface': '#F7F5F2',
            },
            fontFamily: {
                sans: ['"Inter Variable"', 'Inter', ...defaultTheme.fontFamily.sans],
                body: ['"Inter Variable"', 'Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk Variable"', '"Space Grotesk"', ...defaultTheme.fontFamily.sans],
                headline: ['"Space Grotesk Variable"', '"Space Grotesk"', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                'fleet-display': ['1.625rem', { lineHeight: '2.125rem', fontWeight: '500' }],
                'fleet-modal': ['1.25rem', { lineHeight: '1.75rem', fontWeight: '500' }],
                'fleet-body': ['0.875rem', { lineHeight: '1.375rem', fontWeight: '400' }],
                'fleet-label': ['0.875rem', { lineHeight: '1.25rem', fontWeight: '500' }],
            },
            boxShadow: {
                fleet: '0 1px 2px rgb(0 0 0 / 0.04)',
                'fleet-card': '0 8px 24px rgb(0 0 0 / 0.08)',
            },
            transitionTimingFunction: {
                standard: 'cubic-bezier(0.2, 0, 0, 1)',
            },
        },
    },

    plugins: [forms],
};
